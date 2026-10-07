<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 IC 쪽 병원명을 병원 마스터에 맞춘다 (2026-10-07 지시 · SR #75).
 *
 * ## 왜 또 옮기나
 *
 * 먼저 돌린 `hospitals:import-from-ww` 는 `udf33` 을 썼다. 그 칸은 「주소지-정식명」
 * 꼴이라 뒤쪽을 떼어 담았는데, 담당자가 보는 이름은 그것이 아니었다.
 *
 *   udf33  [서울종로새문안-삼성의료재단강북삼성병원]
 *   udf34  [강북삼성병원]                              ← 위드웍스 IC 쪽 이름
 *
 * 그래서 「기존 병원명으로 매칭해서 보던 자료가 매칭되지 않는다」는 말이 나왔다.
 * 조회가 안 되는 병원이 생겨 같은 곳을 새로 등록하는 일도 있었다.
 *
 * ## 세 가지를 따로 한다
 *
 *   --담기   우리에 없는 이름을 담는다 (기존 줄은 건드리지 않는다)
 *   --개명   코드로 짝이 맞고 **또렷한 병원명일 때만** 이름을 바꾼다
 *   --번호   이름이 같은데 번호가 다른 줄의 번호를 원본 값으로 맞춘다
 *
 * 아무 깃발도 주지 않으면 **무엇을 할지 보여 주기만 한다.**
 *
 * ## 일괄 개명을 하지 않는 까닭
 *
 * 코드로 짝을 맞춰 전부 바꾸면 109건이 바뀌는데 그 가운데 오염이 섞인다 —
 * 원본이 한 번호에 두 이름을 적어 둔 것이 11개이고(11100435 = 건국대학교병원 ·
 * 가톨릭대학교은평성모병원), 원본 이름 자체가 메모인 것도 있다
 * (「공단재등록대상자필요X」, 「진주산청지사 2019-10-24일자로 재등록(10/28)」).
 * 우리 표에도 메모가 병원명으로 담긴 줄이 있다(코드 38100509 에 네 줄).
 *
 * 그래서 **또렷한 것만** 바꾼다. 거르는 잣대는 아래 또렷한가() 한 곳에 있다.
 * 걸러진 것은 사람이 화면에서 고친다 — 「그 이후 정리하겠습니다」가 SR 의 말이다.
 *
 * ## 원본은 읽기만 한다
 *
 * `ww_prescription_infos` 는 운영 데이터다. 고치지도 지우지도 않는다.
 */
class ImportHospitalsIcNamesCommand extends Command
{
    protected $signature = 'hospitals:import-ic
                            {--담기 : 우리에 없는 이름을 담는다}
                            {--개명 : 또렷한 것만 이름을 바꾼다}
                            {--번호 : 이름이 같은데 번호가 다른 줄의 번호를 맞춘다}
                            {--메모도 : 병원말이 없는 이름(메모)도 담는다}';

    protected $description = '위드웍스 IC 쪽 병원명(udf34)을 병원 마스터에 맞춘다';

    /**
     * 병원 이름으로 읽히는 말 — 하나라도 들어 있어야 병원명으로 본다.
     *
     * 「의학원」을 더했다 (2026-10-07 운영 미리 보기) — 「한국원자력의학원」이 실제 병원인데
     * 이 목록에 없어 메모로 걸러질 참이었다.
     */
    private const 병원말 = [
        '병원', '의원', '의료원', '의학원', '센터', '클리닉', '치과', '한의원', '보건소',
        '비뇨기과', '외과', '내과', '산부인과', '소아과', '요양원',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('ww_prescription_infos')) {
            $this->error('운영 데이터(ww_prescription_infos)가 없습니다.');

            return self::FAILURE;
        }

        [$이름별, $번호별] = $this->읽기();

        if ($이름별 === []) {
            $this->warn('원본에서 읽을 병원명이 없습니다.');

            return self::SUCCESS;
        }

        $this->info(sprintf('원본 udf34 — 병원명 %d개 · 번호 %d개',
            count($이름별), count($번호별)));

        $우리것   = Hospital::orderBy('id')->get();
        $이름색인 = $우리것->mapWithKeys(fn ($h) => [trim((string) $h->name) => $h->id]);

        ['담을것' => $담을것, '메모' => $메모] = $this->담을것($이름별, $이름색인);
        $개명할것 = $this->개명할것($우리것, $번호별, $이름색인);
        $번호고칠것 = $this->번호고칠것($우리것, $이름별);

        $this->newLine();
        $this->table(['할 일', '몇'], [
            ['담기 — 우리에 없는 이름', count($담을것)],
            ['  그중 원본에 번호가 없는 것', count(array_filter($담을것, fn ($r) => $r['code'] === null))],
            ['  메모로 보아 담지 않는 것', count($메모)],
            ['개명 — 또렷한 것만', count($개명할것['할것'])],
            ['  걸러진 것', count($개명할것['걸러진것'])],
            ['번호 — 이름 같고 번호 다름', count($번호고칠것)],
        ]);

        $한일 = false;

        if ($this->option('담기')) {
            $this->담기($담을것);
            $한일 = true;
        }

        if ($this->option('개명')) {
            $this->개명($개명할것['할것']);
            $한일 = true;
        }

        if ($this->option('번호')) {
            $this->번호맞추기($번호고칠것);
            $한일 = true;
        }

        if (! $한일) {
            $this->보여주기($담을것, $메모, $개명할것, $번호고칠것);
            $this->newLine();
            $this->comment('무엇도 담거나 고치지 않았습니다 — --담기 / --개명 / --번호 를 주십시오.');
        }

        return self::SUCCESS;
    }

    /**
     * 원본에서 이름 × 번호를 센다.
     *
     * @return array{0: array<string, array<string, int>>, 1: array<string, array<string, int>>}
     */
    private function 읽기(): array
    {
        $이름별 = [];
        $번호별 = [];

        DB::table('ww_prescription_infos')
            ->select('udf34 as 이름', 'udf39 as 번호', DB::raw('count(*) as n'))
            ->whereNotNull('udf34')->where('udf34', '!=', '')
            ->groupBy('udf34', 'udf39')
            ->orderBy('udf34')
            ->each(function ($줄) use (&$이름별, &$번호별) {
                $이름 = trim((string) $줄->이름);
                $번호 = trim((string) $줄->번호);

                if ($이름 === '') {
                    return;
                }

                $이름별[$이름][$번호] = ($이름별[$이름][$번호] ?? 0) + (int) $줄->n;

                if ($번호 !== '') {
                    $번호별[$번호][$이름] = ($번호별[$번호][$이름] ?? 0) + (int) $줄->n;
                }
            });

        foreach ($이름별 as &$번호들) {
            arsort($번호들);
        }
        unset($번호들);

        foreach ($번호별 as &$이름들) {
            arsort($이름들);
        }
        unset($이름들);

        return [$이름별, $번호별];
    }

    /**
     * 우리에 없는 이름 — 번호가 비어 있어도 담는다(2026-10-07 지시).
     *
     * **메모는 담지 않는다** (같은 날 지시 · 미리 보기에서 드러남). 원본의 병원명 자리에
     * 메모가 든 줄이 있다 — 「공단재등록대상자필요X」ㆍ「2017년8월 신환」ㆍ「G834」ㆍ
     * 「재등록필요없으신분」. 그런 것이 병원 목록에 서면 조회에 걸려 담당자가 헤맨다.
     *
     * 가리는 잣대는 **병원말이 하나라도 있는가** 하나다. 「주소지-병원명」 꼴은 담는다 —
     * 위드웍스가 그렇게 적어 둔 이름이고, 그 안에 병원말이 있다(「제주제주연신로-한마음병원」).
     * 「진주산청지사 2019-10-24일자로 재등록(10/28)」처럼 병원말이 없는 것만 걸린다.
     *
     * `--메모도` 를 주면 거르지 않는다 — 「그대로 옮기라」고 할 때를 위해 남긴다.
     *
     * @return array{담을것: array<int, array>, 메모: array<int, array>}
     */
    private function 담을것(array $이름별, \Illuminate\Support\Collection $이름색인): array
    {
        $담을것 = [];
        $메모   = [];

        foreach ($이름별 as $이름 => $번호들) {
            if ($이름색인->has($이름)) {
                continue;
            }

            $많이쓴번호 = (string) array_key_first($번호들);

            $줄 = [
                'name' => $이름,
                'code' => $많이쓴번호 !== '' ? $많이쓴번호 : null,
                'used' => array_sum($번호들),
            ];

            if (! $this->병원말있나($이름) && ! $this->option('메모도')) {
                $메모[] = $줄;
                continue;
            }

            $담을것[] = $줄;
        }

        return ['담을것' => $담을것, '메모' => $메모];
    }

    /** 병원말이 하나라도 있는가 */
    private function 병원말있나(string $이름): bool
    {
        foreach (self::병원말 as $말) {
            if (mb_strpos($이름, $말) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 코드로 짝이 맞고 또렷한 이름일 때만 바꾼다.
     *
     * @return array{할것: array<int, array>, 걸러진것: array<int, array>}
     */
    private function 개명할것(
        \Illuminate\Support\Collection $우리것,
        array $번호별,
        \Illuminate\Support\Collection $이름색인,
    ): array {
        $할것 = [];
        $걸러진것 = [];

        /* **같은 새 이름으로 가는 짝은 거른다** (2026-10-07 운영 미리 보기에서 드러남).

           한 번호를 두 줄이 쓰고 있으면 둘 다 그 번호의 대표 이름으로 바뀌어, 이름이 같은
           줄이 둘로 남는다 — 정리하려던 중복을 이름만 바꿔 다시 만드는 셈이다.
           실제로 11100915 가 그랬다(「이대목동병원」ㆍ「이화여대부속목동병원」).
           그 둘은 개명이 아니라 합치기로 모아야 한다. */
        $갈곳 = [];
        foreach ($우리것 as $h) {
            $코드 = trim((string) $h->code);
            if ($코드 !== '' && isset($번호별[$코드])) {
                $갈곳[(string) array_key_first($번호별[$코드])][] = $h->id;
            }
        }

        foreach ($우리것 as $h) {
            $코드 = trim((string) $h->code);
            $지금이름 = trim((string) $h->name);

            if ($코드 === '' || ! isset($번호별[$코드])) {
                continue;
            }

            $후보들 = $번호별[$코드];
            $새이름 = (string) array_key_first($후보들);

            if ($새이름 === $지금이름) {
                continue;
            }

            $까닭 = null;

            /* 원본이 한 번호에 여러 이름을 적어 둔 것은 믿을 수 없다 — 어느 쪽이 맞는지
               우리가 고를 근거가 없다(요양기관번호는 기관마다 하나다). */
            if (count($후보들) > 1) {
                $까닭 = '원본이 이 번호에 이름을 ' . count($후보들) . '개 적어 두었다';
            } elseif (count($갈곳[$새이름] ?? []) > 1) {
                $까닭 = '우리 쪽 ' . count($갈곳[$새이름]) . '줄이 같은 이름으로 가려 한다 — 합치기로 모아야 한다';
            } elseif ($이름색인->has($새이름) && (int) $이름색인[$새이름] !== (int) $h->id) {
                $까닭 = '그 이름의 줄이 이미 있다(#' . $이름색인[$새이름] . ') — 합치기로 모아야 한다';
            } elseif (! $this->또렷한가($새이름)) {
                $까닭 = '원본 이름이 병원명으로 읽히지 않는다';
            }

            $줄 = ['id' => $h->id, 'code' => $코드, 'from' => $지금이름, 'to' => $새이름, 'why' => $까닭];

            if ($까닭 === null) {
                $할것[] = $줄;
            } else {
                $걸러진것[] = $줄;
            }
        }

        return ['할것' => $할것, '걸러진것' => $걸러진것];
    }

    /**
     * 병원명으로 읽히는 이름인가.
     *
     * 원본에는 메모ㆍ주소ㆍ날짜가 병원명 자리에 들어온 줄이 있다. 그런 것을 그대로
     * 덮으면 멀쩡한 이름이 메모로 바뀐다 — 자동으로 바꾸는 자리에서는 거른다.
     *
     *   「공단재등록대상자필요X」                          병원말이 없다
     *   「진주산청지사 2019-10-24일자로 재등록(10/28)」    연도가 들어 있다
     *   「인천중구우물로-가천대부속동인천길병원」          주소지가 앞에 붙었다
     */
    private function 또렷한가(string $이름): bool
    {
        $길이 = mb_strlen($이름);

        if ($길이 < 2 || $길이 > 60) {
            return false;
        }

        /* 주소지가 앞에 붙은 꼴ㆍ지점 이름이 뒤에 붙은 꼴 — 사람이 보고 정할 일이다 */
        if (mb_strpos($이름, '-') !== false) {
            return false;
        }

        /* 날짜ㆍ연도가 든 것은 메모다 */
        if (preg_match('/(19|20)\d{2}/', $이름)) {
            return false;
        }

        return $this->병원말있나($이름);
    }

    /** 이름은 같은데 번호가 다른 줄 — 원본에 번호가 또렷할 때만 */
    private function 번호고칠것(\Illuminate\Support\Collection $우리것, array $이름별): array
    {
        $고칠것 = [];

        foreach ($우리것 as $h) {
            $이름 = trim((string) $h->name);

            if (! isset($이름별[$이름])) {
                continue;
            }

            $번호들 = $이름별[$이름];
            $새번호 = (string) array_key_first($번호들);

            /* 원본이 비어 있으면 우리 값을 그대로 둔다 — 비우는 것은 고치는 것이 아니다.
               한 이름에 번호가 여럿이면 어느 쪽인지 알 수 없어 사람에게 남긴다. */
            if ($새번호 === '' || count(array_filter(array_keys($번호들), fn ($k) => $k !== '')) > 1) {
                continue;
            }

            if (trim((string) $h->code) === $새번호) {
                continue;
            }

            $고칠것[] = ['id' => $h->id, 'name' => $이름, 'from' => (string) $h->code, 'to' => $새번호];
        }

        return $고칠것;
    }

    private function 담기(array $담을것): void
    {
        $담은것 = 0;

        foreach ($담을것 as $줄) {
            /* 번호가 겹치면 담지 않는다 — 번호 하나는 기관 하나다. 겹친 채로 담으면
               청구가 남의 병원으로 갈 수 있다. */
            if ($줄['code'] !== null && Hospital::where('code', $줄['code'])->exists()) {
                $this->warn(sprintf('건너뜀 — [%s] 번호 %s 를 쓰는 병원이 이미 있습니다',
                    $줄['name'], $줄['code']));
                continue;
            }

            $h = Hospital::create([
                'name'      => $줄['name'],
                'code'      => $줄['code'],
                'memo'      => $줄['code'] === null
                                ? '위드웍스에서 옮겼습니다 — 원본에 요양기관번호가 없습니다'
                                : '위드웍스에서 옮겼습니다',
                'is_active' => true,
            ]);

            activity()->performedOn($h)->log(sprintf(
                '위드웍스 병원 목록에서 담았습니다 — %s%s (원본 %s건)',
                $h->name, $h->code ? ' (' . $h->code . ')' : ' · 번호 없음', number_format($줄['used'])));

            $담은것++;
        }

        $this->info(sprintf('담기 — %d곳', $담은것));
    }

    private function 개명(array $할것): void
    {
        foreach ($할것 as $줄) {
            $h = Hospital::find($줄['id']);

            if (! $h) {
                continue;
            }

            $h->forceFill(['name' => $줄['to']])->save();

            activity()->performedOn($h)->log(sprintf(
                '위드웍스 병원명으로 맞췄습니다 — [%s] → [%s] (요양기관번호 %s)',
                $줄['from'], $줄['to'], $줄['code']));
        }

        $this->info(sprintf('개명 — %d곳', count($할것)));
    }

    private function 번호맞추기(array $고칠것): void
    {
        $고친것 = 0;

        foreach ($고칠것 as $줄) {
            $h = Hospital::find($줄['id']);

            if (! $h) {
                continue;
            }

            if (Hospital::where('code', $줄['to'])->where('id', '!=', $h->id)->exists()) {
                $this->warn(sprintf('건너뜀 — [%s] 번호 %s 를 쓰는 다른 병원이 있습니다',
                    $줄['name'], $줄['to']));
                continue;
            }

            $h->forceFill(['code' => $줄['to']])->save();

            activity()->performedOn($h)->log(sprintf(
                '위드웍스 요양기관번호로 맞췄습니다 — %s : [%s] → [%s]',
                $줄['name'], $줄['from'] ?: '빈 값', $줄['to']));

            $고친것++;
        }

        $this->info(sprintf('번호 맞추기 — %d곳', $고친것));
    }

    private function 보여주기(array $담을것, array $메모, array $개명할것, array $번호고칠것): void
    {
        $this->newLine();
        $this->line('<comment>=== 담을 이름 ===</comment>');
        foreach ($담을것 as $줄) {
            $this->line(sprintf('  [%s] 번호=%s · 원본 %s건',
                $줄['name'], $줄['code'] ?? '없음', number_format($줄['used'])));
        }

        $this->newLine();
        $this->line('<comment>=== 메모로 보아 담지 않는 것 ===</comment>');
        foreach ($메모 as $줄) {
            $this->line(sprintf('  [%s] 번호=%s · 원본 %s건',
                $줄['name'], $줄['code'] ?? '없음', number_format($줄['used'])));
        }

        $this->newLine();
        $this->line('<comment>=== 바꿀 이름 (또렷한 것만) ===</comment>');
        foreach ($개명할것['할것'] as $줄) {
            $this->line(sprintf('  #%d %s · [%s] → [%s]', $줄['id'], $줄['code'], $줄['from'], $줄['to']));
        }

        $this->newLine();
        $this->line('<comment>=== 걸러진 것 (사람이 봐야 한다) ===</comment>');
        foreach ($개명할것['걸러진것'] as $줄) {
            $this->line(sprintf('  #%d %s · [%s] → [%s] — %s',
                $줄['id'], $줄['code'], $줄['from'], $줄['to'], $줄['why']));
        }

        $this->newLine();
        $this->line('<comment>=== 맞출 번호 ===</comment>');
        foreach ($번호고칠것 as $줄) {
            $this->line(sprintf('  #%d [%s] %s → %s',
                $줄['id'], $줄['name'], $줄['from'] ?: '빈 값', $줄['to']));
        }
    }
}
