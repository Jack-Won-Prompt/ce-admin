<?php

namespace App\Console\Commands;

use App\Support\WithworksSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 위드웍스 처방전 첨부파일을 받아 온다 (2026-09-29 지시 — 최근 3개월치만 · 운영 디스크에).
 *
 * ## 파일이 어디 있나
 *
 * 저쪽 소스(`AccountAddInfoController::store`)는 첨부를 `Storage::disk('public')` 의
 * `colo/` 아래에 담는다. 표는 `account_add_information_details` 이고, 실제 파일 이름은
 * `refile_name` 이다(`file_path` 칸은 열아홉 만 줄 모두 비어 있다).
 *
 * 그래서 파일은 저쪽 웹서버의 `/storage/colo/<refile_name>` 으로 열린다. 2026-09-29 에
 * 표본 마흔 장을 받아 보니 마흔 장 모두 200 · image/jpeg 로 열렸고 평균 959KB 였다.
 *
 * ## 왜 3개월치인가
 *
 * 전체는 열아홉 만 장이고 최근 3년치만 해도 10만 3천 장 · 94GB 다. 운영 디스크는 29GB 중
 * 23GB 만 남아 있어 3년치는 들어가지 않는다(2026-09-29 확인). 3개월치는 11,301장 ·
 * 10.3GB 라 들어간다.
 *
 * S3 는 뒤로 미뤘다(2026-09-29 지시) — 지금은 운영 디스크에 담는다. 그래서 받기 전에
 * 남은 자리를 먼저 재고, 모자라면 시작하지 않는다. 열 만 장을 받다가 디스크가 차면
 * 웹서버가 함께 멈춘다.
 *
 * ## 첫 장은 처방전 그림이 된다
 *
 * 우리 관례는 **올린 처방전 그림은 `prescriptions.image_path`**, 그 밖의 서류(신분증ㆍ
 * 결과지…)는 `prescription_attachments` 다(`PrescriptionController` 업로드 자리).
 *
 * 주문 등록 화면의 「첨부」 목록(`OrderController::faxDocs`)은 그 둘을 함께 세우는데,
 * 「처방전」 줄만은 `image_path` 를 읽는다. 받은 것을 모두 첨부로만 담으면 그 줄이
 * 「처방전 이미지가 없습니다」로 서서, 사진이 있는데 없다고 보인다.
 *
 * 그래서 한 처방전의 **첫 장**(원천 번호가 가장 작은 것)은 `image_path` 로 담고 첨부 줄은
 * 만들지 않는다. 둘째 장부터 첨부다 — 저쪽은 앞뒤ㆍ여러 쪽을 여러 장으로 올린다.
 *
 * ## 이어받기
 *
 * 한 장씩 남의 웹서버에서 받으므로 중간에 끊긴다. `ww_detail_id` 로 이미 받은 것을 가려
 * 건너뛴다 — 다시 돌리면 못 받은 것만 받는다. 첫 장은 `image_path` 에 담긴 파일 이름으로
 * 가린다.
 *
 * ## 조심할 것
 *
 * 그 주소는 **로그인 없이 열린다**. 우리가 받는 길이면서 저쪽에 알려야 할 것이기도 하다.
 *
 * 담는 곳은 우리 처방전 사진이 쓰는 `public` 디스크의 `prescriptions/` 아래다. 이름만
 * 같고 웹에서 바로 열리는 자리는 아니다 — 화면은 `PrescriptionController` 를 거쳐
 * 「이 첨부가 이 처방전 것인가」를 본 뒤에 내보낸다.
 */
class FetchWithworksAttachmentsCommand extends Command
{
    protected $signature = 'prescriptions:fetch-ww-attachments
                            {--force : 실제로 받는다. 없으면 세어 보이기만 한다}
                            {--months=3 : 최근 몇 달치}
                            {--disk=public : 어느 디스크에 담을까 (우리 처방전 사진이 쓰는 그 디스크)}
                            {--base=https://www.withworks.co.kr : 저쪽 웹서버}
                            {--limit= : 몇 장만 시험 삼아}
                            {--sleep=0 : 한 장마다 몇 밀리초 쉴까 (저쪽에 짐을 덜 지운다)}
                            {--첫장올리기 : 이미 받아 둔 첨부의 첫 장을 처방전 그림으로 올린다 (한 번만 쓰는 손질)}';

    protected $description = '위드웍스 처방전 첨부파일을 받아 우리 처방전에 붙입니다';

    /** 우리가 받아 담는 갈래 — 그 밖의 것은 담지 않는다 */
    private const 받는꼴 = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                            'png' => 'image/png',  'gif'  => 'image/gif',
                            'pdf' => 'application/pdf'];

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');
        $디스크 = (string) $this->option('disk');
        $바탕 = rtrim((string) $this->option('base'), '/');
        $달수 = max(1, (int) $this->option('months'));
        $쉼 = max(0, (int) $this->option('sleep'));
        $기준 = now()->subMonths($달수)->toDateString();

