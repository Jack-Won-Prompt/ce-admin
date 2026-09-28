<?php

namespace App\Console\Commands;

use App\Services\Popbill\CashbillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 주문이 사라진 현금영수증 — 무엇이 되고 무엇이 안 되는지 보여 준다 (2026-09-29).
 *
 * 세금계산서 쪽(popbill:purge-orphan-taxinvoices)과 같은 일을 하려고 만들었다가,
 * **현금영수증은 그렇게 되지 않는다**는 것을 팝빌이 직접 알려 주었다. 실제로 받은
 * 답이 이렇다 —
 *
 *   -14002007  삭제 가능한 상태가 아닙니다
 *              → 국세청에 신고된(300ㆍ304) 현금영수증은 **삭제 자체가 안 된다.**
 *                세금계산서는 발행취소(600)로 내린 뒤 지울 수 있지만, 현금영수증에는
 *                그 길이 없다.
 *
 *   -14001065  취소 현금영수증의 거래금액 합계는 당초 승인 금액을 초과할 수 없습니다
 *              → 이미 취소전표가 딸린 건에 또 취소를 발행하려 해서 나온다.
 *
 *   -14001041  당초 승인 현금영수증만 취소 현금영수증을 발행할 수 있습니다
 *              → 취소전표(CRC…) 자체는 다시 취소할 수 없다.
 *
 * 그래서 이 명령은 **지우지 않는다.** 처음 판은 「지워 보고 안 되면 취소해 본다」였는데,
 * 그 길로 가니 취소전표 세 장이 새로 발행되고 그것도 지워지지 않아 **주인 없는 문서가
 * 오히려 늘었다**(2026-09-29). 현금영수증에서 취소는 「없애기」가 아니라 「거래를
 * 무효로 만들고 전표를 한 장 더 남기기」다.
 *
 * 남는 일은 둘뿐이다 —
 *   · 아직 취소되지 않은 승인거래를 **무효로 만든다** (--revoke). 자취는 줄지 않고
 *     오히려 한 장 늘어난다. 그것을 알고 눌러야 한다.
 *   · 그대로 둔다. 시험 계정의 문서는 국세청에 닿지 않는다.
 */
class PurgeOrphanCashbills extends Command
{
    protected $signature = 'popbill:purge-orphan-cashbills
                            {--revoke : 아직 취소되지 않은 승인거래를 무효로 만든다 (전표가 한 장 늘어난다)}
                            {--force : --revoke 와 함께 주어야 실제로 발행한다}';

    protected $description = '주문이 사라진 현금영수증을 보여 준다 (팝빌은 삭제를 받아 주지 않는다)';

    public function handle(CashbillService $svc): int
    {
        $줄 = DB::table('cashbill_records as c')
            ->leftJoin('orders as o', 'o.id', '=', 'c.order_id')
            ->whereNull('o.id')
            ->select('c.*')
            ->orderBy('c.id')
            ->get();

        if ($줄->isEmpty()) {
            $this->info('주문이 사라진 현금영수증이 없습니다.');

            return self::SUCCESS;
        }

        /* 이미 취소전표가 딸린 원본은 다시 취소할 수 없다. 취소전표는 원본 번호 앞에
           CRC 를 붙여 짓는 것이 우리 규칙이라, 그것으로 짝을 찾는다. */
        $취소된것 = $줄->filter(fn ($r) => str_starts_with((string) $r->mgt_key, 'CRC')
                                        || str_starts_with((string) $r->mgt_key, 'XCR'))
            ->map(fn ($r) => preg_replace('/^(CRC|XCR)/', '', (string) $r->mgt_key))
            ->flip();

        $this->table(
            ['id', '관리번호', '상태', '거래', '고객', '합계', '거래일', '무엇을 할 수 있나'],
            $줄->map(fn ($r) => [
                $r->id,
                $r->mgt_key,
                $this->상태말($r),
                $r->trade_type,
                $r->customer_name,
                number_format((int) $r->total_amount),
                $r->trade_date,
                $this->할수있는일($r, $취소된것),
            ])->all()
        );

        $this->line('');
        $this->warn('  팝빌은 국세청에 신고된 현금영수증을 삭제해 주지 않습니다 (-14002007).');
        $this->line('  세금계산서와 달리 「발행취소하고 지우는」 길이 없습니다.');
        $this->line('  할 수 있는 것은 아직 취소되지 않은 승인거래를 **무효로 만드는** 것뿐이고,');
        $this->line('  그러면 취소전표가 한 장 더 남아 자취는 오히려 늘어납니다.');
        $this->line('');

        if (! $this->option('revoke')) {
            $this->line('  무효로 만들려면 --revoke --force 를 함께 주십시오.');
            $this->line('');

            return self::SUCCESS;
        }

        $할것 = $줄->filter(fn ($r) => $this->취소할수있나($r, $취소된것));

        if ($할것->isEmpty()) {
            $this->info('  무효로 만들 수 있는 승인거래가 없습니다.');

            return self::SUCCESS;
        }

        $this->line('  무효로 만들 승인거래 ' . $할것->count() . '건');

        if (! $this->option('force')) {
            $this->warn('  보여 주기만 했습니다. --force 를 함께 주십시오.');

            return self::SUCCESS;
        }

        foreach ($할것 as $r) {
            $취소번호 = 'CRC' . mb_substr($r->mgt_key, 0, 21);

            try {
                $c = $svc->revokeRegistIssue($r->corp_num, $취소번호, $r->confirm_num, $r->trade_date);
                $this->line('  무효 ' . $r->mgt_key . ' → ' . $취소번호
                    . ' code=' . ($c->code ?? '?') . ' ' . ($c->message ?? ''));
            } catch (\Throwable $e) {
                $this->error('  무효로 만들지 못함 ' . $r->mgt_key . ' → '
                    . mb_substr($e->getMessage(), 0, 110));
            }
        }

        $this->line('');
        $this->line('  발행한 취소전표는 팝빌에 남습니다 — 지울 수 없습니다.');
        $this->line('');

        return self::SUCCESS;
    }

    /** 이 줄에 할 수 있는 일 — 사람이 표에서 바로 읽게 */
    private function 할수있는일(object $r, $취소된것): string
    {
        if (str_starts_with((string) $r->mgt_key, 'CRC') || str_starts_with((string) $r->mgt_key, 'XCR')) {
            return '없음 (취소전표)';
        }

        if (isset($취소된것[$r->mgt_key])) {
            return '없음 (이미 무효)';
        }

        if (! $r->confirm_num || ! $r->trade_date) {
            return '없음 (승인번호·거래일 없음)';
        }

        return '무효로 만들 수 있음';
    }

    private function 취소할수있나(object $r, $취소된것): bool
    {
        return $this->할수있는일($r, $취소된것) === '무효로 만들 수 있음';
    }

    /** 사람이 읽을 상태 — 숫자만 두면 무엇인지 알 수 없다 */
    private function 상태말(object $r): string
    {
        return match ((int) $r->state_code) {
            100     => '100 발행대기',
            300     => '300 발행완료',
            301     => '301 국세청 전송전',
            302     => '302 국세청 전송대기',
            303     => '303 국세청 전송중',
            304     => '304 국세청 신고완료',
            305     => '305 국세청 전송실패',
            600     => '600 발행취소',
            default => $r->state_code . ' 살아 있음',
        };
    }
}
