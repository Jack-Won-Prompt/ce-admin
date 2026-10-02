<?php

namespace App\Http\Controllers;

use App\Models\DuplicatePaymentRefund;
use App\Models\Order;
use App\Services\TossPayments\DuplicatePaymentFinder;
use App\Services\TossPayments\DuplicatePaymentRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 한 주문에 두 번 이상 들어온 결제를 찾아 돌려준다 (2026-10-02 지시).
 *
 * 찾는 일은 토스에 묻는다 — 우리 표에는 중복 결제가 남지 않기 때문이다
 * (까닭은 DuplicatePaymentFinder 의 글에 적었다).
 *
 * 돌려주는 일은 **요청과 승인을 나눈다.** 담당자가 올리고 최종승인자가 승인해야
 * 저쪽에 간다 — 되돌릴 수 없고 곧 돈이 나가는 일이다.
 */
class DuplicatePaymentController extends Controller
{
    /** 이 화면의 권한 이름 */
    private const 페이지 = 'duplicate-payments';

    public function __construct(
        private readonly DuplicatePaymentFinder $finder,
        private readonly DuplicatePaymentRefundService $refunds,
    ) {}

    public function index(Request $request)
    {
        abort_unless(perm(self::페이지, 'view'), 403, '이 화면을 볼 권한이 없습니다.');

        /* 토스를 부르는 일이라 화면을 열 때는 조회하지 않는다 — 담당자가 기간을
           고르고 「조회」를 눌러야 간다. 하루치가 50건 안팎이라 몇 초가 걸린다. */
        return view('duplicate-payments.index', [
            'from'        => $request->input('from', now()->toDateString()),
            'to'          => $request->input('to', now()->toDateString()),
            'canApprove'  => $this->승인할수있나(),
            'canRequest'  => perm(self::페이지, 'create'),
            'pending'     => DuplicatePaymentRefund::with(['order.patient', 'requestedBy', 'approvedBy'])
                                ->whereIn('status', [DuplicatePaymentRefund::요청])
                                ->latest('id')->get(),
            'recent'      => DuplicatePaymentRefund::with(['order.patient', 'requestedBy', 'approvedBy'])
                                ->whereNotIn('status', [DuplicatePaymentRefund::요청])
                                ->latest('id')->limit(30)->get(),
        ]);
    }

    /** 토스에 물어 중복을 찾는다 — 읽기만 한다 */
    public function scan(Request $request): JsonResponse
    {
        abort_unless(perm(self::페이지, 'view'), 403);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        /* 기간이 길면 저쪽을 그만큼 많이 부른다 — 화면이 하염없이 기다리지 않게 막는다 */
        $날수 = \Illuminate\Support\Carbon::parse($data['from'])
                    ->diffInDays(\Illuminate\Support\Carbon::parse($data['to']));

        if ($날수 > 31) {
            return response()->json([
                'success' => false,
                'message' => '한 번에 볼 수 있는 기간은 31일까지입니다 — 기간을 좁혀 주십시오.',
            ], 422);
        }

        try {
            $결과 = $this->finder->찾기($data['from'], $data['to']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[중복결제] 조회 실패', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '토스에서 거래를 가져오지 못했습니다 — ' . mb_substr($e->getMessage(), 0, 150),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'scanned' => $결과['scanned'],
            'queried' => $결과['queried'],
            'rows'    => array_map(fn ($r) => $this->줄로($r), $결과['rows']),
        ]);
    }

    /** 담당자가 환불을 올린다 */
    public function request(Request $request): JsonResponse
    {
        abort_unless(perm(self::페이지, 'create'), 403, '환불을 요청할 권한이 없습니다.');

        $data = $request->validate([
            'order_id'      => ['required', 'integer'],
            'payment_key'   => ['required', 'string', 'max:100'],
            'toss_order_id' => ['nullable', 'string', 'max:120'],
            'method'        => ['nullable', 'string', 'max:20'],
            'amount'        => ['required', 'integer', 'min:1'],
            'approved_at'   => ['nullable', 'string', 'max:40'],
            'note'          => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::with(['patient', 'tossPayment'])->find($data['order_id']);

        if (! $order) {
            return response()->json(['success' => false, 'message' => '주문을 찾지 못했습니다.'], 404);
        }

        $결과 = $this->refunds->요청($order, $data, $data['note'] ?? null);

        return response()->json([
            'success' => $결과['ok'],
            'message' => $결과['message'],
        ], $결과['ok'] ? 200 : 422);
    }

    /** 최종승인자가 승인한다 — 그 자리에서 실제로 환불이 나간다 */
    public function approve(DuplicatePaymentRefund $refund): JsonResponse
    {
        abort_unless($this->승인할수있나(), 403, '환불을 승인할 권한이 없습니다.');

        /* 올린 사람이 스스로 승인하지 못하게 한다 — 결재의 뜻이 그것이다 */
        if ($refund->requested_by && $refund->requested_by === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '올린 사람은 스스로 승인할 수 없습니다 — 다른 최종승인자가 승인해 주십시오.',
            ], 422);
        }

        $결과 = $this->refunds->승인($refund);

        return response()->json([
            'success' => $결과['ok'],
            'message' => $결과['message'],
        ], $결과['ok'] ? 200 : 422);
    }

    public function reject(Request $request, DuplicatePaymentRefund $refund): JsonResponse
    {
        abort_unless($this->승인할수있나(), 403, '환불을 반려할 권한이 없습니다.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $결과 = $this->refunds->반려($refund, $data['reason']);

        return response()->json([
            'success' => $결과['ok'],
            'message' => $결과['message'],
        ], $결과['ok'] ? 200 : 422);
    }

    /* ── 안쪽 ──────────────────────────────────── */

    /**
     * 승인할 수 있는가 — 반품 결재와 같은 잣대를 쓴다.
     *
     * 권한을 나눠 부여하는 동안에는 옛 `approve` 도 함께 본다(OrderReturn 참고).
     */
    private function 승인할수있나(): bool
    {
        return perm(self::페이지, 'final_approve') || perm(self::페이지, 'approve');
    }

    /** 화면이 그릴 꼴로 바꾼다 */
    private function 줄로(array $r): array
    {
        /** @var Order $order */
        $order = $r['order'];

        return [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'patient'      => $order->patient?->name ?? '-',
            'rx_number'    => $order->prescription?->rx_number ?? '-',
            'expected'     => $r['expected'],
            'paid_sum'     => $r['paid_sum'],
            'ledger_sum'   => $r['ledger_sum'],
            'excess'       => $r['excess'],
            'payments'     => array_map(fn ($p) => [
                'payment_key'   => $p['payment_key'],
                'toss_order_id' => $p['toss_order_id'],
                'method'        => $p['method'],
                'amount'        => $p['amount'],
                'approved_at'   => $p['approved_at'],
                'in_ledger'     => $p['in_ledger'],
                'refund_status' => $p['refund']?->status,
                'refund_label'  => $p['refund']?->상태말(),
            ], $r['payments']),
        ];
    }
}
