<?php

namespace App\Console\Commands;

use App\Models\BillingOffice;
use App\Models\BillingOfficeArea;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 지자체 청구처를 표 하나로 채운다.
 *
 * 기초(의료급여) 요양비는 시ㆍ군ㆍ구청에 등기로 낸다. 그 관할이 이백스물여섯 곳인데,
 * 화면에서 하나씩 등록하면 이백스물여섯 번을 눌러야 하고 그 사이 몇을 빠뜨린다.
 * 엑셀로 한 번 채워 여기에 넣는다.
 *
 *     php artisan billing-offices:seed-local database/data/지자체청구처_씨앗.csv
 *     php artisan billing-offices:seed-local ... --dry     (넣지 않고 세어만 본다)
 *
 * 첫 줄은 머리글이다. 칸 이름은 아래 그대로 쓴다(차례는 상관없다).
 *
 *     시도,시군구,기관명,부서,전화,팩스,주소,비고
 *     서울특별시,중구,서울특별시 중구청,복지정책과 의료급여팀,02-3396-4114,,[우.04558] 서울특별시 중구 창경궁로 17,
 *
 * **관할은 시군구 전체다** — 읍ㆍ면ㆍ동을 적지 않는다. 지자체는 시ㆍ군ㆍ구청 하나가
 * 그 안을 통째로 맡으므로 동까지 갈 것이 없다(billing_office_areas.emd 를 비운다).
 *
 * 같은 시군구가 이미 있으면 새로 만들지 않고 **빈 칸만 채운다** — 담당자가 화면에서
 * 손봐 둔 것을 표가 덮어쓰면 안 된다. 덮어쓰려면 --force 를 준다.
 */
class SeedLocalBillingOffices extends Command
{
    protected $signature = 'billing-offices:seed-local
                            {file : 시도ㆍ시군구ㆍ기관명이 든 CSV}
                            {--dry : 넣지 않고 무엇이 될지만 보여 준다}
                            {--force : 이미 있는 줄의 값도 표의 값으로 덮는다}';

    protected $description = '지자체(시군구청) 청구처를 CSV 한 벌로 채운다';

    /** 머리글 이름 → 우리 칸 */
    private const 칸 = [
        '시도' => 'sido', '시군구' => 'sigungu', '기관명' => 'office_name',
        '부서' => 'dept', '전화' => 'tel', '팩스' => 'fax',
        '주소' => 'address', '비고' => 'note',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("파일이 없습니다: {$path}");

            return self::FAILURE;
        }

        $rows = $this->읽는다($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        $dry   = (bool) $this->option('dry');
        $force = (bool) $this->option('force');
        $셈    = ['새로' => 0, '채움' => 0, '그대로' => 0, '건너뜀' => 0];

        foreach ($rows as $줄 => $r) {
            $sigungu = trim((string) ($r['sigungu'] ?? ''));
            $name    = trim((string) ($r['office_name'] ?? ''));

            if ($sigungu === '' || $name === '') {
                $this->warn("  {$줄}줄: 시군구나 기관명이 비어 건너뜁니다");
                $셈['건너뜀']++;
                continue;
            }

            $office = $this->찾는다($r);

            if (!$office) {
                $셈['새로']++;
                $this->line("  <fg=green>새로</> {$name}" . ($r['dept'] ? ' · ' . $r['dept'] : ''));
                if (!$dry) {
                    $this->세운다($r);
                }
                continue;
            }

            $채울것 = $this->채울것($office, $r, $force);
            if (!$채울것) {
                $셈['그대로']++;
                continue;
            }

            $셈['채움']++;
            $this->line("  <fg=yellow>채움</> {$office->office_name} — " . implode(', ', array_keys($채울것)));
            if (!$dry) {
                $office->update($채울것);
                $this->관할을세운다($office, $r);
            }
        }

        $this->newLine();
        $this->info(($dry ? '[세어만 봄] ' : '') . sprintf(
            '새로 %d · 채움 %d · 그대로 %d · 건너뜀 %d',
            $셈['새로'], $셈['채움'], $셈['그대로'], $셈['건너뜀']
        ));

        if ($dry) {
            $this->comment('--dry 라 아무것도 넣지 않았습니다.');
        }

        return self::SUCCESS;
    }

    /** CSV 를 읽어 우리 칸 이름으로 바꾼다 */
    private function 읽는다(string $path): ?array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            $this->error('파일을 열지 못했습니다.');