        if (! Schema::hasColumn('prescription_attachments', 'ww_detail_id')) {
            $this->error('prescription_attachments.ww_detail_id 가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('══ 위드웍스 처방전 첨부파일 받기 '
            . ($정말 ? '(실제로 받습니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line("  저쪽 : {$바탕}/storage/colo/<refile_name>");
        $this->line("  때   : 처방전 reg_date >= {$기준} (최근 {$달수}개월)");
        $this->line("  담을 곳 : {$디스크} 디스크");
        $this->line('');

        if ($정말 && ! $this->디스크되나($디스크)) {
            return self::FAILURE;
        }

        /* 우리가 옮겨 둔 처방전 — 원천 번호로 찾는다 */
        $우리처방전 = DB::table('prescriptions')->whereNotNull('ww_add_id')
            ->pluck('id', 'ww_add_id');

        if ($우리처방전->isEmpty()) {
            $this->error('옮겨 둔 처방전이 없습니다 — prescriptions:migrate-from-ww 를 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $this->line('  옮겨 둔 처방전 ' . number_format($우리처방전->count()) . '장');

        $이미 = DB::table('prescription_attachments')->whereNotNull('ww_detail_id')
            ->pluck('ww_detail_id')->flip();
        $this->line('  이미 받아 둔 첨부파일 ' . number_format($이미->count()) . '장');

        /* 그림 칸이 이미 찬 처방전 — 첫 장을 두 번 담지 않으려면 알고 있어야 한다.
           우리가 담은 것뿐 아니라 사람이 올린 것도 함께 본다.

           **파일 이름까지 쥔다.** 번호만 쥐었다가 한 번 크게 어긋났다(2026-09-29) —
           첫 장을 그림으로 올릴 때 첨부 줄을 지우니 원천 번호 표시가 사라지고, 이어받기가
           그 파일을 다시 받아 첨부로 한 번 더 세웠다(2,042장). 담긴 자리는 원천 이름으로
           끝나므로 그것으로 가린다. */
        $그림이름 = DB::table('prescriptions')->whereNotNull('ww_add_id')
            ->whereNotNull('image_path')->where('image_path', '<>', '')
            ->pluck('image_path', 'id')
            ->map(fn ($자리) => basename((string) $자리))
            ->all();
        $this->line('  처방전 그림이 이미 있는 것 ' . number_format(count($그림이름)) . '장');

        /* 이미 받아 둔 첨부의 첫 장을 처방전 그림으로 올린다 — 한 번만 쓰는 손질 */
        if ($this->option('첫장올리기')) {
            return $this->첫장올리기($정말);
        }

        $창고 = WithworksSource::연결(WithworksSource::창고);

        /* 저쪽 표를 한 번에 다 쥐면 메모리가 버겁다 — 처방전 번호를 나눠 묶음으로 묻는다 */
        $줄들 = collect();

        foreach (array_chunk($우리처방전->keys()->all(), 2000) as $묶음) {
            $줄들 = $줄들->concat(
                $창고->table('account_add_information_details as d')
                    ->join('account_add_informations as p', 'p.id', '=', 'd.add_id')
                    ->whereNull('d.deleted_at')->whereNull('p.deleted_at')
                    ->whereDate('p.reg_date', '>=', $기준)
                    ->whereIn('d.add_id', $묶음)
                    ->whereNotNull('d.refile_name')->where('d.refile_name', '<>', '')
                    ->orderBy('d.id')
                    ->get(['d.id', 'd.add_id', 'd.file_name', 'd.refile_name',
                           'd.created_at', 'p.reg_date'])
            );
        }

        $this->line('  저쪽에 있는 첨부파일 ' . number_format($줄들->count()) . '장');
        $this->line('');

        if ($한도 = $this->option('limit')) {
            $줄들 = $줄들->take((int) $한도);
        }

        /* 받기 전에 자리를 잰다. 열 만 장을 받다가 디스크가 차면 웹서버가 함께 멈춘다 —
           로그를 못 쓰고 세션도 못 써서 화면이 통째로 죽는다. 그래서 넉넉히 1.3배를
           잡고, 모자라면 시작조차 하지 않는다. */
        if ($정말 && ! $this->자리되나($디스크, $줄들->count() - $이미->count())) {
            return self::FAILURE;
        }

        $셈 = ['모두' => 0, '받을것' => 0, '이미있음' => 0, '꼴아님' => 0,
               '받음' => 0, '처방전그림' => 0, '첨부' => 0, '못받음' => 0, '바이트' => 0];
        $못받은것 = [];
        $막대 = $정말 ? $this->output->createProgressBar($줄들->count()) : null;
        $막대?->start();

        foreach ($줄들 as $d) {
            $셈['모두']++;
            $막대?->advance();

            if (isset($이미[$d->id])) {
                $셈['이미있음']++;

                continue;
            }

            /* 이 파일이 이미 처방전 그림으로 담겨 있나 — 첨부 줄이 없어도 담긴 것이다 */
            $처방전번호 = $우리처방전[$d->add_id];

            if (($그림이름[$처방전번호] ?? null) === $d->refile_name) {
                $셈['이미있음']++;

                continue;
            }

            $꼴 = strtolower(pathinfo((string) $d->refile_name, PATHINFO_EXTENSION));

            if (! isset(self::받는꼴[$꼴])) {
                $셈['꼴아님']++;

                continue;
            }

            $셈['받을것']++;

            if (! $정말) {
                continue;
            }

            $주소 = $바탕 . '/storage/colo/' . rawurlencode((string) $d->refile_name);

            try {
                /* 저쪽 웹서버라 느릴 때가 있다 — 넉넉히 기다리고, 한 장 실패로 멈추지 않는다 */
                $답 = Http::timeout(40)->retry(2, 800)->get($주소);

                if (! $답->successful()) {
                    throw new \RuntimeException('HTTP ' . $답->status());
                }

                $몸 = $답->body();

                if ($몸 === '') {
                    throw new \RuntimeException('빈 파일');
                }

                /* 로그인 화면이 200 으로 돌아오는 일이 있다 — 사진이 아니면 담지 않는다 */
                $받은꼴 = strtolower(explode(';', (string) $답->header('Content-Type'))[0]);

                if ($받은꼴 !== '' && ! in_array($받은꼴, array_values(self::받는꼴), true)) {
                    throw new \RuntimeException("사진이 아니다 ({$받은꼴})");
                }

                /* 해마다ㆍ달마다 나눠 담는다 — 한 칸에 만 장이 넘으면 목록을 열 수 없다 */
                $때 = $d->reg_date ?: ($d->created_at ?: now());
                $자리 = 'prescriptions/ww/' . date('Y/m', strtotime((string) $때))
                    . '/' . $d->refile_name;

                Storage::disk($디스크)->put($자리, $몸);

                $처방전 = $처방전번호;
                $이름 = mb_substr((string) ($d->file_name ?: $d->refile_name), 0, 255);

                /* 첫 장은 처방전 그림으로 올린다 — 주문 등록의 「첨부」 목록에서 「처방전」
                   줄이 읽는 칸이 그것이다. 그 칸이 이미 차 있으면(사람이 올린 것이거나
                   앞 판에서 담은 것) 손대지 않고 첨부로 담는다. */
                if (! isset($그림이름[$처방전])) {
                    DB::table('prescriptions')->where('id', $처방전)->update([
                        'image_path'          => $자리,
                        'image_original_name' => $이름,
                        'image_mime_type'     => self::받는꼴[$꼴],
                        'image_size'          => strlen($몸),
                    ]);
                    $그림이름[$처방전] = $d->refile_name;
                    $셈['처방전그림']++;
                } else {
                    DB::table('prescription_attachments')->insert([
                        'ww_detail_id'       => $d->id,
                        'prescription_id'    => $처방전,
                        'file_path'          => $자리,
                        'file_original_name' => $이름,
                        'file_mime_type'     => self::받는꼴[$꼴],
                        'file_size'          => strlen($몸),
                        'doc_type'           => 'prescription',
                        'doc_label'          => '처방전',
                        'display_order'      => 0,
                        'uploaded_by'        => null,
                        'created_at'         => $d->created_at ?: now(),
                        'updated_at'         => $d->created_at ?: now(),
                    ]);
                    $셈['첨부']++;
                }

                $셈['받음']++;
                $셈['바이트'] += strlen($몸);
            } catch (\Throwable $e) {
                $셈['못받음']++;

                if (count($못받은것) < 20) {
                    $못받은것[] = [$d->id, $d->refile_name, mb_substr($e->getMessage(), 0, 50)];
                }
            }

            if ($쉼 > 0) {
                usleep($쉼 * 1000);
            }
        }

        $막대?->finish();
        $this->line('');
        $this->line('');

        $보일것 = collect($셈)->map(fn ($v, $k) => [$k, $k === '바이트'
            ? sprintf('%.2f GB', $v / 1024 / 1024 / 1024)
            : number_format($v)])->values()->all();
        $this->table(['무엇', '몇'], $보일것);

        if ($못받은것 !== []) {
            $this->line('');
            $this->warn('  못 받은 것 (앞 스물) —');
            $this->table(['원천 #', '파일', '왜'], $못받은것);
        }

        $this->line('');

        if (! $정말) {
            $그램 = $셈['받을것'] * 959 / 1024 / 1024;
            $this->line(sprintf('  받으면 대략 %.1f GB 입니다 (표본 평균 959KB 로 셈).', $그램));
            $this->warn('  세어 보이기만 했습니다. 실제로 받으려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  받았습니다. 첨부파일이 지금 '
                . number_format(DB::table('prescription_attachments')->count()) . '장입니다.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 이미 받아 둔 첨부의 첫 장을 처방전 그림으로 올린다 — 한 번만 쓰는 손질.
     *
     * 첫 판에서는 받은 것을 모두 첨부로 담았다(4,902장). 그러면 주문 등록의 「첨부」
     * 목록에서 「처방전」 줄이 `image_path` 를 읽어 「처방전 이미지가 없습니다」로 선다.
     *
     * **파일은 그대로 둔다** — 자리만 옮긴다. 다시 받으면 3.7GB 를 또 내려받아야 하고,
     * 저쪽 웹서버에도 그만큼 짐을 지운다.
     */
    private function 첫장올리기(bool $정말): int
    {
        $this->line('');
        $this->info('── 이미 받아 둔 첨부의 첫 장을 처방전 그림으로 올립니다 '
            . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)'));

        /* 그림 칸이 빈 처방전마다, 원천 번호가 가장 작은 첨부 한 장을 고른다 */
        $첫장들 = DB::table('prescription_attachments as a')
            ->join('prescriptions as p', 'p.id', '=', 'a.prescription_id')
            ->whereNotNull('a.ww_detail_id')
            ->where(fn ($q) => $q->whereNull('p.image_path')->orWhere('p.image_path', ''))
            ->orderBy('a.prescription_id')->orderBy('a.ww_detail_id')
            ->get(['a.id', 'a.prescription_id', 'a.ww_detail_id', 'a.file_path',
                   'a.file_original_name', 'a.file_mime_type', 'a.file_size'])
            ->groupBy('prescription_id')
            ->map(fn ($것들) => $것들->first());

        $this->line('  그림 칸이 빈 처방전 ' . number_format($첫장들->count()) . '장');
        $this->line('  그 가운데 첨부가 있어 올릴 수 있는 것 ' . number_format($첫장들->count()) . '장');
        $this->line('');

        foreach ($첫장들->take(5) as $a) {
            $this->line(sprintf('    처방전#%-7s ← 첨부#%-6s %s', $a->prescription_id, $a->id, $a->file_path));
        }

        if (! $정말) {
            $this->line('');
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');

            return self::SUCCESS;
        }

        $올림 = 0;

        DB::transaction(function () use ($첫장들, &$올림) {
            foreach ($첫장들->chunk(500) as $묶음) {
                foreach ($묶음 as $a) {
                    DB::table('prescriptions')->where('id', $a->prescription_id)->update([
                        'image_path'          => $a->file_path,
                        'image_original_name' => $a->file_original_name,
                        'image_mime_type'     => $a->file_mime_type,
                        'image_size'          => $a->file_size,
                    ]);

                    /* 첨부 줄만 지운다 — 파일은 처방전 그림이 되어 그대로 쓰인다 */
                    DB::table('prescription_attachments')->where('id', $a->id)->delete();
                    $올림++;
                }
            }
        });

        /* 그림으로 올린 파일이 첨부로도 서 있는 줄을 치운다.
           첫 판에서 첨부 줄을 지워 원천 번호 표시가 사라지자, 이어받기가 같은 파일을
           다시 받아 첨부로 한 번 더 세웠다(2,042장). 그 줄만 지운다 — 파일은 그림이
           가리키고 있으니 그대로 둔다. */
        $겹친줄 = DB::table('prescription_attachments as a')
            ->join('prescriptions as p', 'p.id', '=', 'a.prescription_id')
            ->whereNotNull('a.ww_detail_id')
            ->whereColumn('a.file_path', 'p.image_path')
            ->pluck('a.id');

        $치움 = 0;

        if ($겹친줄->isNotEmpty()) {
            foreach ($겹친줄->chunk(1000) as $묶음) {
                $치움 += DB::table('prescription_attachments')->whereIn('id', $묶음->all())->delete();
            }
        }

        $this->line('');
        $this->info('  올렸습니다 ' . number_format($올림) . '장.');
        $this->line('  그림과 같은 파일을 가리켜 겹친 첨부 줄 ' . number_format($치움) . '개를 치웠습니다.');
        $this->line('  처방전 그림이 있는 것 ' . number_format(
            DB::table('prescriptions')->whereNotNull('ww_add_id')
                ->whereNotNull('image_path')->where('image_path', '<>', '')->count()) . '장');
        $this->line('  남은 첨부 ' . number_format(
            DB::table('prescription_attachments')->whereNotNull('ww_detail_id')->count()) . '장');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 담을 자리가 있나.
     *
     * 표본 평균 959KB 로 셈하고 1.3배를 잡는다 — 평균보다 큰 사진이 몰릴 수 있고, 디스크는
     * 가득 차기 전에 여유를 남겨 둬야 한다. 남은 자리에서 **2GB 는 건드리지 않는다**.
     */
    private function 자리되나(string $디스크, int $받을장수): bool
    {
        if ($받을장수 <= 0) {
            return true;
        }

        try {
            $뿌리 = Storage::disk($디스크)->path('');
        } catch (\Throwable $e) {
            /* 고장이 아니다 — S3 처럼 제 자리(path)가 없는 디스크는 잴 것이 없다 */
            return true;
        }

        $남음 = @disk_free_space($뿌리);

        if ($남음 === false) {
            $this->warn('  남은 자리를 재지 못했습니다 — 그대로 갑니다.');

            return true;
        }

        $쓸것 = (int) ($받을장수 * 959 * 1024 * 1.3);
        $여유 = 2 * 1024 * 1024 * 1024;

        $기가 = fn ($b) => sprintf('%.1f GB', $b / 1024 / 1024 / 1024);

        $this->line(sprintf('  받을 것 %s장 · 필요한 자리 약 %s · 남은 자리 %s',
            number_format($받을장수), $기가($쓸것), $기가($남음)));

        if ($쓸것 + $여유 > $남음) {
            $this->error(sprintf('  자리가 모자랍니다 — 약 %s 가 필요하고 %s 만 남았습니다(%s 는 남겨 둡니다).',
                $기가($쓸것), $기가($남음), $기가($여유)));
            $this->line('  --months 를 줄이거나 디스크를 늘려 주십시오.');

            return false;
        }

        return true;
    }

    /**
     * 담을 디스크가 정말 쓸 수 있나.
     *
     * S3 는 값이 비어 있어도 조용히 만들어진다 — 쓰는 순간에야 죽는다. 열 만 장을 받다가
     * 중간에 죽으면 어디까지 받았는지 헷갈리므로, 시작 전에 한 장 써 보고 지운다.
     */
    private function 디스크되나(string $디스크): bool
    {
        if ($디스크 === 's3') {
            foreach (['key' => 'AWS_ACCESS_KEY_ID', 'secret' => 'AWS_SECRET_ACCESS_KEY',
                      'region' => 'AWS_DEFAULT_REGION', 'bucket' => 'AWS_BUCKET'] as $칸 => $이름) {
                if (! config("filesystems.disks.s3.{$칸}")) {
                    $this->error("  S3 설정이 비어 있습니다 — .env 의 {$이름} 를 채워 주십시오.");

                    return false;
                }
            }

            if (! class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
                $this->error('  S3 어댑터가 없습니다 — composer require league/flysystem-aws-s3-v3');

                return false;
            }
        }

        $시험 = 'prescriptions/ww/.write-test-' . now()->format('YmdHis');

        try {
            Storage::disk($디스크)->put($시험, 'ok');
            $읽음 = Storage::disk($디스크)->get($시험);
            Storage::disk($디스크)->delete($시험);

            if ($읽음 !== 'ok') {
                throw new \RuntimeException('쓴 것과 읽은 것이 다릅니다.');
            }
        } catch (\Throwable $e) {
            $this->error("  {$디스크} 디스크에 쓸 수 없습니다 — " . mb_substr($e->getMessage(), 0, 120));

            return false;
        }

        $this->line("  {$디스크} 디스크에 한 장 써 보고 지웠습니다 — 쓸 수 있습니다.");

        return true;
    }
}
