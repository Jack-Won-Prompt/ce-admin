<?php

namespace App\Console\Commands;

use App\Support\ClaimAgency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 처방전을 우리 처방전으로 옮긴다 (2026-09-29 지시 — 최근 3년치만).
 *
 * 원천은 거울 표 `ww_prescription_infos` 다(위드웍스 `account_add_informations`). 저쪽 운영
 * DB 를 여기서 다시 읽지 않는다 — 거울은 `withworks:import prescription_infos` 가 채운다.
 *
 * ## udf 짝
 *
 * 저쪽은 항목 이름이 udf1…udf50 이다. 짝은 **우리 소스에 이미 적혀 있던 것**을 쓴다 —
 * `PrescriptionController::counselingColumns()` 가 화면으로 내보낼 때 쓰는 그 표다. 이름만
 * 보고 짐작한 자리는 아래에 따로 적어 둔다.
 *
 * ## 갈래
 *
 * 원천 `type` 은 10 처방전-원외 · 20 처방외 · 30 처방전-원내 다(`admin.code_lists` 의
 * `ACCADDTYPE`). 「처방외」는 처방전이 아니므로 기본으로 옮기지 않는다 — 함께 옮기려면
 * `--처방외` 를 적는다.
 *
 * ## 상태
 *
 * 원천 `status` 는 02 등록 · 95 확인 이다(`ACCADDSTATUS`). 확인된 것은 우리 `approved`,
 * 등록만 된 것은 `pending` 으로 놓는다.
 *
 * ## 담지 않는 것
 *
 * - `udf1`(주민등록번호)은 **평문**이다. 거래처에 이미 암호화해 담겨 있으므로 처방전으로
 *   옮기지 않는다. 옮기면 같은 주민번호가 다섯 만 장에 한 번 더 퍼진다.
 * - `udf29`(모든 서류발행일) · `udf31`(원본서류보관) · `udf50`(NPD vs. Veteran) 은 우리에게
 *   맞는 칸이 없다. 거울 표에 그대로 남아 있으니 잃는 것은 없다.
 * - `udf4ㆍ6ㆍ19ㆍ22ㆍ23ㆍ32ㆍ42ㆍ43` 은 거래처 칸이다(저쪽이 거래처에서 베껴 둔 것).
 *   거래처 이관이 이미 담았으므로 여기서 다시 쓰지 않는다.
 * - 첨부파일은 이 명령이 다루지 않는다 — 파일이 저쪽 웹서버에 있어 따로 받아야 한다.
 *
 * **다시 돌려도 겹치지 않는다** — `ww_add_id` 에 유일 색인이 있다.
 */
class MigratePrescriptionsFromWithworksCommand extends Command
{
    protected $signature = 'prescriptions:migrate-from-ww
                            {--force : 실제로 옮긴다. 없으면 세어 보이기만 한다}
                            {--years=3 : 최근 몇 년치}
                            {--처방외 : 원천 갈래 20(처방외)도 함께 옮긴다}
                            {--limit= : 몇 줄만 시험 삼아}';

    protected $description = '위드웍스 처방전을 우리 처방전으로 옮깁니다 (거울 표에서 읽습니다)';

    /** 원천 상태(ACCADDSTATUS) → 우리 상태 */
    private const 상태 = ['95' => 'approved', '02' => 'pending'];

    /** 원천 갈래(ACCADDTYPE) → 사람이 읽는 말 */
    private const 갈래 = ['10' => '처방전 - 원외', '20' => '처방외', '30' => '처방전 - 원내'];

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');

