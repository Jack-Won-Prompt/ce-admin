<?php

namespace App\Console\Commands;

use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 운영 고객 정보를 거래처 관리로 옮긴다 (2026-09-29 지시).
 *
 * 원천은 `ww_customers` — 위드웍스 운영 DB 에서 옮겨 담아 둔 자리다. **그 표는 읽기만
 * 한다.** 고치지도 지우지도 않는다(운영 데이터 메뉴의 자리).
 *
 * 옮길 것을 가르는 잣대는 이름의 `(E)` 접두사다. 위드웍스에서 환자 계정은 모두 그렇게
 * 서고, 대리점ㆍ기관은 「길의료기」ㆍ「소매(End User-…)」처럼 선다. 12,829명이 환자다.
 *
 * **다시 돌려도 덧쓰기다.** ww_account_id 에 유일 색인이 있어 같은 사람이 두 줄로 서지
 * 않는다. 시험을 다시 하려면 test-data:purge 로 묶음째 지우고 다시 돌리면 된다.
 *
 * 먼저 세어 보이고 그 다음에 옮긴다 — --force 가 없으면 아무것도 쓰지 않는다.
 */
class MigratePatientsFromWithworksCommand extends Command
{
    protected $signature = 'patients:migrate-from-ww
                            {--force : 실제로 옮긴다. 없으면 세어 보이기만 한다}
                            {--batch= : 묶음 이름. 비우면 오늘 날짜로 짓는다}
                            {--limit= : 몇 명만 시험 삼아}
                            {--with-signs : 위임장 서명도 함께 잇는다}';

    protected $description = '운영 고객 정보(ww_customers)를 거래처 관리로 옮긴다';

    /** 읽기만 하는 표 — 이 명령은 여기에 쓰지 않는다 */
    private const 읽기만 = ['ww_customers', 'ww_customer_addresses', 'ww_prescription_infos'];

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');
        $묶음 = $this->option('batch') ?: ('ww-' . now()->format('Ymd-His'));

