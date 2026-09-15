<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentLink;
use Illuminate\Console\Command;

/**
 * 취소된 주문에 살아 있는 결제 링크를 해지한다 (2026-09-16 지시).
 *
 * 주문 취소 경로는 살아 있는 링크를 해지하도록 되어 있는데(OrderCancelService),
 * 그 길을 타지 않고 상태만 바뀐 건이 있다 — 실제로 EUD202609132024001 은 주문이
 * 취소인데 링크 #104 가 아직 `sent` 였다. 환자가 지금 눌러도 결제된다.
 *
 * 한 번 훑어 정리하고, 앞으로도 가끔 돌려 새는 것을 잡는다. 먼저 --dry 로 무엇이
 * 걸리는지 보고 나서 실제로 닫는 것을 권한다.
 */
class CloseStalePaymentLinks extends Command
{
    protected $signature = 'payments:close-stale-links {--dry : 무엇이 걸리는지만 보여 준다}';

    protected $description = '취소된 주문에 살아 있는 결제 링크를 해지합니다';

    public function handle(): int
    {
        $볼것만 = (bool) $this->option('dry');

        $취소된주문 = Order::where('status', 'cancelled')->pluck('id');

        $살아있는링크 = PaymentLink::whereIn('order_id', $취소된주문)
            ->where('status', 'sent')
            ->with('order')
            ->get();

        if ($살아있는링크->isEmpty()) {
            $this->info('취소된 주문에 살아 있는 결제 링크가 없습니다.');

            return self::SUCCESS;
        }

        $this->warn("살아 있는 링크 {$살아있는링크->count()}건");

        foreach ($살아있는링크 as $링크) {
            $this->line(sprintf('  #%d | %s | %s원 | %s | 보냄 %s',
                $링크->id,
                $링크->order?->order_number ?? '-',
                number_format((int) $링크->amount),
                $링크->method,
                $링크->sent_at?->format('Y-m-d H:i') ?? '-'));
        }

        if ($볼것만) {
            $this->comment('--dry 이므로 닫지 않았습니다.');

            return self::SUCCESS;
        }

        foreach ($살아있는링크 as $링크) {
            $링크->update(['status' => 'cancelled']);

            if ($링크->order) {
                activity()->performedOn($링크->order)
                    ->log("취소된 주문의 결제 링크 #{$링크->id} 를 해지했습니다 (2026-09-16 정리)");
            }
        }

        $this->info("{$살아있는링크->count()}건을 해지했습니다.");

        return self::SUCCESS;
    }
}
