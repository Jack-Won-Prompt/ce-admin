<?php

namespace App\Console\Commands;

use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 주문이 사라진 세금계산서 줄을 팝빌에서 지운다 (2026-09-27 지시).
 *
 * 시험을 돌리다 주문을 지우면 팝빌의 세금계산서는 남는다. 그러면 전자세금계산서
 * 화면에 **주문번호가 「—」인 줄**이 서고, 내일처럼 여럿이 모여 보는 자리에서
 * 「이건 무슨 건이냐」로 시간을 잡아먹는다.
 *
 * **표에서 지우는 것만으로는 안 된다.** 그 화면은 열 때마다 팝빌에서 그 기간을
 * 통째로 받아 DB 에 없는 것을 새로 만든다 — 우리 표는 거울이고 정본은 팝빌에 있다.
 * 그래서 팝빌에서 지워야 하고, 팝빌은 **발행취소된 것만** 지울 수 있다.
 *
 * 되돌릴 수 없는 일이라 기본은 「보여 주기만」이다. 정말 지우려면 --force 를 준다.
 */
class PurgeOrphanTaxinvoices extends Command
{
    protected $signature = 'popbill:purge-orphan-taxinvoices
                            {--force : 정말 지운다 (없으면 무엇을 지울지 보여 주기만 한다)}
                            {--cancel-live : 아직 살아 있는 건은 발행취소부터 하고 지운다}';

    protected $description = '주문이 사라진 세금계산서를 팝빌에서 지운다';

    public function handle(TaxinvoiceService $svc): int
    {
        $줄 = DB::table('popbill_taxinvoices as t')
            ->leftJoin('orders as o', 'o.id', '=', 't.order_id')
            ->whereNull('o.id')
            ->select('t.*')
            ->orderBy('t.id')
            ->get();

        if ($줄->isEmpty()) {
            $this->info('주문이 사라진 세금계산서가 없습니다.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', '관리번호', '상태', '받는이', '합계', '작성일'],
            $줄->map(fn ($r) => [
                $r->id,
                $r->mgt_key,
                (int) $r->state_code === 600 ? '600 발행취소' : $r->state_code . ' 살아 있음',
                $r->invoicee_corp_name,
                number_format((int) $r->total_amount),
                $r->write_date,
            ])->all()
        );

        if (! $this->option('force')) {
            $this->warn('보여 주기만 했습니다. 정말 지우려면 --force 를 주십시오.');

            return self::SUCCESS;
        }

        $지움 = 0;
        $남김 = 0;

        foreach ($줄 as $r) {
            /* 팝빌은 발행취소된 것만 지운다 */
            if ((int) $r->state_code !== 600) {
                if (! $this->option('cancel-live')) {
                    $this->line("건너뜀(살아 있음) {$r->mgt_key} state={$r->state_code}");
                    $남김++;

                    continue;
                }

                try {
                    $c = $svc->cancelIssue($r->corp_num, $r->mgt_key_type, $r->mgt_key, '시험 자료 정리 (주문 삭제됨)');
                    $this->line("발행취소 {$r->mgt_key} → code=" . ($c->code ?? '?') . ' ' . ($c->message ?? ''));
                } catch (\Throwable $e) {
                    $this->error("발행취소 실패 {$r->mgt_key} → " . $e->getMessage());
                    $남김++;

                    continue;
                }
            }

            try {
                $res  = $svc->delete($r->corp_num, $r->mgt_key_type, $r->mgt_key);
                $code = (int) ($res->code ?? 0);
                $this->line("삭제 {$r->mgt_key} → code={$code} " . ($res->message ?? ''));

                if ($code === 1) {
                    DB::table('popbill_taxinvoices')->where('id', $r->id)->delete();
                    $지움++;
                } else {
                    $남김++;
                }
            } catch (\Throwable $e) {
                $this->error("삭제 실패 {$r->mgt_key} → " . $e->getMessage());
                $남김++;
            }
        }

        $this->info("지움 {$지움}건 · 남김 {$남김}건");

        return self::SUCCESS;
    }
}