        $this->line('');
        $this->info('══ 운영 고객 → 거래처 관리 ' . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line('  묶음 : ' . $묶음);
        $this->line('  원천 : ww_customers (읽기만 합니다 — 고치지도 지우지도 않습니다)');
        $this->line('');

        $질의 = DB::table('ww_customers')
            ->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->orderBy('ww_id');

        if ($한도 = $this->option('limit')) {
            $질의->limit((int) $한도);
        }

        $셈 = ['모두' => 0, '새로' => 0, '덧씀' => 0, '주민번호' => 0, '주소' => 0,
               '전화이상' => 0, '이름겹침' => 0, '주민겹침' => 0];

        /* 겹치는 사람을 미리 센다 — 한 사람이 위드웍스에 두 계정으로 있는 일이 있다.
           이름 52명ㆍ주민번호 106명(2026-09-29 확인). 옮기기 자체는 막지 않는다 —
           ww_account_id 가 다르므로 두 줄로 서고, 사람이 보고 합친다. */
        $이름겹침 = DB::table('ww_customers')->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->selectRaw('account_name')->groupBy('account_name')
            ->havingRaw('COUNT(*) > 1')->pluck('account_name')->flip();

        $주민겹침 = DB::table('ww_customers')->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->whereRaw("resident_no REGEXP '^[0-9]{6}-?[0-9]{7}$'")
            ->selectRaw('resident_no')->groupBy('resident_no')
            ->havingRaw('COUNT(*) > 1')->pluck('resident_no')->flip();

        $보기 = [];

        $질의->chunk(500, function ($줄들) use (&$셈, &$보기, $정말, $묶음, $이름겹침, $주민겹침) {
            foreach ($줄들 as $w) {
                $셈['모두']++;

                $이름 = Patient::bare($w->account_name);          // (E) 를 뗀다
                $번호 = $this->전화($w->phone_1);
                $주소 = $this->주소($w->address_id);

                if (isset($이름겹침[$w->account_name])) { $셈['이름겹침']++; }
                if ($w->resident_no && isset($주민겹침[$w->resident_no])) { $셈['주민겹침']++; }
                if ($w->phone_1 && ! $번호) { $셈['전화이상']++; }
                if ($주소) { $셈['주소']++; }

                $있나 = Patient::withTrashed()->where('ww_account_id', $w->ww_id)->first();
                $있나 ? $셈['덧씀']++ : $셈['새로']++;

                if (count($보기) < 5) {
                    $보기[] = [$w->ww_id, $이름, $번호 ?: '-',
                        $w->resident_no ? '있음' : '-', $주소 ? mb_substr($주소['address'], 0, 20) : '-',
                        $있나 ? '덧씀' : '새로'];
                }

                if (! $정말) {
                    continue;
                }

                $값 = [
                    'name'            => $이름,
                    'mobile'          => $번호,
                    'ww_account_code' => $w->account_code,
                    'data_origin'     => 'migration',
                    'data_batch'      => $묶음,
                    'updated_at'      => now(),
                ] + ($주소 ?: []);

                $거래처 = $있나 ?: new Patient();
                $거래처->forceFill($값 + ['ww_account_id' => $w->ww_id]);

                /* 주민번호는 **평문으로 넣지 않는다.** resident_no 에 값을 주면 모델의
                   씌우개(setResidentNoAttribute)가 암호문ㆍ해시ㆍ별표를 갈라 담고
                   보유 근거(rrn_purpose)까지 적는다. 그 자리를 지나치면 암호화가 빠진다. */
                if ($w->resident_no && preg_match('/^\d{6}-?\d{7}$/', $w->resident_no)) {
                    $거래처->resident_no = $w->resident_no;
                    $셈['주민번호']++;
                }

                $거래처->save();
            }
        });

        $this->table(['ww_id', '이름', '휴대폰', '주민번호', '주소', '어떻게'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($this->option('with-signs')) {
            $this->서명잇기($정말);
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  옮겼습니다. 되돌리려면 test-data:purge --batch=' . $묶음 . ' 를 쓰십시오.');
        }

        $this->line('  ' . implode(' · ', self::읽기만) . ' 는 읽기만 했습니다.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 위임장 서명을 운영 고객ㆍ거래처와 잇는다.
     *
     * **서명 표는 지우지 않는다 — 칸만 채운다.** 이름 하나로 또렷이 짝지어지는 것만
     * 잇는다(3,639줄). 여럿이 걸리는 16줄은 두지 않는다 — 잘못 이으면 남의 서명이
     * 남의 거래처에 붙는다.
     */
    private function 서명잇기(bool $정말): void
    {
        $this->line('');
        $this->info('  ── 위임장 서명 잇기 ──');

        $하나 = DB::table('delegation_signs as d')
            ->whereRaw("(SELECT COUNT(*) FROM ww_customers w WHERE w.account_name = d.customer_name) = 1");

        $셀것 = (clone $하나)->count();
        $this->line('    이름 하나로 짝지어지는 서명 ' . number_format($셀것) . '줄');

        if (! $정말) {
            return;
        }

        $고침 = DB::update("
            UPDATE delegation_signs d
              JOIN ww_customers w ON w.account_name = d.customer_name
              LEFT JOIN patients p ON p.ww_account_id = w.ww_id
               SET d.ww_account_id = w.ww_id,
                   d.patient_id    = p.id
             WHERE (SELECT COUNT(*) FROM ww_customers x WHERE x.account_name = d.customer_name) = 1");

        $this->line('    이어 둔 줄 ' . number_format($고침));
    }

    /**
     * 전화번호를 쓸 수 있는 꼴로.
     *
     * 원천에 「연락: 010-5687-0026」ㆍ「010-65610542-(아들)」처럼 글이 섞인 것이 204줄
     * 있다. 숫자만 뽑되 **자릿수가 맞을 때만** 쓴다 — 어긋난 것을 담으면 그 번호로
     * 문자가 나간다.
     */
    private function 전화(?string $값): ?string
    {
        $숫자 = preg_replace('/\D/', '', (string) $값);

        return preg_match('/^01[0-9]{8,9}$/', $숫자) ? $숫자 : null;
    }

    /** 대표 주소 — address_id 가 가리키는 줄 */
    private function 주소(?int $열쇠): ?array
    {
        if (! $열쇠) {
            return null;
        }

        $a = DB::table('ww_customer_addresses')->where('ww_id', $열쇠)->first();

        if (! $a) {
            return null;
        }

        $한줄 = trim((string) $a->address_line_1);

        return $한줄 === '' ? null : [
            'address'        => mb_substr($한줄, 0, 300),
            'address_detail' => mb_substr((string) $a->address_line_2, 0, 255) ?: null,
            'postcode'       => preg_replace('/\D/', '', (string) $a->zipcode) ?: null,
        ];
    }
}
