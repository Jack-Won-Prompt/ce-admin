<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 운영 데이터의 처방전 정보에서 병원 마스터를 채운다 (2026-09-30 지시).
 *
 * 병원 마스터(`hospitals`)가 **0건**이라, 주문 등록에서 병원을 고르려 하면 늘
 * 「검색된 병원이 없습니다」였다. 담당자마다 같은 병원을 새로 등록하게 되어,
 * 곧 「서울아산병원」이 여러 줄로 쌓이고 요양기관번호도 제각각이 된다.
 *
 * 원천은 `ww_prescription_infos` 다 — **읽기만 한다.** 운영 데이터 표는 고치지도
 * 지우지도 않는다.
 *
 * **병원명과 요양기관번호가 둘 다 있는 줄만 옮긴다** (2026-09-30 지시). 번호 없이
 * 이름만 담으면 골라도 요양기관번호 칸이 비어, 공단 서류를 다시 손으로 채워야 한다.
 *
 *   udf33  「주소지-병원명」 꼴이다 (「서울종로대학로-서울대학교병원」). 뒤쪽이 이름이다.
 *   udf39  요양기관번호
 *
 * ## 짐작하지 않는다
 *
 * 원천이 깨끗하지 않다(10만 줄 기준).
 *
 *   서로 다른 병원명    328
 *   서로 다른 요양번호  268
 *   한 이름에 번호 여럿  15   서울아산병원 = 11100800 · 11100915
 *   한 번호에 이름 여럿  26   11100435 = 건국대학교병원 · 가톨릭대학교은평성모병원
 *
 * 뒤엣것은 **원천이 틀린 것**이다 — 요양기관번호는 기관마다 하나다. 어느 쪽이 맞는지
 * 우리가 고를 근거가 없으므로, 가장 많이 쓰인 번호를 담되 **다툼이 있으면 비고에
 * 그대로 적어 둔다.** 사람이 보고 고치는 것이 맞다.
 *
 * ## 다시 돌려도 겹치지 않는다
 *
 * 이미 있는 이름은 건드리지 않는다. 사람이 번호를 고쳐 두었을 수 있는데, 다시 돌릴
 * 때마다 원천 값으로 되돌리면 고친 일이 사라진다.
 */
class ImportHospitalsFromWithworksCommand extends Command
{
    protected $signature = 'hospitals:import-from-ww
                            {--dry : 담지 않고 무엇이 담길지만 보여 준다}
                            {--min=1 : 이 횟수보다 적게 쓰인 병원명은 건너뛴다}';

    protected $description = '운영 데이터(처방전 정보)의 병원명ㆍ요양기관번호를 병원 마스터로 옮긴다';

