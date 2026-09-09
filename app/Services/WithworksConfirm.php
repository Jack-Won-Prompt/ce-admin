<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 입금이 확인되면 창고의 판매주문을 확정한다(2026-09-03 확정 · 테스트 시나리오 3.3).
 *
 * 여태 판매주문은 「등록(02)」으로만 서 있었다(so_store 에 confirm=false). 돈이
 * 들어와도 창고는 그것을 모르니, 담당자가 위드웍스 화면에 들어가 손으로 확정을
 * 눌러야 출고가 시작됐다.
 *
 * 확정은 재고를 잡는 일이라 실패할 수 있다 — 가용재고가 모자라면 저쪽이 202 와 함께
 * 까닭을 돌려준다. 그때는 우리 쪽을 되돌리지 않는다. 돈은 이미 들어왔고 발행도
 * 끝났다. 확정만 다시 하면 되는 일이라, 못 했다는 것을 남기고 담당자가 잇게 둔다.
 */
final class WithworksConfirm
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function confirm(Order $order): array
    {
        $soNo = trim((string) $order->withworks_so_no);

        if ($soNo === '') {
            return ['ok' => false, 'message' => '창고에 판매주문이 아직 등록되지 않아 확정하지 못했습니다.'];
        }

        $baseUrl = rtrim((string) config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        if (! $baseUrl || ! $token) {
            return ['ok' => false, 'message' => '위드웍스 연동 설정이 없어 확정하지 못했습니다.'];
        }

        /* 거래명세서 발행일을 함께 보낸다(2026-09-09 지시).

           명세서는 입금이 확인되면 그 자리에서 만들어지고(DepositAutoIssue), 확정도
           같은 자리에서 이어진다 — 그래서 여기 올 때는 날이 이미 굳어 있다. 저쪽
           판매주문의 statement_date 가 그 자리다.

           아직 날이 없으면 보내지 않는다. 지금 셈해 보낼 수도 있지만, 그러면 종이에
           찍힌 날과 창고가 아는 날이 갈릴 수 있다 — 없는 것은 없는 대로 둔다. */
        $보낼것 = ['so_no' => $soNo];
        if ($order->statement_date) {
            $보낼것['statement_date'] = $order->statement_date->format('Y-m-d');
        }

        try {
            $res = Http::withToken($token)->timeout(20)->asForm()
                ->post("{$baseUrl}/api/v1/ce-admin/so_confirm", $보낼것);
        } catch (\Throwable $e) {
            Log::error('[위드웍스 확정] 부르지 못했습니다', [
                'order' => $order->order_number, 'so_no' => $soNo, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => '창고를 부르지 못했습니다 — ' . $e->getMessage()];
        }

        $body = $res->json();
        $ok   = (bool) ($body['result']['confirmed'] ?? ($body['success'] ?? false));

        if ($ok) {
            /* 창고가 잡았다. 우리 쪽 상태도 「확정」으로 옮긴다 — 그래야 목록에서
               「돈은 들어왔는데 아직 등록」인 건과 갈린다. */
            if ($order->status === 'pending') {
                $order->update(['status' => 'confirmed']);
            }

            $날 = isset($보낼것['statement_date'])
                ? " · 거래명세서 발행일 {$보낼것['statement_date']}"
                : '';

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("위드웍스 판매주문 확정 {$soNo}{$날}");

            return ['ok' => true, 'message' => "창고 판매주문 {$soNo} 을(를) 확정했습니다."];
        }

        /* 재고가 모자라면 여기로 온다(202). 돈은 이미 들어왔으니 되돌리지 않는다 —
           까닭을 남기고 담당자가 잇는다. */
        $why = $body['result']['error'] ?? $body['message'] ?? '사유를 알 수 없습니다';

        Log::warning('[위드웍스 확정] 확정되지 않았습니다', [
            'order' => $order->order_number, 'so_no' => $soNo,
            'status' => $res->status(), 'why' => $why,
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log("위드웍스 판매주문 확정 실패 {$soNo} — {$why}");

        return ['ok' => false, 'message' => "창고 판매주문을 확정하지 못했습니다 — {$why}"];
    }
}