            return null;
        }

        $머리 = fgetcsv($fh);
        if (!$머리) {
            $this->error('첫 줄(머리글)이 없습니다.');
            fclose($fh);

            return null;
        }

        // 엑셀이 붙이는 BOM 을 떼지 않으면 첫 칸 이름이 안 맞는다
        $머리[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $머리[0]);
        $짝 = [];
        foreach ($머리 as $i => $h) {
            $h = trim((string) $h);
            if (isset(self::칸[$h])) {
                $짝[$i] = self::칸[$h];
            }
        }

        if (!in_array('sigungu', $짝, true) || !in_array('office_name', $짝, true)) {
            $this->error('머리글에 「시군구」와 「기관명」이 있어야 합니다. 본 것: ' . implode(', ', $머리));
            fclose($fh);

            return null;
        }

        $rows = [];
        $줄   = 1;
        while (($line = fgetcsv($fh)) !== false) {
            $줄++;
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;   // 빈 줄
            }
            $r = [];
            foreach ($짝 as $i => $key) {
                $r[$key] = trim((string) ($line[$i] ?? ''));
            }
            $rows[$줄] = $r;
        }
        fclose($fh);

        $this->info(count($rows) . '줄을 읽었습니다.');

        return $rows;
    }

    /**
     * 이미 있는가 — 같은 **시도의** 같은 시군구를 맡는 지자체 줄.
     *
     * 시도를 느슨하게 보면 안 된다. 「중구」는 서울ㆍ부산ㆍ대구ㆍ인천ㆍ울산에 다 있고
     * 「동구」ㆍ「서구」ㆍ「남구」ㆍ「북구」도 그렇다. 시도가 비어 있는 헌 줄에 아무
     * 시도의 「중구」나 걸리면, 부산 사람 서류가 대구로 간다.
     *
     * 그래서 시도까지 꼭 맞아야 같은 곳으로 본다. 헌 줄에 시도가 비어 있으면 그것이
     * 어느 시도인지 우리가 알 수 없으므로 겹치는 것으로 보지 않는다 — 새로 세우고,
     * 헌 줄은 담당자가 보고 정리한다.
     */
    private function 찾는다(array $r): ?BillingOffice
    {
        if (($r['sido'] ?? '') === '') {
            return null;    // 시도를 모르면 무엇과도 짝지을 수 없다
        }

        return BillingOffice::where('kind', BillingOffice::KIND_LOCAL)
            ->whereHas('areas', fn ($a) => $a->where('sigungu', $r['sigungu'])->where('sido', $r['sido']))
            ->first();
    }

    private function 세운다(array $r): void
    {
        DB::transaction(function () use ($r) {
            $office = BillingOffice::create([
                'kind'        => BillingOffice::KIND_LOCAL,
                'region'      => $r['sido'] ?: null,
                'office_name' => $r['office_name'],
                'dept'        => $r['dept'] ?: null,
                'tel'         => $r['tel'] ?: null,
                'fax'         => $r['fax'] ?: null,
                'address'     => $r['address'] ?: null,
                'note'        => $r['note'] ?: null,
                'is_active'   => true,
            ]);

            $this->관할을세운다($office, $r);
        });
    }

    /** 관할은 시군구 하나 — 읍ㆍ면ㆍ동을 비운다(= 그 시군구 전체) */
    private function 관할을세운다(BillingOffice $office, array $r): void
    {
        BillingOfficeArea::updateOrCreate(
            [
                'billing_office_id' => $office->id,
                'sido'              => $r['sido'] ?: null,
                'sigungu'           => $r['sigungu'],
                'emd'               => null,
            ],
            []
        );
    }

    /**
     * 무엇을 채울 것인가.
     *
     * 담당자가 화면에서 손봐 둔 값을 표가 덮으면 안 된다 — 표는 대개 한 번 만들고
     * 오래 두는 것이라 화면 쪽이 더 새롭다. 그래서 **빈 칸만** 채운다.
     */
    private function 채울것(BillingOffice $office, array $r, bool $force): array
    {
        $둘 = [
            'office_name' => $r['office_name'],
            'dept'        => $r['dept'],
            'tel'         => $r['tel'],
            'fax'         => $r['fax'],
            'address'     => $r['address'],
            'note'        => $r['note'],
            'region'      => $r['sido'],
        ];

        $채울것 = [];
        foreach ($둘 as $칸 => $새값) {
            if ($새값 === '' || $새값 === null) {
                continue;
            }
            $헌값 = trim((string) $office->{$칸});
            if ($헌값 === '' || ($force && $헌값 !== $새값)) {
                $채울것[$칸] = $새값;
            }
        }

        return $채울것;
    }
}
