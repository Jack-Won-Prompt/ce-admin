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
            'from'       => $request->input('from', now()->toDateString()),
            'to'         => $request->input('to', now()->toDateString()),
            'canApprove' => $this->승인할수있나(),
            'canRequest' => perm(self::페이지, 'create'),

            /* 칸은 서버가 정한다 — 다른 목록 화면과 같은 길이다(wwGrid).
               renderer 는 **이름**으로 내려보내고 화면이 함수로 바꿔 끼운다. */
            'scanColumns' => self::조회칸(),
            'workColumns' => self::처리칸(),
            'workData'    => $this->처리내역(),
        ]);
    }

    /** 조회 결과 — 결제 한 줄이 한 줄이다. 환불은 결제 단위로 하기 때문이다. */
    private static function 조회칸(): array
    {
        return [
            ['header' => '주문번호',  'name' => 'order_number', 'width' => 160],
            ['header' => '고객',      'name' => 'patient',      'width' => 100, 'sortable' => true],
            ['header' => '승인 시각', 'name' => 'approved_at',  'width' => 130, 'sortable' => true],
            ['header' => '수단',      'name' => 'method',       'width' => 90,  'align' => 'center'],
            /* 「주문 결제」는 그 주문의 결제로 기록된 것이다. 무르면 결제 금액이
               0으로 읽혀 정산ㆍ증빙이 어긋나므로 환불할 수 없다. */
            ['header' => '구분',      'name' => 'kind',         'width' => 100, 'align' => 'center',
             'renderer' => 'dpKindBadge'],
            ['header' => '승인 금액', 'name' => 'amount',       'width' => 100, 'align' => 'right', 'editor' => 'number'],

            /* 아래 넷은 **주문 단위** 금액이라 같은 주문의 줄마다 되풀이된다
               (2026-10-02 지시로 이름을 맞췄다). */
            ['header' => '결제 대상 금액', 'name' => 'expected',   'width' => 120, 'align' => 'right', 'editor' => 'number'],
            ['header' => '토스 승인 금액', 'name' => 'toss_sum',   'width' => 120, 'align' => 'right', 'editor' => 'number'],
            ['header' => '결제 금액',      'name' => 'ledger_sum', 'width' => 110, 'align' => 'right', 'editor' => 'number'],
            ['header' => '중복 결제 금액', 'name' => 'excess',     'width' => 120, 'align' => 'right', 'editor' => 'number'],

            ['header' => '결제키',   'name' => 'payment_key',  'width' => 240],
            ['header' => '환불',     'name' => 'act',          'width' => 120, 'align' => 'center',
             'renderer' => 'dpRefundBadge'],
        ];
    }

    /** 환불 처리 내역 — 올린 것과 끝난 것을 한 자리에서 본다 */
    private static function 처리칸(): array
    {
        return [
            ['header' => '올린 때',   'name' => 'requested_at', 'width' => 130, 'sortable' => true],
            ['header' => '주문번호',  'name' => 'order_number', 'width' => 160],
            ['header' => '고객',      'name' => 'patient',      'width' => 100, 'sortable' => true],
            ['header' => '금액',      'name' => 'amount',       'width' => 100, 'align' => 'right', 'editor' => 'number'],
            ['header' => '상태',      'name' => 'status_label', 'width' => 100, 'align' => 'center',
             'renderer' => 'dpStatusBadge', 'sortable' => true],
            ['header' => '올린 사람', 'name' => 'requester',    'width' => 100],
            ['header' => '승인한 사람', 'name' => 'approver',   'width' => 100],
            ['header' => '고객 안내', 'name' => 'notify',       'width' => 150],
            ['header' => '결제키',    'name' => 'payment_key',  'width' => 240],
            ['header' => '처리',      'name' => 'act',          'width' => 150, 'align' => 'center',
             'renderer' => 'dpApproveBadge'],
        ];
    }

    /** 처리 내역 줄 — 승인 대기가 맨 위에 선다 */
    private function 처리내역(): array
    {
        return DuplicatePaymentRefund::with(['order.patient', 'requestedBy', 'approvedBy'])
            ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
            ->latest('id')->limit(200)->get()
            ->map(fn (DuplicatePaymentRefund $r) => [
                'id'           => $r->id,
                'requested_at' => $r->requested_at?->format('Y-m-d H:i') ?? '-',
                'order_number' => $r->order?->order_number ?? '-',
                'patient'      => $r->order?->patient?->name ?? '-',
                'amount'       => (int) $r->amount,
                'status'       => $r->status,
                'status_label' => $r->상태말(),
                'requester'    => $r->requestedBy?->name ?? '-',
                'approver'     => $r->approvedBy?->name ?? '-',
                'notify'       => $r->notify_result ?: '-',
                'payment_key'  => $r->payment_key,
                'reject'       => $r->reject_reason ?: '',
                /* 그릴 수 있는 단추가 있는가 — 그리는 일은 화면이 한다 */
                'act'          => $r->status === DuplicatePaymentRefund::요청 ? '승인' : '',
            ])->values()->all();
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

        /* 결제 한 줄이 목록 한 줄이다 — 환불은 결제 단위로 하므로, 주문을 묶어
           두면 단추를 어느 결제에 걸지 화면이 다시 풀어야 한다. */
        $줄들 = [];
        foreach ($결과['rows'] as $r) {
            foreach ($this->줄로($r) as $줄) {
                $줄들[] = $줄;
            }
        }

        return response()->json([
            'success' => true,
            'scanned' => $결과['scanned'],
            'queried' => $결과['queried'],
            'orders'  => count($결과['rows']),
            'rows'    => $줄들,
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

    /**
     * 한 주문의 결제들을 목록 줄로 편다.
     *
     * 주문 정보(고객ㆍ받을 돈ㆍ더 들어온 돈)는 줄마다 되풀이된다. 되풀이가 눈에
     * 거슬려도 그 편이 낫다 — 환불은 결제 하나를 고르는 일이고, 목록을 정렬하거나
     * 내려받을 때도 줄이 제 몫을 온전히 들고 있어야 한다.
     */
    private function 줄로(array $r): array
    {
        /** @var Order $order */
        $order = $r['order'];
        $줄들  = [];

        foreach ($r['payments'] as $p) {
            $환불 = $p['refund'];

            $줄들[] = [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'patient'      => $order->patient?->name ?? '-',
                'rx_number'    => $order->prescription?->rx_number ?? '-',
                /* 주문 단위 금액 넷 — 이름은 2026-10-02 지시를 그대로 따른다 */
                'expected'   => $r['expected'],    // 결제 대상 금액
                'toss_sum'   => $r['paid_sum'],    // 토스 승인 금액 (살아 있는 승인의 합)
                'ledger_sum' => $r['ledger_sum'],  // 결제 금액 (우리가 받은 것으로 아는 돈)
                'excess'     => $r['excess'],      // 중복 결제 금액

                'payment_key'   => $p['payment_key'],
                'toss_order_id' => $p['toss_order_id'],
                'method'        => $p['method'] ?: '-',
                'amount'        => $p['amount'],
                /* 2026-10-01T13:34:27+09:00 → 10-01 13:34:27 */
                'approved_at'   => $p['approved_at']
                    ? \Illuminate\Support\Carbon::parse($p['approved_at'])->format('m-d H:i:s')
                    : '-',

                /* 「주문 결제」는 이 주문의 결제로 적혀 있는 것이다. 무르면 받은 돈이
                   0으로 읽혀 정산ㆍ증빙이 어긋나므로 환불 대상이 아니다. */
                'kind'       => $p['in_ledger'] ? 'ledger' : 'extra',
                'kind_label' => $p['in_ledger'] ? '주문 결제' : '초과 결제',

                'refund_status' => $환불?->status,
                'refund_label'  => $환불?->상태말(),

                /* 단추를 세울 수 있는 줄인가 — 그리는 일은 화면이 한다 */
                'act' => match (true) {
                    $p['in_ledger']                => '',
                    $환불 !== null                  => '',
                    ! perm(self::페이지, 'create') => '',
                    default                        => '환불 요청',
                },
            ];
        }

        return $줄들;
    }
}
