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
 * ## 이어받기
 *
 * 한 장씩 남의 웹서버에서 받으므로 중간에 끊긴다. `ww_detail_id` 로 이미 받은 것을 가려
 * 건너뛴다 — 다시 돌리면 못 받은 것만 받는다.
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
                            {--sleep=0 : 한 장마다 몇 밀리초 쉴까 (저쪽에 짐을 덜 지운다)}';

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
               '받음' => 0, '못받음' => 0, '바이트' => 0];
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

                DB::table('prescription_attachments')->insert([
                    'ww_detail_id'       => $d->id,
                    'prescription_id'    => $우리처방전[$d->add_id],
                    'file_path'          => $자리,
                    'file_original_name' => mb_substr((string) ($d->file_name ?: $d->refile_name), 0, 255),
                    'file_mime_type'     => self::받는꼴[$꼴],
                    'file_size'          => strlen($몸),
                    'doc_type'           => 'prescription',
                    'doc_label'          => '처방전',
                    'display_order'      => 0,
                    'uploaded_by'        => null,
                    'created_at'         => $d->created_at ?: now(),
                    'updated_at'         => $d->created_at ?: now(),
                ]);

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
