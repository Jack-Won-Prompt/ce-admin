<?php

namespace App\Http\Controllers;

use App\Models\OrderReturn;
use App\Services\ReturnFinalApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 교환ㆍ반품 최종승인자 서명 — 문자로 받은 링크로 여는 공개 화면 (2026-09-28 지시).
 *
 * 로그인 밖에 둔다. 최종승인자가 폰으로 여는 자리라 로그인을 요구하면 대개 그 자리에서
 * 멈춘다 — 사내 계정을 폰에 넣어 두는 사람이 드물다.
 *
 * 그래서 링크 하나가 열쇠다. 지켜야 할 것이 셋이다 —
 *
 *   하나  **그 권한이 있는 사람에게만 보낸다** (OrderReturnController::finalSignSend).
 *   둘    **24시간이 지나면 닫힌다.** 결재는 사람이 자리에 없을 수 있어 환자용(30분)
 *         보다 길게 두지만, 며칠을 열어 두면 그 사이에 금액이 바뀐다.
 *   셋    **한 번만 받는다.** 두 번 받으면 어느 것이 참인지 가릴 수 없다.
 *
 * 위임장 서명(DelegationSignPublicController)과 달리 본인확인(NICE)은 걸지 않는다.
 * 서명할 사람이 환자가 아니라 우리 직원이고, 그 서명은 공단에 내는 서류가 아니라 내부
 * 결재 자취다. 대신 누른 자리의 IP 와 브라우저를 함께 남긴다.
 *
 * **서명이 곧 실행이다.** 실행은 ReturnFinalApproval 한 곳을 지난다 — 화면 안 서명과
 * 이 링크가 같은 자리를 부른다. 두 곳에 적으면 한쪽만 고치는 날이 온다.
 */
class ReturnSignPublicController extends Controller
{
    public function show(string $token): View
    {
        $r = OrderReturn::with(['order.patient', 'order.items', 'inspectConfirmer'])
            ->where('final_sign_token', $token)
            ->firstOrFail();

        return view('order-returns.public-sign', [
            'r'      => $r,
            '닫힘'   => $this->닫힌까닭($r),
            '금액'   => $r->움직일금액(),
            '받은것' => (int) ($r->order?->받은금액() ?? 0),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $r = OrderReturn::with('order.patient')
            ->where('final_sign_token', $token)
            ->firstOrFail();

        /* 화면에서도 막지만 여기서도 막는다 — 화면을 거치지 않고 이 주소를 바로
           부르는 길이 있다 */
        if ($닫힘 = $this->닫힌까닭($r)) {
            return response()->json(['success' => false, 'message' => $닫힘], 410);
        }

        $값 = $request->validate([
            'action'    => 'required|in:sign,reject',
            'signature' => 'nullable|string|max:500000',
            'reason'    => 'nullable|string|max:500',
        ]);

        /* 서명하는 사람은 링크를 받은 그 사람이다. 지금 로그인한 사람을 보지 않는다 —
           이 화면은 로그인 밖이고, 어쩌다 다른 계정이 열려 있으면 엉뚱한 이름이
           결재 자취에 남는다. */
        $서명자 = $r->finalSignTarget;

        if ($값['action'] === 'reject') {
            $사유 = trim((string) ($값['reason'] ?? ''));

            if ($사유 === '') {
                return response()->json(['success' => false, 'message' => '반려 사유를 적어 주십시오.'], 422);
            }

            app(ReturnFinalApproval::class)->반려($r, $서명자, $사유);

            return response()->json([
                'success' => true,
                'message' => '반려했습니다. 창고에 다시 검수를 청했습니다.',
            ]);
        }

        if (! $this->그림있나((string) ($값['signature'] ?? ''))) {
            return response()->json(['success' => false, 'message' => '서명란에 서명해 주십시오.'], 422);
        }

        $말 = app(ReturnFinalApproval::class)->서명하고실행(
            $r, $서명자, $값['signature'], $request->ip(), $request->userAgent());

        /* 실행이 막혀도 **서명은 들어갔다.** 서명한 사람에게 「실패했습니다」만 보이면
           다시 서명해야 하는 줄 알고 링크를 또 연다 — 그때는 이미 닫혀 있다. */
        $막혔나 = str_starts_with($말, '!');

        return response()->json([
            'success' => true,
            'warn'    => $막혔나,
            'message' => ltrim($말, '! '),
        ]);
    }

    // ──────────────────────────────────────────────────────

    /** 열 수 없는 까닭 — 없으면 null */
    private function 닫힌까닭(OrderReturn $r): ?string
    {
        if ($r->final_signed_at) {
            return '이미 서명을 마친 건입니다. (' . $r->final_signed_at->format('Y-m-d H:i') . ')';
        }

        if ($r->final_rejected_at && ! $r->inspect_confirmed_at) {
            return '반려로 마친 건입니다. 창고에서 다시 검수 중입니다.';
        }

        if (! $r->inspect_confirmed_at) {
            return '책임자 검수 승인이 취소되었습니다. 담당자에게 확인해 주십시오.';
        }

        if (! $r->needsFinalSign()) {
            return '금액 변동이 없는 건으로 바뀌었습니다. 서명을 받지 않습니다.';
        }

        if ($r->final_sign_expires_at && $r->final_sign_expires_at->isPast()) {
            return '링크 유효 시간(24시간)이 지났습니다. 담당자에게 다시 요청해 주십시오.';
        }

        return null;
    }

    /**
     * 손대지 않은 빈 그림을 걸러낸다.
     *
     * 화면이 canvas 를 그대로 내보내면 아무것도 그리지 않아도 흰 그림이 온다 —
     * 받아 두면 「서명함」으로 서지만 결재 자취에는 아무것도 없다.
     * 위임장 서명과 같은 잣대다(칠해진 점 서른).
     */
    private function 그림있나(string $data): bool
    {
        if (! str_starts_with($data, 'data:image/')) {
            return false;
        }

        $바이트 = base64_decode(preg_replace('~^data:image/\w+;base64,~', '', $data), true);

        if ($바이트 === false || strlen($바이트) < 200) {
            return false;
        }

        $그림 = @imagecreatefromstring($바이트);

        if (! $그림) {
            /* GD 가 없는 데서도 서명을 막지는 않는다 — 크기로만 가린다 */
            return strlen($바이트) > 1000;
        }

        $w = imagesx($그림);
        $h = imagesy($그림);
        $칠 = 0;

        for ($y = 0; $y < $h && $칠 < 30; $y += 2) {
            for ($x = 0; $x < $w && $칠 < 30; $x += 2) {
                if ((imagecolorat($그림, $x, $y) >> 24) < 100) {
                    $칠++;
                }
            }
        }
        imagedestroy($그림);

        return $칠 >= 30;
    }
}
