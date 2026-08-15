<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 교환 · 반품 · 취소.
 *
 * 지금까지는 주문을 취소하면 상태만 cancelled 로 바뀌고 끝이라, 왜 취소됐는지·물건은
 * 돌아왔는지·돈은 돌려줬는지가 남지 않았다. 교환은 다룰 자리가 없어 취소하고 새로 주문하는
 * 식으로 처리됐고 그러면 원 주문과의 연결이 끊긴다.
 *
 * 아직 못 하는 것이 둘 있다. 위드웍스 역물류 연계(CR-RTN-06)는 그쪽 API 가 정의되지 않았고,
 * 카드 결제취소(CR-RTN-09)는 카드 결제 자체가 없다. 둘 다 화면에는 자리를 두되 값만 적는다.
 */
class OrderReturnController extends Controller
{
    public function index(Request $request): View
    {
        $query = OrderReturn::with(['order.patient', 'assignee'])->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('receipt_no', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows = $query->get();

        $gridData = $rows->map(fn (OrderReturn $r) => [
            'id'        => $r->id,
            'receipt'   => $r->receipt_no,
            'type'      => $r->typeLabel(),
            'status'    => $r->statusLabel(),
            'order_no'  => $r->order?->order_number ?? '-',
            'patient'   => $r->order?->patient?->name ?? '-',
            'reason'    => OrderReturn::REASONS[$r->reason_code]['label'] ?? $r->reason_code,
            'burden'    => OrderReturn::BURDENS[$r->shipping_burden] ?? '-',
            'refund'    => $r->refund_amount ? number_format($r->refund_amount) : '-',
            'assignee'  => $r->assignee?->name ?? '-',
            'created'   => $r->created_at?->format('Y-m-d') ?? '-',
        ])->values();

        $counts = OrderReturn::selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        return view('order-returns.index', [
            'gridData' => $gridData,
            'total'    => $gridData->count(),
            'counts'   => $counts,
        ]);
    }

    /** 원 주문을 골라 신청서를 연다 */
    public function create(Request $request): View
    {
        $order = $request->filled('order')
            ? Order::with(['patient', 'items', 'prescription'])->find($request->order)
            : null;

        return view('order-returns.create', [
            'order'  => $order,
            'orders' => Order::with('patient')->latest('id')->limit(200)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id'          => 'required|exists:orders,id',
            'type'              => 'required|in:exchange,return,cancel',
            'reason_code'       => 'required|string|in:' . implode(',', array_keys(OrderReturn::REASONS)),
            'reason_text'       => 'nullable|string|max:500',
            'shipping_burden'   => 'nullable|in:customer,company',
            'collect_method'    => 'nullable|in:courier,self',
            'exchange_product'  => 'nullable|string|max:200',
            'exchange_quantity' => 'nullable|integer|min:1',
            'reship_address'    => 'nullable|string|max:300',
            'refund_method'     => 'nullable|in:account,card,va',
            'refund_bank'       => 'nullable|string|max:50',
            'refund_account'    => 'nullable|string|max:50',
            'refund_holder'     => 'nullable|string|max:50',
            'refund_amount'     => 'nullable|integer|min:0',
        ]);

        // 사유가 정해지면 배송비를 누가 무는지도 정해진다. 담당자가 고쳐 보낸 값이 있으면 그것을 쓴다.
        $data['shipping_burden'] = $data['shipping_burden']
            ?? OrderReturn::REASONS[$data['reason_code']]['burden'] ?? null;

        $return = DB::transaction(function () use ($data) {
            $return = OrderReturn::create($data + [
                'receipt_no' => OrderReturn::generateReceiptNo(),
                'status'     => 'received',
                'created_by' => Auth::id(),
            ]);

            OrderReturnLog::create([
                'order_return_id' => $return->id,
                'to_status'       => 'received',
                'reason'          => $data['reason_text'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            return $return;
        });

        activity()->causedBy(Auth::user())->performedOn($return->order)
            ->log("{$return->typeLabel()} 접수 {$return->receipt_no}");

        return redirect()->route('order-returns.show', $return)
            ->with('status', "접수했습니다. 접수번호 {$return->receipt_no}");
    }

    public function show(OrderReturn $orderReturn): View
    {
        $orderReturn->load(['order.patient', 'order.items', 'logs.creator', 'assignee', 'creator']);

        return view('order-returns.show', ['r' => $orderReturn]);
    }

    /**
     * 다음 단계로 옮긴다.
     *
     * 흐름에 없는 곳으로 건너뛰지 못하게 막는다. 검수도 안 했는데 환불완료가 되는 식이면
     * 상태를 두는 뜻이 없다.
     */
    public function advance(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $data = $request->validate([
            'to_status' => 'required|string|max:30',
            'reason'    => 'nullable|string|max:500',
        ]);

        if (!in_array($data['to_status'], $orderReturn->nextStatuses(), true)) {
            return back()->withErrors(['to_status' => '지금 상태에서 갈 수 없는 단계입니다.']);
        }

        DB::transaction(function () use ($orderReturn, $data) {
            OrderReturnLog::create([
                'order_return_id' => $orderReturn->id,
                'from_status'     => $orderReturn->status,
                'to_status'       => $data['to_status'],
                'reason'          => $data['reason'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            $orderReturn->update([
                'status'      => $data['to_status'],
                // 환불완료로 넘어가는 순간이 실제로 돈이 나간 때다
                'refunded_at' => $data['to_status'] === 'refunded' ? now() : $orderReturn->refunded_at,
            ]);

            /* 반품·취소가 끝나면 원 주문도 취소된 것이다. 주문 목록에 그대로 살아 있으면
               정산·청구가 그 주문을 계속 셈에 넣는다. */
            if (in_array($data['to_status'], ['refunded'], true)
                && in_array($orderReturn->type, [OrderReturn::TYPE_RETURN, OrderReturn::TYPE_CANCEL], true)) {
                $orderReturn->order?->update(['status' => 'cancelled']);
            }
        });

        return back()->with('status', '상태를 옮겼습니다.');
    }
}
