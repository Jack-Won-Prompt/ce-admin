<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Services\OrderNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 창고로 보내다 막힌 까닭을 남긴다 (2026-09-11 지시).
 *
 * ［주문 생성 및 연계］는 문 여섯을 지나야 나간다 — 검수ㆍ동의ㆍ총계ㆍ청구 한도ㆍ
 * 수량ㆍ배송지. 막히면 그 자리 사람에게 창이 하나 뜨고 끝이었다. 창을 닫으면
 * 아무 데도 남지 않아, 담당자가 나중에 「왜 안 나갔지」를 되짚을 수 없었다.
 *
 * 이제 막힌 까닭을 주문 이력에 적고 담당자에게 알린다. 창고 소식과 같은 방에
 * 쌓이므로, 돌아와서 그 건이 어디서 멈췄는지 볼 수 있다.
 */
class OrderBlockedController extends Controller
{
    public function store(Request $request)
    {
        /* 같은 사람이 단추를 거듭 누르면 같은 말이 쌓인다. 1분에 다섯 번까지만 남긴다 —
           막힌 사실은 한 번만 알면 되고, 그 뒤로는 화면의 창이 말해 준다. */
        $열쇠 = 'order-blocked:' . (Auth::id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($열쇠, 5)) {
            return response()->json(['success' => true, 'skipped' => 'too_many']);
        }
        RateLimiter::hit($열쇠, 60);

        $값 = $request->validate([
            'rx_number' => 'required|string|max:40',
            'gate'      => 'required|string|max:40',
            'reason'    => 'required|string|max:300',
        ]);

        $rx = Prescription::where('rx_number', $값['rx_number'])->first();
        if (! $rx) {
            return response()->json(['success' => false, 'message' => '처방전을 찾지 못했습니다.'], 404);
        }

        $order = $rx->order;
        $까닭  = trim($값['reason']);

        /* 이력은 처방전에 남긴다 — 주문이 아직 없는 자리에서도 막히기 때문이다.
           주문이 있으면 주문에도 같이 적어, 주문 상세에서 바로 보이게 한다. */
        activity()->causedBy(Auth::user())->performedOn($order ?? $rx)
            ->log("창고 전송 막힘 ({$값['gate']}): {$까닭}");

        /* 담당자에게 알린다. 창고 소식과 같은 방에 쌓여 나중에 되짚을 수 있다.
           주문이 아직 없으면 알릴 자리가 없다 — 그때는 이력만 남긴다. */
        if ($order) {
            app(OrderNotice::class)->tellOwner($order, '창고로 보내지 못했습니다 — ' . $까닭, 'warning');
        }

        return response()->json(['success' => true]);
    }
}