    public function handle(): int
    {
        $헛돌리기 = (bool) $this->option('dry');
        $최소     = max(1, (int) $this->option('min'));

        if (! \Illuminate\Support\Facades\Schema::hasTable('ww_prescription_infos')) {
            $this->error('운영 데이터(ww_prescription_infos)가 없습니다.');

            return self::FAILURE;
        }

        $this->info('운영 데이터를 읽습니다 — 고치지 않습니다.');

        [$이름별, $번호별] = $this->읽기();

        if ($이름별 === []) {
            $this->warn('옮길 병원이 없습니다.');

            return self::SUCCESS;
        }

        $이미있는것 = Hospital::pluck('id', 'name');

        $담을것 = [];
        $건너뛴것 = 0;
        $다툼 = 0;

        foreach ($이름별 as $이름 => $번호들) {
            $쓰임 = array_sum($번호들);

            if ($쓰임 < $최소) {
                $건너뛴것++;
                continue;
            }

            if ($이미있는것->has($이름)) {
                $건너뛴것++;
                continue;
            }

            /* 가장 많이 쓰인 번호를 담는다. 읽을 때 번호 없는 줄을 이미 걸렀으므로
               여기 오는 것은 모두 번호가 있다. */
            $쓸만한번호 = $번호들;
            arsort($쓸만한번호);

            $번호 = (string) array_key_first($쓸만한번호);
            $비고 = $this->비고($이름, $쓸만한번호, $번호, $번호별);

            if ($비고 !== null) {
                $다툼++;
            }

            $담을것[] = [
                'name'       => $이름,
                'code'       => $번호,
                'memo'       => $비고,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->line('');
        $this->line('  원천 병원명      : ' . count($이름별));
        $this->line('  담을 것          : ' . count($담을것));
        $this->line('  건너뛴 것        : ' . $건너뛴것 . ' (이미 있거나 쓰임이 적음)');
        $this->line('  비고를 남긴 것   : ' . $다툼 . ' (번호가 다투는 줄 — 사람이 봐야 합니다)');
        $this->line('');

        if ($헛돌리기) {
            foreach (array_slice($담을것, 0, 15) as $줄) {
                $this->line(sprintf('  %-34s %-10s %s',
                    mb_strimwidth($줄['name'], 0, 34, ''), $줄['code'] ?? '-', $줄['memo'] ?? ''));
            }
            $this->comment('헛돌리기입니다 — 담지 않았습니다.');

            return self::SUCCESS;
        }

        foreach (array_chunk($담을것, 200) as $묶음) {
            Hospital::insert($묶음);
        }

        $this->info('병원 마스터 ' . count($담을것) . '건을 담았습니다. (전체 ' . Hospital::count() . '건)');

        return self::SUCCESS;
    }

    /**
     * 원천을 훑어 이름별ㆍ번호별 쓰임을 센다.
     *
     * @return array{0: array<string, array<string,int>>, 1: array<string, array<string,int>>}
     */
    private function 읽기(): array
    {
        $이름별 = [];
        $번호별 = [];

        DB::table('ww_prescription_infos')
            ->select('udf33', 'udf39')
            ->orderBy('id')
            ->chunk(5000, function ($줄들) use (&$이름별, &$번호별) {
                foreach ($줄들 as $줄) {
                    $이름 = $this->병원이름($줄->udf33);
                    $번호 = preg_replace('/\s+/u', '', trim((string) $줄->udf39));

                    /* **둘 다 있는 줄만 센다** (2026-09-30 지시).

                       이름만 있고 번호가 없는 줄이 적지 않다. 그런 줄까지 담으면
                       마스터에 번호 없는 병원이 서고, 골라도 요양기관번호 칸이 비어
                       공단 서류를 다시 손으로 채워야 한다 — 마스터를 두는 뜻이 없다. */
                    if ($이름 === null || $번호 === '') {
                        continue;
                    }

                    $이름별[$이름][$번호] = ($이름별[$이름][$번호] ?? 0) + 1;
                    $번호별[$번호][$이름] = ($번호별[$번호][$이름] ?? 0) + 1;
                }
            });

        ksort($이름별);

        return [$이름별, $번호별];
    }

    /**
     * udf33 은 「주소지-병원명」 꼴이다 — 뒤쪽만 이름이다.
     *
     * 이관 명령(MigratePrescriptionsFromWithworksCommand)이 처방전에 적을 때 쓰는 잣대와
     * **같아야 한다.** 다르면 처방전에 적힌 이름과 마스터의 이름이 어긋나 골라도
     * 안 맞는다.
     */
    private function 병원이름(mixed $값): ?string
    {
        $v = trim((string) $값);

        if ($v === '') {
            return null;
        }

        if (($자리 = mb_strrpos($v, '-')) !== false) {
            $뒤 = trim(mb_substr($v, $자리 + 1));

            if ($뒤 !== '') {
                $v = $뒤;
            }
        }

        return mb_substr($v, 0, 255);
    }

    /**
     * 사람이 봐야 하는 줄에만 비고를 남긴다.
     *
     * 둘을 적는다 — 이 병원에 번호가 여럿인 것, 그리고 고른 번호를 **다른 병원이
     * 함께 쓰는 것**. 뒤엣것이 더 위험하다. 요양기관번호는 기관마다 하나여서,
     * 겹친다는 것은 원천 어딘가가 틀렸다는 뜻이다.
     */
    private function 비고(string $이름, array $쓸만한번호, string $고른번호, array $번호별): ?string
    {
        $말 = [];

        if (count($쓸만한번호) > 1) {
            $줄 = [];

            foreach ($쓸만한번호 as $번호 => $수) {
                $줄[] = $번호 . '(' . $수 . '건)';
            }

            $말[] = '원천에 요양기관번호가 여럿입니다 — ' . implode(' · ', $줄)
                  . '. 가장 많이 쓰인 것을 담았습니다.';
        }

        $같은번호쓰는곳 = array_diff(array_keys($번호별[$고른번호] ?? []), [$이름]);

        if ($고른번호 !== '' && $같은번호쓰는곳 !== []) {
            $말[] = '요양기관번호 ' . $고른번호 . ' 를 다른 병원명도 씁니다 — '
                  . implode(' · ', array_slice($같은번호쓰는곳, 0, 4))
                  . '. 어느 쪽이 맞는지 확인이 필요합니다.';
        }

        return $말 === [] ? null : '[운영 데이터 이관] ' . implode(' / ', $말);
    }
}