        if (! Schema::hasColumn('prescriptions', 'ww_add_id')) {
            $this->error('prescriptions.ww_add_id 가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $해수 = max(1, (int) $this->option('years'));
        $기준 = now()->subYears($해수)->toDateString();
        $갈래들 = $this->option('처방외') ? ['10', '20', '30'] : ['10', '30'];

        $this->line('');
        $this->info('══ 위드웍스 처방전 → 우리 처방전 '
            . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line("  원천 : ww_prescription_infos (거울 표 · 읽기만 합니다)");
        $this->line("  때   : reg_date >= {$기준} (최근 {$해수}년)");
        $this->line('  갈래 : ' . implode(' · ', array_map(fn ($t) => self::갈래[$t], $갈래들)));
        $this->line('');

        /* 거래처 번호 지도 — 줄마다 물으면 다섯 만 번 오간다 */
        $거래처 = DB::table('patients')->whereNotNull('ww_account_id')
            ->pluck('id', 'ww_account_id')->all();
        $this->line('  이어진 거래처 ' . number_format(count($거래처)) . '명');

        /* 청구 기관 이름 지도 — 빈칸을 없앤 이름으로 찾는다 */
        $기관 = [];
        foreach (DB::table('billing_offices')->get(['id', 'office_name']) as $o) {
            $기관[preg_replace('/\s+/u', '', (string) $o->office_name)] = $o->id;
        }
        $this->line('  청구 기관 ' . number_format(count($기관)) . '곳');

        /* 이미 옮긴 원천 번호 */
        $이미 = DB::table('prescriptions')->whereNotNull('ww_add_id')
            ->pluck('ww_add_id')->flip();

        /* rx_number 가 겹치면 안 된다(유일 색인) — 이미 쓰인 번호를 미리 쥔다 */
        $쓴번호 = DB::table('prescriptions')->whereNotNull('rx_number')
            ->pluck('rx_number')->flip();

        $묶음표 = 'ww-rx-' . now()->format('Ymd-His');

        $질의 = DB::table('ww_prescription_infos as p')
            ->join('ww_customers as c', 'c.ww_id', '=', 'p.to_account_id')
            ->whereRaw("c.account_name LIKE '(E)%'")
            ->whereNull('c.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereIn('p.type', $갈래들)
            ->whereDate('p.reg_date', '>=', $기준)
            ->orderBy('p.ww_id')
            ->select('p.*');

        if ($한도 = $this->option('limit')) {
            $질의->limit((int) $한도);
        }

        $셈 = ['모두' => 0, '새로' => 0, '이미있음' => 0, '거래처없음' => 0,
               '번호겹침' => 0, '기관못찾음' => 0, '기관맞춤' => 0];
        $보기 = [];
        $담을것 = [];
        $못찾은기관 = [];

        $질의->chunk(500, function ($줄들) use (
            &$셈, &$보기, &$담을것, &$못찾은기관, $거래처, $기관, $이미, $쓴번호, $정말, $묶음표
        ) {
            foreach ($줄들 as $p) {
                $셈['모두']++;

                if (isset($이미[$p->ww_id])) {
                    $셈['이미있음']++;

                    continue;
                }

                $환자 = $거래처[$p->to_account_id] ?? null;

                if (! $환자) {
                    $셈['거래처없음']++;

                    continue;
                }

                /* 원천 번호는 「ADD0072363」 과 맨 숫자 「77628」 두 꼴로 섞여 있다
                   (최근 3년 · 갈래 10ㆍ30 : ADD 12,218 · 맨 숫자 38,745). 맨 숫자를 그대로
                   담으면 처방번호 칸에 「77628」 이 서서 우리 번호(RX-20260929-001)와
                   구별되지 않는다 — 옮겨 온 것임을 번호로 알 수 있게 앞에 WW- 를 붙인다.
                   원래 값은 거울 표의 add_no 에 그대로 있다. */
                $번호 = trim((string) $p->add_no);
                $번호 = $번호 === '' ? '' : 'WW-' . $번호;

                if ($번호 === '' || isset($쓴번호[$번호])) {
                    $셈['번호겹침']++;

                    continue;
                }

                $급여 = $this->글($p->udf11, 20);
                $기관번호 = null;

                if ($이름 = $this->기관이름($p->udf28)) {
                    $기관번호 = $this->기관찾기($이름, $기관);

                    if ($기관번호) {
                        $셈['기관맞춤']++;
                    } else {
                        $셈['기관못찾음']++;
                        $못찾은기관[$이름] = ($못찾은기관[$이름] ?? 0) + 1;
                    }
                }

                $셈['새로']++;

                if (count($보기) < 5) {
                    $보기[] = [$p->ww_id, $번호, $환자, self::갈래[$p->type] ?? $p->type,
                        $급여 ?: '-', $this->날($p->udf12) ?: '-', $기관번호 ?: '-'];
                }

                if (! $정말) {
                    continue;
                }

                $담을것[] = [
                    'ww_add_id'        => $p->ww_id,
                    'rx_number'        => $번호,
                    'patient_id'       => $환자,
                    'status'           => self::상태[$p->status] ?? 'pending',
                    'is_blank_draft'   => 0,

                    /* 처방 내용 */
                    'diagnosis_date'   => $this->날($p->udf2),      // 진단확인일
                    'disease_class'    => $this->글($p->udf3, 100),  // 상병구분
                    'disease_code'     => $this->글($p->udf5, 200),  // 상병코드
                    'uro_date'         => $this->날($p->udf7),      // 요류 역학 검사일
                    'daily_count'      => $this->셈($p->udf8, 65535),   // 1일 처방 개수
                    'total_days'       => $this->셈($p->udf9, 65535),   // 총 처방기간
                    'total_count'      => $this->셈($p->udf10, 4294967295), // 총계
                    'issued_date'      => $this->날($p->udf12),     // 처방전 발행일
                    'rx_use_period'    => $this->셈($p->udf13, 2147483647), // 사용 기간
                    'rx_end_date'      => $this->날($p->udf14),     // 처방전 종료일
                    'doctor_name'      => $this->글($p->udf15, 255), // 담당 의사명
                    'next_repurchase'  => $this->날($p->udf30),     // 다음 재구매 가능일

                    /* 갈래ㆍ청구 */
                    'benefit_class'    => $급여,                     // udf11 급여구분
                    'claim_agency'     => ClaimAgency::fromBenefitClass($급여),
                    'billing_office_id' => $기관번호,                // udf28 환급 해당 기관
                    'purchase_type'    => $this->글($p->udf17, 20),  // 신구매ㆍ재구매
                    'special_case'     => $this->글($p->udf18, 50),  // 입원산재보훈출국
                    'reason'           => $this->글($p->udf20, 200), // 사유

                    /* 병원ㆍ사람 */
                    'hospital_name'    => $this->병원이름($p->udf33),
                    'hospital_code'    => $this->글($p->udf39, 255), // 요양기관번호
                    'caregiver_name'   => $this->글($p->udf24, 50),  // 보호자명
                    'order_manager'    => $this->글($p->udf25, 50),  // 주문 담당자

                    /* 원천이 udf 아닌 칸으로 들고 있던 것 */
                    'five_program'     => $this->글($p->five_program, 10),
                    'five_110days'     => $this->글($p->five, 50),
                    'diverticulums'    => $this->글($p->diverticulums, 10),
                    'admin_note'       => $this->비고($p),

                    'data_origin'      => 'migration',
                    'data_batch'       => $묶음표,

                    /* 언제 적힌 처방전인지는 원천이 안다 */
                    'created_at'       => $p->created_at ?: ($p->reg_date ?: now()),
                    'updated_at'       => $p->updated_at ?: ($p->created_at ?: now()),
                ];

                /* 같은 판에서 같은 번호가 두 번 오는 것도 막는다 */
                $쓴번호[$번호] = true;

                if (count($담을것) >= 300) {
                    DB::table('prescriptions')->insert($담을것);
                    $담을것 = [];
                }
            }
        });

        if ($정말 && $담을것 !== []) {
            DB::table('prescriptions')->insert($담을것);
        }

        $this->line('');
        $this->table(['원천 #', '처방번호', '거래처 #', '갈래', '급여', '발행일', '청구기관 #'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($못찾은기관 !== []) {
            arsort($못찾은기관);
            $this->line('');
            $this->warn('  청구 기관 이름을 우리 표에서 못 찾은 것 ' . number_format(count($못찾은기관)) . '가지 —');
            $this->line('  (billing_offices 에 그 기관이 없다는 뜻이다. 거울 표에 원래 값이 그대로 남아 있다)');
            foreach (array_slice($못찾은기관, 0, 15, true) as $이름 => $몇) {
                $this->line(sprintf('    %-26s %s장', $이름, number_format($몇)));
            }
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  옮겼습니다. 처방전이 지금 '
                . number_format(DB::table('prescriptions')->count()) . '장입니다.'
                . ' (묶음 ' . $묶음표 . ')');
        }

        $this->line('  ww_prescription_infos · ww_customers 는 읽기만 했습니다.');
        $this->line('  udf1(주민등록번호)ㆍ첨부파일은 옮기지 않았습니다.');
        $this->line('');

        return self::SUCCESS;
    }

    /** 글자 칸 — 비면 null, 넘치면 자른다 */
    private function 글(mixed $값, int $길이): ?string
    {
        $v = trim((string) $값);

        return $v === '' ? null : mb_substr($v, 0, $길이);
    }

    /**
     * 날짜 칸 — 원천은 varchar 라 무엇이든 들어 있다.
     *
     * 「0000-00-00」ㆍ「미정」ㆍ「2026.09.08」 같은 것이 섞여 있다. 읽을 수 있는 것만 담고
     * 나머지는 비운다 — 엉뚱한 날짜를 담으면 재구매일 알림이 헛돈다.
     */
    private function 날(mixed $값): ?string
    {
        $v = trim((string) $값);

        if ($v === '' || str_starts_with($v, '0000')) {
            return null;
        }

        $v = str_replace(['.', '/'], '-', $v);

        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
            return null;
        }

        [$해, $달, $날] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        return checkdate($달, $날, $해) ? sprintf('%04d-%02d-%02d', $해, $달, $날) : null;
    }

    /** 셈 칸 — 숫자가 아니면 비운다. 칸보다 크면 담지 않는다(잘라 담으면 딴 수가 된다) */
    private function 셈(mixed $값, int $상한): ?int
    {
        $v = trim((string) $값);

        if ($v === '' || ! preg_match('/^\d+$/', $v)) {
            return null;
        }

        $n = (int) $v;

        return $n <= $상한 ? $n : null;
    }

    /**
     * udf33 은 「주소지-병원명」 꼴이다 — 「서울종로대학로-서울대학교병원」.
     *
     * 뒤쪽만 병원 이름이다. 줄표가 없으면(「병원명확인중」) 그대로 둔다.
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

    /** udf28 의 여러 꼴을 우리 이름에 맞춘다 */
    private function 기관이름(mixed $값): ?string
    {
        $v = preg_replace('/\s+/u', '', trim((string) $값));

        return $v === '' ? null : $v;
    }

    /**
     * 청구 기관 찾기.
     *
     * 저쪽은 같은 곳을 여러 꼴로 적어 두었다 — 「청주서부지사(공단)」ㆍ「공단(제주지사)」ㆍ
     * 「전주북부」. 꼴을 펴서 찾되, **없으면 비워 둔다**. 「서구지사」처럼 광역시마다 있는
     * 이름은 어느 곳인지 알 수 없으므로 짐작하지 않는다.
     */
    private function 기관찾기(string $이름, array $기관): ?int
    {
        $꼴 = [$이름];

        // 「청주동부지사(공단)」 → 「청주동부지사」
        $꼴[] = preg_replace('/\((공단|건보|건강보험공단)\)$/u', '', $이름);

        // 「공단(제주지사)」 → 「제주지사」
        if (preg_match('/^(?:공단|건보|건강보험공단)\((.+)\)$/u', $이름, $m)) {
            $꼴[] = $m[1];
        }

        // 「전주북부」 → 「전주북부지사」
        foreach ($꼴 as $x) {
            if ($x !== '' && ! preg_match('/(지사|출장소|구청|시청|군청|사무소|센터)$/u', $x)) {
                $꼴[] = $x . '지사';
            }
        }

        foreach (array_unique(array_filter($꼴)) as $x) {
            if (isset($기관[$x])) {
                return $기관[$x];
            }
        }

        return null;
    }

    /**
     * 비고.
     *
     * 원천 `descr` 과 갈래를 함께 적어 둔다. 갈래(원외ㆍ원내ㆍ처방외)를 담을 칸이 우리에게
     * 없어서다 — 화면에서 왜 이 처방전이 이렇게 보이는지 되짚을 실마리는 남겨야 한다.
     */
    private function 비고(object $p): ?string
    {
        $줄 = ['[이관] 원천 ' . (self::갈래[$p->type] ?? $p->type)
               . ' · 원천 번호 ' . $p->ww_id
               . ' · 등록일 ' . ($p->reg_date ?: '-')];

        if (($비고 = trim((string) $p->descr)) !== '') {
            $줄[] = $비고;
        }

        return implode("\n", $줄);
    }
}
