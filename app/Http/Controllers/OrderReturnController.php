<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\OrderReturnLog;
use App\Services\ReturnSettlement;
use App\Services\WithworksReturns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
    public function __construct(
        private readonly WithworksReturns $withworks,
        private readonly ReturnSettlement $settlement,
    ) {}

    public function index(Request $request): View
    {
        $query = OrderReturn::with(['order.patient', 'order.prescription.billingOffice', 'assignee',
                                    'order.items.lots', 'order.operationUser', 'order.tossPayment', 'items',
                                    'creator', 'approver'])->latest('id');

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

        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('order.patient_id'));

        $gridData = $rows->map(fn (OrderReturn $r) => [
            'id'        => $r->id,
            'receipt'   => $r->receipt_no,
            'type'      => $r->typeLabel(),
            // 절차서의 갈래 — 같은 「교환」이라도 변심과 불량은 하는 일이 다르다
            'scenario'  => $r->scenarioLabel(),
            'status'    => $r->statusLabel(),
            // 창고가 알려 준 그대로다 — 우리가 적는 값이 아니다
            'pl3'       => $r->pl3_status_label ?? '',
            'partial'   => $r->is_partial ? '부분' : '전체',
            // 늦은 건은 눈에 띄어야 한다 — 묻히면 절차서의 기한을 둔 뜻이 없다
            'overdue'   => ($o = $r->overdue()) ? "{$o[0]} {$o[1]}일 초과" : '',
            'order_no'  => $r->order?->order_number ?? '-',
            // 창고와 맞춰 볼 때 쓰는 번호 — 없으면 아직 알리지 못한 것이다
            'origin_so' => $r->order?->withworks_so_no ?: '-',
            'return_so' => $r->withworks_so_no ?: ($r->withworks_error ? '실패' : '미전달'),
            'patient'   => $r->order?->patient?->name ?? '-',
            'reason'    => OrderReturn::reasonLabel($r->reason_code),
            'burden'    => OrderReturn::BURDENS[$r->shipping_burden] ?? '-',
            'refund'    => $r->refund_amount ? number_format($r->refund_amount) : '-',
            'assignee'  => $r->assignee?->name ?? '-',
            'created'   => $r->created_at?->format('Y-m-d') ?? '-',

            /* 접수 화면에서도 사람과 처방을 알아볼 수 있어야 한다 — 지금까지는 이름
               하나뿐이라 누구의 무슨 건인지 상세를 열어야 알았다. */
            'resident_no' => $r->order?->patient?->masked_resident_no ?? '',
            'mobile'      => $r->order?->patient?->mobile ?? '',

            /* ── 요청서 4쪽이 더 달라 한 것들 ─────────────────────────
               우리 표에 있는 것은 그대로 꺼내고, 원 주문ㆍ결제에 있는 것은 거기서
               끌어온다. 같은 값을 두 곳에 적어 두면 언젠가 갈린다. */
            'taker'        => $r->creator?->name ?? '',
            'approver'     => $r->approver?->name ?? '',
            'approved_at'  => $r->approved_at?->format('Y-m-d') ?? '',
            // 몇 개 가운데 몇 개가 되돌아왔는가 — 부분 반품은 이 둘이 갈린다
            'qty_ordered'  => (int) $r->items->sum('ordered_quantity') ?: '',
            'qty_returned' => (int) $r->items->sum('quantity') ?: '',
            // 되돌아온 물건의 Lot — 사람이 상자를 보고 적는다
            'rt_lot'       => $r->items->pluck('lot_no')->filter()->implode(', '),
            // 수거 송장. 나갈 때의 송장은 공통 칸의 「운송장」이 세운다.
            'collect_no'   => $r->collect_tracking_no ?? '',

            // ── 환불을 어떻게 돌려줬는가 ──────────────────────
            'refund_method' => OrderReturn::REFUND_METHODS[$r->refund_method] ?? '',
            'refunded_at'   => $r->refunded_at?->format('Y-m-d') ?? '',
            'refund_bank'   => $r->refund_bank ?? '',
            'refund_holder' => $r->refund_holder ?? '',
            'refund_acct'   => $r->refund_account ?? '',
            'card_issuer'   => $r->card_issuer ?? '',
            'card_expiry'   => $r->card_expiry ?? '',
            'approval_no'   => $r->refund_approval_no ?? '',
            'handling'      => $r->handling_branch ?? '',
            'refund_agency' => $r->refund_agency ?? '',
            'rt_cash_no'    => $r->refund_cash_receipt_no ?? '',
            'rt_cash_type'  => OrderReturn::REFUND_RECEIPT_TYPES[$r->refund_cash_receipt_type] ?? '',
            'memo'          => $r->memo ?? '',
            'staff_memo'    => $r->staff_memo ?? '',

            /* ── 무엇을 물렸는가 ────────────────────────────────
               현금영수증ㆍ세금계산서 취소는 주문이 적고 있다 — 여기 옮겨 적지 않고
               그 값을 그대로 본다. 카드ㆍ무통장 취소는 우리 표에 있다. */
            'ti_cancel'   => $r->order?->tax_invoice_cancelled_at?->format('Y-m-d') ?? '',
            'cr_cancel'   => $r->order?->cash_receipt_cancelled_at?->format('Y-m-d') ?? '',
            'card_cancel' => $r->card_cancelled_at?->format('Y-m-d') ?? '',
            'bank_cancel' => $r->bank_cancelled_at?->format('Y-m-d') ?? '',

            /* ── 원 주문의 결제 ─────────────────────────────────
               가상계좌는 토스가 발급한 것이라 toss_payments 가 원본이다. */
            'va_no'     => $r->order?->tossPayment?->account_number ?? '',
            'va_bank'   => $r->order?->tossPayment?->bank ?? '',
            'va_holder' => $r->order?->tossPayment?->customer_name ?? '',

            /* ── 절차서가 정한 기한 ─────────────────────────────
               입고일에서 셈해 나오는 값이다. 적어 두지 않는 까닭은 규칙이 바뀌면
               적어 둔 옛 값이 남기 때문이다. */
            'due_inspect' => $r->inspectDueAt()?->format('Y-m-d') ?? '',
            'due_final'   => $r->finalDueAt()?->format('Y-m-d') ?? '',

            // 병원ㆍ처방 정보 탭의 칸 + 네 화면이 함께 쓰는 칸
        ] + $extras->rx($r->order?->prescription, $r->order?->patient)
          + $extras->ww($r->order, $r->order?->prescription, $r->order?->patient, $r)
          + $extras->of($r->order))->values();

        $counts = OrderReturn::selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        // 늦은 건이 몇 건인지는 목록을 다 훑어야 알 수 있다 — 화면 위에 세어 둔다
        $lateCount = $rows->filter(fn (OrderReturn $r) => $r->overdue() !== null)->count();

        return view('order-returns.index', [
            'gridData' => $gridData,
            'total'    => $gridData->count(),
            'counts'   => $counts,
            'lateCount' => $lateCount,
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
            // 여기도 같은 규칙이다 — 창고에 넘긴 건만 고른다
            'orders' => Order::with('patient')->sentToWarehouse()->latest('id')->limit(200)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id'          => 'required|exists:orders,id',
            'type'              => 'required|in:exchange,return,cancel',
            // 취소의 하위 갈래 — 출고 전 취소인가, 자격 변경 같은 일반 환불인가
            'subtype'           => 'nullable|in:' . implode(',', array_keys(OrderReturn::SUBTYPES)),
            'reason_code'       => 'required|string|in:' . implode(',', array_keys(OrderReturn::reasons())),
            'reason_text'       => 'nullable|string|max:500',
            'refund_method'     => 'nullable|in:account,card,va',
            'refund_bank'       => 'nullable|string|max:50',
            'refund_account'    => 'nullable|string|max:50',
            'refund_holder'     => 'nullable|string|max:50',
            'refund_amount'     => 'nullable|integer|min:0',
            // 되돌리는 줄 — 부분 취소가 여기서 산다
            'items'                    => 'nullable|array',
            'items.*.order_item_id'    => 'nullable|integer',
            'items.*.product_code'     => 'nullable|string|max:50',
            'items.*.product_name'     => 'nullable|string|max:200',
            'items.*.ordered_quantity' => 'nullable|integer|min:0',
            'items.*.quantity'         => 'nullable|integer|min:0',
            'items.*.unit_price'       => 'nullable|integer|min:0',
            'items.*.copay'            => 'nullable|integer|min:0',
        ]);

        $rawItems = collect($data['items'] ?? [])
            ->map(fn ($i) => [
                'order_item_id'    => $i['order_item_id'] ?? null,
                'product_code'     => $i['product_code'] ?? null,
                'product_name'     => $i['product_name'] ?? null,
                'ordered_quantity' => (int) ($i['ordered_quantity'] ?? 0),
                'quantity'         => (int) ($i['quantity'] ?? 0),
                'unit_price'       => (int) ($i['unit_price'] ?? 0),
                'copay'            => (int) ($i['copay'] ?? 0),
            ])
            // 0개는 되돌리지 않는 줄이다 — 담지 않는다
            ->filter(fn ($i) => $i['quantity'] > 0)
            ->values();

        unset($data['items']);

        // 배송비를 누가 무는지는 사유가 정한다 — 접수 때 따로 묻지 않는다.
        // 담당자마다 다르게 고르면 같은 사유인데 부담 주체가 갈린다.
        $data['shipping_burden'] = OrderReturn::reasons()[$data['reason_code']]['burden'] ?? null;

        /* 취소인데 갈래를 안 골랐으면 출고 전 취소로 본다 — 지금까지 취소는 그것 하나였다.
           자격 변경은 물건이 없어 언제나 일반 환불이다. */
        if ($data['type'] === OrderReturn::TYPE_CANCEL) {
            $data['subtype'] = $data['reason_code'] === 'eligibility'
                ? OrderReturn::SUB_REFUND_ONLY
                : ($data['subtype'] ?: OrderReturn::SUB_BEFORE_SHIP);
        } else {
            $data['subtype'] = null;
        }

        /* 한 줄이라도 원 주문보다 적게 되돌리면 부분이다. 부분은 이미 발행한 계산서를
           자동으로 취소하지 않는다 — 남는 금액을 얼마로 할지는 사람이 정한다. */
        $data['is_partial'] = $rawItems->contains(
            fn ($i) => $i['ordered_quantity'] > 0 && $i['quantity'] < $i['ordered_quantity']
        );

        $return = DB::transaction(function () use ($data, $rawItems) {
            $return = OrderReturn::create($data + [
                'receipt_no' => OrderReturn::generateReceiptNo(),
                'status'     => 'received',
                'created_by' => Auth::id(),
            ]);

            foreach ($rawItems as $item) {
                OrderReturnItem::create($item + ['order_return_id' => $return->id]);
            }

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

        /* 창고에 알린다. 반품 판매주문을 세우거나(반품 5005 · 교환 5006 · 출고 후 취소),
           출고 전 취소면 원 판매주문을 취소한다.
           실패해도 접수는 살려 둔다 — 창고에 알리지 못한 것과 고객의 신청을 받지 못한 것은
           다른 일이다. 대신 왜 못 갔는지를 화면에 띄워 다시 보낼 수 있게 한다. */
        /* 일반 환불(자격 변경 등)은 여기서 창고에 알리지 않는다. 되돌려 받을 물건이 없어
           반품 주문을 세우면 창고가 오지 않을 물건을 기다리고, 원 주문은 이미 나가 정상
           출고된 건이라 취소할 것도 아니다. 금액조정 주문은 승인·결제취소를 마친 뒤
           「금액조정」 단계에서 따로 세운다. */
        if ($return->scenario() === OrderReturn::SC_REFUND_ONLY) {
            return redirect()->route('order-returns.show', $return)->with('status',
                "접수했습니다. 접수번호 {$return->receipt_no} — 일반 환불이라 창고에는 알리지 않습니다. "
                . '승인·결제취소 뒤 금액조정 주문을 생성합니다.');
        }

        $sent = $this->withworks->push($return->load('order.items'));

        return redirect()->route('order-returns.show', $return)
            ->with('status', $sent
                ? "접수했습니다. 접수번호 {$return->receipt_no} — 위드웍스에 전달했습니다."
                : "접수했습니다. 접수번호 {$return->receipt_no} — 위드웍스 전달은 실패했습니다.");
    }

    /**
     * 환자를 찾는다 — 검색 칸 옆 조회 창이 쓴다.
     *
     * 이름만 적어 넣게 두면 동명이인을 가릴 수 없다. 고르면 생년월일·전화번호가 함께
     * 채워져, 그다음 주문 조회가 한 사람으로 좁혀진다.
     */
    public function patientSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $kw = trim((string) $request->q);

        if (mb_strlen($kw) < 2) {
            return response()->json(['rows' => [], 'message' => '두 글자 이상 넣으십시오']);
        }

        $digits = preg_replace('/[^0-9]/', '', $kw);

        $rows = \App\Models\Patient::where(fn ($q) => $q
                ->where('name', 'like', "%{$kw}%")
                ->when($digits !== '', fn ($s) => $s
                    ->orWhere('mobile', 'like', "%{$digits}%")
                    ->orWhere('phone', 'like', "%{$digits}%")))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'birth_date', 'mobile', 'phone']);

        return response()->json([
            'rows' => $rows->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'birth' => $p->birth_date?->format('Y-m-d') ?? '',
                'phone' => $p->mobile ?: ($p->phone ?? ''),
            ])->values(),
        ]);
    }

    /**
     * 되돌릴 원 주문을 찾는다.
     *
     * 예전에는 최근 200건을 셀렉트에 통째로 부어 놓고 고르게 했다. 주문이 쌓이면
     * 찾을 수 없고, 200건 밖의 주문은 아예 고를 수 없다. 찾아서 고르게 한다.
     */
    public function orderSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $no    = trim((string) $request->order_no);
        $name  = trim((string) $request->patient_name);
        $birth = trim((string) $request->birth_date);
        $phone = preg_replace('/[^0-9]/', '', (string) $request->phone);

        /* 조건이 하나도 없으면 최근 것을 보여 준다. 빈 손으로 눌러도 무엇이 있는지는
           보여야 다음에 무엇을 칠지 정할 수 있다. */
        $orders = Order::with(['patient', 'items', 'prescription'])
            // 창고에 넘긴 건만 고를 수 있다 — 까닭은 Order::scopeSentToWarehouse 에 적어 두었다
            ->sentToWarehouse()
            ->when($no !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('order_number', 'like', "%{$no}%")
                ->orWhere('withworks_so_no', 'like', "%{$no}%")))
            /* 이름은 두 곳에 있다 — 환자로 맺어진 건은 patients 에, 아직 안 맺어진 건은
               처방전에 적힌 이름뿐이다. 한 쪽만 보면 스무네 가운데 스물네가 이름으로
               찾히지 않는다. */
            ->when($name !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$name}%"))
                ->orWhereHas('prescription', fn ($p) => $p
                    ->where('patient_name_ocr', 'like', "%{$name}%"))))
            ->when($birth !== '', fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p->whereDate('birth_date', $birth)))
            ->when($phone !== '', fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p
                    ->whereRaw("REPLACE(REPLACE(mobile,'-',''),' ','') LIKE ?", ["%{$phone}%"])
                    ->orWhereRaw("REPLACE(REPLACE(phone,'-',''),' ','') LIKE ?", ["%{$phone}%"])))
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'rows' => $orders->map(fn (Order $o) => [
                'id'       => $o->id,
                'order_no' => $o->order_number,
                /* 환자로 맺어지지 않은 건이 스물네 가운데 스물넷이다 — 그것을 전부 「-」로
                   보이면 무엇을 고르는지 알 수 없다. 처방전에 적힌 이름으로라도 채운다. */
                'patient'  => $o->patient?->name ?: ($o->prescription?->patient_name_ocr ?: '-'),
                'birth'    => $o->patient?->birth_date?->format('Y-m-d') ?? '',
                'phone'    => $o->patient?->mobile ?: ($o->patient?->phone ?? ''),
                'product'  => $o->product_name ?? '-',
                'amount'   => (int) $o->total_amount,
                'address'  => trim(($o->shipping_address ?? '')),
                'so_no'    => $o->withworks_so_no ?? '',
                'status'   => $o->status_label,
                'order_date' => $o->created_at?->format('Y-m-d') ?? '',
                /* 송장이 붙었는가 — 아직이면 되돌려 받을 물건이 없어 종류와 상관없이
                   판매주문 취소로 나간다. 접수하는 사람이 그것을 미리 알아야 한다. */
                'shipped'  => (bool) ($o->withworks_ship_no || $o->withworks_tracking_no),
                // 이미 접수한 적이 있으면 알려 준다 — 같은 주문을 두 번 접수하는 일이 있다
                'returns'  => $o->returns()->count(),
                /* 제품은 주문에 딸린 것을 그대로 준다. 품목 표가 비어 있는 옛 주문은
                   주문 자체에 적힌 대표 제품 한 줄로 대신한다 — 빈 표를 보여 주면
                   무엇을 되돌리는지 알 수 없다. */
                'items'    => $o->items->isNotEmpty()
                    ? $o->items->map(fn ($i) => [
                        'order_item_id' => $i->id,
                        'product_code' => $i->product_code ?? '',
                        'product_name' => $i->product_name ?? '',
                        'quantity'     => (int) $i->quantity,
                        'unit_price'   => (int) ($i->insurance_price ?: $i->product_price),
                        'copay'        => (int) $i->patient_copay,
                    ])->values()
                    : collect([[
                        'order_item_id' => null,
                        'product_code' => $o->product_code ?? '',
                        'product_name' => $o->product_name ?? '',
                        'quantity'     => (int) $o->quantity,
                        'unit_price'   => (int) $o->unit_price,
                        'copay'        => (int) $o->patient_copay,
                    ]]),
            ])->values(),
        ]);
    }

    /**
     * 환불을 실제로 처리한 자취를 적는다 (요청서 4쪽, 2026-08-31).
     *
     * 카드 취소 승인번호, 통장을 물린 날, 환불분 현금영수증 번호 — 팝빌과 토스 화면을
     * 보며 담당자가 옮겨 적는 값이다. 우리가 만들 수 없어 받아 적는 자리를 둔다.
     *
     * 단계는 여기서 건드리지 않는다. 그것은 advance() 가 절차서대로 옮긴다 —
     * 두 곳에서 옮기면 승인 없이 넘어가는 길이 생긴다.
     */
    public function update(Request $request, OrderReturn $orderReturn): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'card_issuer'              => 'nullable|string|max:50',
            // 카드번호는 받지 않는다 — 유효기간만으로는 결제할 수 없다
            'card_expiry'              => 'nullable|string|max:7',
            'refund_approval_no'       => 'nullable|string|max:50',
            'card_cancelled_at'        => 'nullable|date',
            'bank_cancelled_at'        => 'nullable|date',
            'handling_branch'          => 'nullable|string|max:100',
            'refund_agency'            => 'nullable|string|max:200',
            'refund_cash_receipt_no'   => 'nullable|string|max:50',
            'refund_cash_receipt_type' => ['nullable', Rule::in(array_keys(OrderReturn::REFUND_RECEIPT_TYPES))],
            'memo'                     => 'nullable|string|max:500',
            'staff_memo'               => 'nullable|string|max:500',
        ]);

        $orderReturn->update($data);

        return back()->with('success', '환불 정보를 적었습니다.');
    }

    /**
     * 어디까지 왔는지 환자에게 알린다 (요청서 4쪽 「접수자 → 환자 inform」).
     *
     * 창고 사건마다 저절로 보내지 않는다. 밖으로 나가는 말이라 무를 수 없고, 검수중ㆍ
     * 입고중처럼 환자가 알 까닭이 없는 걸음도 있다 — 무엇을 알릴지는 접수자가 정한다.
     */
    public function notifyPatient(Request $request, OrderReturn $orderReturn,
                                  \App\Services\ReturnPatientNotice $notice): RedirectResponse
    {
        $data = $request->validate(['extra' => 'nullable|string|max:200']);

        $out = $notice->send($orderReturn, $data['extra'] ?? null);

        return $out['sent']
            ? back()->with('status', $out['message'])
            : back()->withErrors(['extra' => $out['message']]);
    }

    public function show(OrderReturn $orderReturn): View
    {
        $orderReturn->load([
            'order.patient', 'order.items', 'items', 'logs.creator',
            'assignee', 'creator', 'approver', 'inspectConfirmer',
        ]);

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

        $to = $data['to_status'];

        if (!in_array($to, $orderReturn->nextStatuses(), true)) {
            return back()->withErrors(['to_status' => '지금 상태에서 갈 수 없는 단계입니다.']);
        }

        /* 검수 확정과 전자 승인은 승인 권한이 있어야 누른다. 절차서가 승인자를 따로
           두라고 했는데 아무나 누를 수 있으면 그 줄을 둔 뜻이 없다. */
        if (OrderReturn::needsApproval($to) && !perm('order-returns', 'approve')) {
            return back()->withErrors(['to_status' =>
                OrderReturn::STATUS_LABELS[$to] . '은(는) 승인 권한이 있어야 누를 수 있습니다 ('
                . $orderReturn->approverRole() . ').']);
        }

        DB::transaction(function () use ($orderReturn, $data, $to) {
            OrderReturnLog::create([
                'order_return_id' => $orderReturn->id,
                'from_status'     => $orderReturn->status,
                'to_status'       => $to,
                'reason'          => $data['reason'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            $fill = ['status' => $to];

            /* 단계마다 「누가 언제」를 따로 남긴다. 이력만으로도 읽히지만, 승인·검수는
               다른 화면과 문서가 곧바로 찾아 쓰는 값이라 칸으로 둔다. */
            match ($to) {
                // 창고에 물건이 들어온 때 — 검수 2영업일·출고 3영업일을 여기서부터 센다
                'inspecting'      => $fill['arrived_at'] = $orderReturn->arrived_at ?? now(),
                'inspected'       => $fill = $fill + [
                    'inspect_confirmed_by' => Auth::id(),
                    'inspect_confirmed_at' => now(),
                ],
                'approved'        => $fill = $fill + ['approved_by' => Auth::id(), 'approved_at' => now()],
                'payment_checked' => $fill['payment_checked_at'] = now(),
                'order_confirmed' => $fill['order_confirmed_at'] = now(),
                'refunded'        => $fill['refunded_at'] = $orderReturn->refunded_at ?? now(),
                default           => null,
            };

            $orderReturn->update($fill);

            /* 반품·취소가 끝나면 원 주문도 취소된 것이다. 주문 목록에 그대로 살아 있으면
               정산·청구가 그 주문을 계속 셈에 넣는다.
               부분은 다르다 — 남는 수량이 있어 주문 자체는 살아 있다. */
            if ($to === 'refunded' && !$orderReturn->is_partial
                && in_array($orderReturn->type, [OrderReturn::TYPE_RETURN, OrderReturn::TYPE_CANCEL], true)) {
                $orderReturn->order?->update(['status' => 'cancelled']);
            }
        });

        /* 창고에도 알린다. 반품 주문을 세우지 않은 건(출고 전 취소)은 알릴 곳이 없어
           그냥 지나간다. 실패해도 우리 쪽 단계는 이미 옮겼다 — 되돌리면 담당자가 한 일이
           사라진다. 로그에만 남긴다. */
        $this->withworks->pushStatus($orderReturn);

        /* 절차서의 「접수자 → 팀장님 승인요청」(요청서 4쪽). 다음 걸음이 승인이면
           그때가 곧 요청이다 — 접수자가 따로 부탁하게 두면 잊는다.
           알람과 채팅을 함께, 승인할 수 있는 사람에게만 보낸다(2026-08-31 회신). */
        app(\App\Services\ReturnNotice::class)->askApproval($orderReturn->fresh());

        $extra = '';

        /* 금액조정·마이너스 발행은 단계에 딸린 일이라 여기서 함께 한다. 사람이 단추를
           한 번 더 눌러야 하면 잊고 넘어가 결국 돈만 안 맞는다.
           실패해도 단계는 이미 옮겼다 — 왜 안 됐는지를 화면에 띄우고 다시 누르게 둔다. */
        if ($to === 'adjusted') {
            $extra = $this->settlement->adjust($orderReturn->fresh(['order.patient', 'items']))
                ? ' 금액조정 주문을 세웠습니다.'
                : ' 금액조정 주문을 생성하지 못했습니다 — 상세에서 다시 시도해 주십시오.';
        }

        if ($to === 'credited') {
            $out   = $this->settlement->credit($orderReturn->fresh(['order', 'items']));
            $extra = ' ' . $out['note'];
        }

        return back()->with('status', '상태를 옮겼습니다.' . $extra);
    }

    /**
     * 창고에 다시 알린다.
     *
     * 접수할 때 못 보냈으면(연동이 꺼져 있었거나 창고가 거절했거나) 여기서 다시 보낸다.
     * 사람이 눌러야 하는 이유는, 실패한 까닭을 먼저 읽고 고쳐야 하기 때문이다.
     */
    public function resend(OrderReturn $orderReturn): RedirectResponse
    {
        $sent = $this->withworks->push($orderReturn->load('order.items'));

        return back()->with('status', $sent
            ? '위드웍스에 전달했습니다.'
            : '전달하지 못했습니다: ' . ($orderReturn->fresh()->withworks_error ?: '알 수 없는 오류'));
    }

    /**
     * 3PL 검수 결과를 위드웍스에서 받아 온다.
     *
     * 검수는 창고가 한다. 그 결과를 눈으로 옮겨 적게 두면 잘못 적히고, 언제 받은
     * 것인지도 남지 않는다. 받아 온 뒤 확정은 사람이 누른다 — Care team manager 몫이다.
     */
    public function pullInspection(OrderReturn $orderReturn): RedirectResponse
    {
        $r = $this->withworks->pull($orderReturn);

        if ($r === null) {
            return back()->withErrors(['withworks' =>
                '창고에서 받아 오지 못했습니다 — 반품 주문이 아직 서지 않았거나 연동이 꺼져 있습니다.']);
        }

        return back()->with('status', '창고 검수 결과를 받았습니다: '
            . ($orderReturn->fresh()->withworks_status_label ?: '상태 없음'));
    }

    /**
     * 마이너스 발행을 다시 시도한다.
     *
     * 단계를 옮길 때 함께 돌지만 팝빌이 거절하는 일이 있다. 그때 단계를 되돌려 다시
     * 밟게 하면 이력이 지저분해진다 — 발행만 다시 누르게 둔다.
     *
     * ⚠ 국세청 신고까지 가는 동작이다. 화면에서 한 번 더 묻는다.
     */
    public function issueCredit(OrderReturn $orderReturn): RedirectResponse
    {
        $out = $this->settlement->credit($orderReturn->load(['order', 'items']));

        return $out['ok']
            ? back()->with('status', $out['note'])
            : back()->withErrors(['credit' => $out['note']]);
    }
}
