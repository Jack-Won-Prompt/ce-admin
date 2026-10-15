<?php

namespace App\Console\Commands;

use App\Models\TossPayment;
use App\Services\TossPayments\VirtualAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 입금을 기다리는 가상계좌를 토스에 되물어, 들어온 돈을 놓치지 않는다 (2026-10-15 지시).
 *
 * ## 왜 필요한가
 *
 * 여태 가상계좌 입금을 아는 길은 **토스 웹훅 하나뿐**이었다. 그 한 번이 실패하면
 * 돈은 들어왔는데 우리는 영영 모른다 — 화면에는 「입금 대기」로 남고, 증빙도
 * 창고도 서지 않는다.
 *
 * 실제로 그렇게 됐다. 2026-10-14 15:26~15:44 에 웹훅 마흔한 번이 401 로 거절됐고
 * (우리 서명 검증이 막았다 — 그날 고쳤다), 그 사이 입금된 건을 사람이 하나씩
 * 찾아 맞췄다. 486,000원이었다. 토스는 「가상계좌 입금 완료 콜백 호출이
 * 실패했습니다」라는 메일만 보낼 뿐, 우리가 묻지 않으면 다시 알려 주지 않는다.
 *
 * 웹훅이 실패하는 길은 그 하나가 아니다 — 저쪽 재시도가 다 끝나거나, 우리 서버가
 * 잠깐 멈췄거나, 인증서가 끊기면 똑같이 놓친다. **받는 길 하나에 돈을 걸지 않는다.**
 *
 * ## 어떻게 하나
 *
 * 입금을 기다리는 줄을 토스에 하나씩 물어보고, 저쪽이 `DONE` 이라고 답하면
 * **웹훅이 하는 바로 그 자리**(`handleDepositWebhook`)를 부른다. 두 길이 각자
 * 처리하면 언젠가 갈린다 — 들어오는 문만 둘이고 하는 일은 하나다.
 *
 * 본문을 지어내 넣는 것이 아니다. 그 자리는 결제키로 토스에 다시 물어 확인한
 * 값으로만 움직이므로, 우리가 결제키만 건네도 저쪽 답이 정본이다.
 *
 * ## 무엇을 묻지 않는가
 *
 *   · 기한이 지난 지 이틀이 넘은 줄 — 그 계좌는 닫혀 입금될 수 없다
 *   · 결제키가 없는 줄 — 물어볼 것이 없다
 *   · 이미 입금 시각이 적힌 줄 — 끝난 일이다
 *
 * 묻는 건수가 많지 않다(운영에서 한 번에 서넛). 토스에 부담을 주지 않는다.
 */
class TossSyncDepositsCommand extends Command
{
    protected $signature = 'toss:sync-deposits
                            {--limit=50 : 한 번에 물어볼 최대 건수}
                            {--grace=2 : 기한이 지난 뒤 며칠까지 더 물어볼지}
                            {--dry : 저쪽 상태만 보고 **반영하지 않는다**}';

    protected $description = '입금 대기 중인 가상계좌를 토스에 되물어 입금을 반영한다';

    public function handle(VirtualAccountService $va): int
    {
        $살피기 = (bool) $this->option('dry');
        $한도   = max(1, (int) $this->option('limit'));
        $말미   = max(0, (int) $this->option('grace'));
        $기한선 = now()->subDays($말미);

        $줄들 = TossPayment::query()
            ->whereNull('deleted_at')
            ->where('method', 'VIRTUAL_ACCOUNT')
            ->where('status', 'WAITING_FOR_DEPOSIT')
            ->whereNull('deposited_at')
            ->whereNotNull('payment_key')
            ->where('payment_key', '!=', '')
            /* 기한이 없으면 묻는다 — 없다고 지나치면 놓친 건이 영영 남는다 */
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $기한선))
            ->orderBy('id')
            ->limit($한도)
            ->get();

        $this->info("물어볼 줄 {$줄들->count()}건");

        $반영 = 0; $그대로 = 0; $탈 = 0;

        foreach ($줄들 as $줄) {
            try {
                $답 = $va->fetchByPaymentKey((string) $줄->payment_key);
                $저쪽상태 = (string) ($답['status'] ?? '');

                if ($저쪽상태 !== 'DONE') {
                    $그대로++;
                    continue;
                }

                if ($살피기) {
                    $반영++;
                    $this->warn('   [살피기] 반영될 것 : 주문 ' . (string) ($줄->order?->order_number ?? $줄->order_id)
                        . ' · ' . number_format((float) $줄->amount) . '원 · 결제키 '
                        . mb_substr((string) $줄->payment_key, 0, 16) . '…');
                    continue;
                }

                /* 웹훅이 하는 그 자리를 그대로 부른다 — 하는 일은 하나여야 한다 */
                $결과 = $va->handleDepositWebhook(['data' => [
                    'paymentKey' => (string) $줄->payment_key,
                    'orderId'    => (string) $줄->toss_order_id,
                    'status'     => 'DONE',
                ]]);

                if ($결과) {
                    $반영++;
                    $this->warn("   입금 반영 : 주문 "
                        . (string) ($결과->order?->order_number ?? $줄->order_id)
                        . ' · ' . number_format((float) $줄->amount) . '원');

                    Log::warning('[Toss] 웹훅이 놓친 입금을 대사로 찾아 반영했다', [
                        'order'       => $결과->order?->order_number,
                        'payment_key' => $줄->payment_key,
                        'amount'      => $줄->amount,
                    ]);
                } else {
                    $탈++;
                    $this->error('   반영하지 못했다 : ' . (string) $줄->payment_key
                        . ' — ' . (string) ($va->건너뛴까닭 ?? '까닭 없음'));
                }
            } catch (\Throwable $e) {
                $탈++;
                /* 한 줄이 넘어져도 나머지는 본다 — 저쪽이 잠깐 답하지 않을 수 있다 */
                Log::warning('[Toss] 입금 대사 중 한 줄이 넘어졌다', [
                    'payment_key' => $줄->payment_key,
                    'error'       => $e->getMessage(),
                ]);
                $this->error('   물어보지 못했다 : ' . (string) $줄->payment_key
                    . ' — ' . mb_substr($e->getMessage(), 0, 80));
            }
        }

        $this->info(($살피기 ? '[살피기] 반영될 것 ' : '입금 반영 ')
            . "{$반영}건 · 아직 대기 {$그대로}건 · 탈 {$탈}건");

        return self::SUCCESS;
    }
}
