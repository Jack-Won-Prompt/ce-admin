<?php

namespace App\Console\Commands;

use App\Services\Popbill\CashbillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 주문이 사라진 현금영수증을 팝빌에서 지운다 (2026-09-29 지시).
 *
 * 세금계산서 쪽(popbill:purge-orphan-taxinvoices)과 같은 까닭이고 같은 짜임이다 —
 * 시험을 돌리다 주문을 지우면 팝빌의 현금영수증은 남고, 현금영수증 화면에
 * 주문번호가 「—」인 줄이 선다. 우리 표는 거울이라 표만 지우면 다음에 다시 받아 온다.
 *
 * **팝빌은 발행취소된 것만 지운다.** 살아 있는 건은 먼저 취소해야 하는데,
 * 현금영수증의 취소는 「취소현금영수증을 새로 발행하는」 일이다(RevokeRegistIssue) —
 * 그러면 주인 없는 문서가 오히려 한 장 더 는다. 그래서 --cancel-live 를 주었을 때만
 * 그 길로 가고, 취소전표까지 이어서 지운다.
 *
 * 취소하려면 **원본의 국세청승인번호와 거래일자**가 있어야 한다. 표에 적어 둔
 * confirm_num · trade_date 를 쓴다 — 둘 중 하나라도 비면 취소하지 않고 건너뛴다.
 * 짐작으로 채워 넣으면 엉뚱한 거래를 취소한다.
 *
 * 되돌릴 수 없는 일이라 기본은 「보여 주기만」이다.
 */
class PurgeOrphanCashbills extends Command
{
    protected $signature = 'popbill:purge-orphan-cashbills
                            {--force : 정말 지운다 (없으면 무엇을 지울지 보여 주기만 한다)}
                            {--cancel-live : 아직 살아 있는 건은 취소현금영수증을 발행하고 지운다}';

    protected $description = '주문이 사라진 현금영수증을 팝빌에서 지운다';

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

        $this->table(
            ['id', '관리번호', '상태', '거래', '고객', '합계', '거래일'],
            $줄->map(fn ($r) => [
                $r->id,
                $r->mgt_key,
                $this->상태말($r),
                $r->trade_type,
                $r->customer_name,
                number_format((int) $r->total_amount),
                $r->trade_date,
            ])->all()
        );

        if (! $this->option('force')) {
            $this->warn('보여 주기만 했습니다. 정말 지우려면 --force 를 주십시오.');

            return self::SUCCESS;
        }

        $지움 = 0;
        $남김 = 0;

        foreach ($줄 as $r) {
            /* 먼저 그냥 지워 본다 — 이미 취소된 것과 취소전표는 이 자리에서 끝난다 */
            if ($this->지우기($svc, $r)) {
                $지움++;

                continue;
            }

            if (! $this->option('cancel-live')) {
                $this->line("  건너뜀(살아 있음) {$r->mgt_key} state={$r->state_code}");
                $남김++;

                continue;
            }

            /* 취소하려면 원본의 국세청승인번호와 거래일자가 있어야 한다 */
            if (! $r->confirm_num || ! $r->trade_date) {
                $this->warn("  취소 못함 {$r->mgt_key} — 원본 승인번호나 거래일자가 비어 있습니다");
                $남김++;

                continue;
            }

            /* 취소현금영수증의 관리번호는 새로 짓는다 — 원본과 같은 번호는 쓸 수 없다 */
            $취소번호 = 'X' . mb_substr($r->mgt_key, 0, 23);

            try {
                $c = $svc->revokeRegistIssue(
                    $r->corp_num, $취소번호, $r->confirm_num, $r->trade_date);

                $this->line('  취소발행 ' . $r->mgt_key . ' → ' . $취소번호
                    . ' code=' . ($c->code ?? '?') . ' ' . ($c->message ?? ''));
            } catch (\Throwable $e) {
                $this->error('  취소발행 실패 ' . $r->mgt_key . ' → ' . $e->getMessage());
                $남김++;

                continue;
            }

            /* 원본과 방금 만든 취소전표를 함께 지운다 — 취소전표를 남기면 주인 없는
               문서가 오히려 한 장 늘어난다 */
            $this->지우기($svc, $r) ? $지움++ : $남김++;

            try {
                $res = $svc->delete($r->corp_num, $취소번호);
                $this->line('  취소전표 삭제 ' . $취소번호 . ' → code=' . ((int) ($res->code ?? 0))
                    . ' ' . ($res->message ?? ''));
            } catch (\Throwable $e) {
                $this->warn('  취소전표 삭제 실패 ' . $취소번호 . ' → ' . $e->getMessage());
            }
        }

        $this->info("지움 {$지움}건 · 남김 {$남김}건");

        return self::SUCCESS;
    }

    /** 팝빌에서 지우고, 지워졌으면 우리 표에서도 지운다 */
    private function 지우기(CashbillService $svc, object $r): bool
    {
        try {
            $res  = $svc->delete($r->corp_num, $r->mgt_key);
            $code = (int) ($res->code ?? 0);

            if ($code === 1) {
                $this->line("  삭제 {$r->mgt_key} → 삭제 완료");
                DB::table('cashbill_records')->where('id', $r->id)->delete();

                return true;
            }

            $this->line("  삭제 안 됨 {$r->mgt_key} → code={$code} " . ($res->message ?? ''));
        } catch (\Throwable $e) {
            $this->line("  삭제 안 됨 {$r->mgt_key} → " . mb_substr($e->getMessage(), 0, 90));
        }

        return false;
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
