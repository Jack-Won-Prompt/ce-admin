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
        $query = OrderReturn::with(['order.patient', 'order.prescription.billingOffice', 'order.tossPayment', 'assignee',
                                    'order.items.lots', 'order.operationUser', 'order.tossPayment', 'items',
                                    'creator', 'approver'])->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        /* 승인 대기 묶음 — 지금 서 있는 걸음의 **다음**이 승인인 건들이다.
           상태 거르개만 있을 때는 「검수 확정」과 「전자 승인」을 따로 곱라 보아야
           했다. 승인할 사람은 「내가 누를 것」을 한 번에 보길 원한다. */
        if ($request->boolean('pending')) {
            $query->whereIn('status', OrderReturn::awaitingCandidates());
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('receipt_no', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows = $query->get();

        /* 걸러내는 것은 줄마다 본다 — 같은 「접수」라도 자격 변경만 다음이
           승인이고, 나머지는 수거부터다. 상태 이름만으로는 갈라지지 않는다. */
        if ($request->boolean('pending')) {
            $rows = $rows->filter(fn (OrderReturn $r) => $r->awaitsApproval())->values();
        }

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
            /* 창고가 실물을 보고 적은 말. 목록에서는 있다·없다만 보이면 된다 —
               읽는 자리는 상세다. 있는데 아무 표가 없으면 열어 볼 까닭을 모른다. */
            'pl3_note'  => $r->pl3_note ? '있음' : '',
            'partial'   => $r->is_partial ? '부분' : '전체',
            // 늦은 건은 눈에 띄어야 한다 — 묻히면 절차서의 기한을 둔 뜻이 없다
            'overdue'   => ($o = $r->overdue()) ? "{$o[0]} {$o[1]}일 초과" : '',
            'order_no'  => $r->order?->order_number ?? '-',
            // 창고와 맞춰 볼 때 쓰는 번호 — 없으면 아직 알리지 못한 것이다
            'origin_so' => $r->order?->withworks_so_no ?: '-',
            'return_so' => $r->withworks_so_no ?: ($r->withworks_error ? '실패' : '해당없음'),
            'patient'   => $r->order?->patient?->name ?? '-',
            'reason'    => OrderReturn::reasonLabel($r->reason_code),
            'refund'    => $r->refund_amount ? number_format($r->refund_amount) : '-',
            /* 승인 팝오버가 읽는다 — 승인하기 전에 무엇을 승인하는지를 보여 준다.
               목록의 값은 사람이 읽는 꼴(리 넣은 글)이라, 셀할 수 있는 숫자를 따로 싣는다. */
            'ap_next'      => (function () use ($r) {
                $to = collect($r->nextStatuses())->first(fn ($x) => OrderReturn::needsApproval($x));
                return $to ? (OrderReturn::STATUS_LABELS[$to] ?? $to) : '';
            })(),
            'ap_role'      => $r->approverRole(),
            'ap_order_amt' => (int) ($r->order?->total_amount ?? 0),
            'ap_copay'     => (int) ($r->order?->patient_copay ?? 0),
            'ap_adjust'    => $r->needsAdjust() ? (int) ($r->adjust_amount ?? $r->adjustedAmount() ?? 0) : null,
            'ap_adjust_dir'=> $r->needsAdjust()
                ? (OrderReturn::ADJ_DIRECTIONS[$r->adjust_direction ?? OrderReturn::ADJ_REFUND] ?? '') : '',
            'ap_saved'     => $r->adjust_amount !== null,

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
            /* 승인은 「언제」가 곧 근거다. 날짜만 적어 두면 같은 날 두 번 오간 건을
               가릴 수 없고, 승인 앞뒤로 무엇이 있었는지도 맞춰 볼 수 없다.
               시ㆍ분ㆍ초까지 적는다 (2026-09-07 지시). */
            'approved_at'  => $r->approved_at?->format('Y-m-d H:i:s') ?? '',
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

        /* 승인을 기다리는 건은 찾는 조건과 상관없이 세어 둔다 — 거르고 있는 중에도
           쓸 일이 몇 건인지는 보여야 한다. */
        $pendingCount = OrderReturn::whereIn('status', OrderReturn::awaitingCandidates())
            ->get()->filter(fn (OrderReturn $r) => $r->awaitsApproval())->count();

        return view('order-returns.index', [
            'gridData' => $gridData,
            'total'    => $gridData->count(),
            'counts'   => $counts,
            'lateCount' => $lateCount,
            'pendingCount' => $pendingCount,
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
                        // 공단에 청구할 때 쓰는 번호 — 되돌린 뒤 다시 청구할 때 필요하다
                        'device_code'  => \App\Support\DeviceCode::for($i->product_code) ?? '',
                        'product_name' => $i->product_name ?? '',
                        'quantity'     => (int) $i->quantity,
                        'unit_price'   => (int) ($i->insurance_price ?: $i->product_price),
                        'copay'        => (int) $i->patient_copay,
                    ])->values()
                    : collect([[
                        'order_item_id' => null,
                        'product_code' => $o->product_code ?? '',
                        'device_code'  => \App\Support\DeviceCode::for($o->product_code) ?? '',
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
     * 목록에서 고른 건을 한 번에 승인한다.
     *
     * 승인할 사람은 하루에 여러 건을 본다. 한 건씩 열어 진행 단계 탭까지 들어가
     * 누르게 두면, 스무 건이면 스무 번을 오간다. 목록에서 골라 한 번에 누른다.
     *
     * 어느 걸음으로 가는지는 건마다 다르다 — 검수 확정을 기다리는 건도 있고 반품
     * 승인을 기다리는 건도 있다. 그 건의 다음 걸음 가운데 승인인 것을 찾아 옮긴다.
     * 기다리지 않는 건이 섞여 들어오면 건너뛰고 몇 건이 그랬는지 말해 준다.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        if (!perm('order-returns', 'approve')) {
            return back()->withErrors(['bulk' => '승인 권한이 있어야 누를 수 있습니다.']);
        }

        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rows = OrderReturn::whereIn('id', $data['ids'])->get();

        $done = 0;
        $skipped = [];

        foreach ($rows as $r) {
            $to = collect($r->nextStatuses())->first(fn ($s) => OrderReturn::needsApproval($s));

            if (!$to) {
                $skipped[] = $r->receipt_no;
                continue;
            }

            $this->applyTransition($r, $to, $data['reason'] ?? null);
            $done++;
        }

        $말 = $done . '건을 승인했습니다.';
        if ($skipped) {
            $말 .= ' 승인을 기다리지 않는 ' . count($skipped) . '건은 건너뛰었습니다 — '
                 . implode(', ', array_slice($skipped, 0, 5))
                 . (count($skipped) > 5 ? ' 외' : '') . '.';
        }

        return back()->with('status', $말);
    }

    /**
     * 걸음 하나를 옮긴다 — 화면에서 누르는 길과 목록에서 한 번에 누르는 길이 함께 쓴다.
     *
     * 막는 일(흐름·권한·조정 금액)은 부르는 쪽이 이미 가렸다. 여기서는 옮기고,
     * 자취를 남기고, 창고와 승인자에게 알린다.
     */
    private function applyTransition(OrderReturn $orderReturn, string $to, ?string $reason): void
    {
        DB::transaction(function () use ($orderReturn, $to, $reason) {
            OrderReturnLog::create([
                'order_return_id' => $orderReturn->id,
                'from_status'     => $orderReturn->status,
                'to_status'       => $to,
                'reason'          => $reason,
                'created_by'      => Auth::id(),
            ]);

            $fill = ['status' => $to];

            match ($to) {
                'inspected' => $fill = $fill + [
                    'inspect_confirmed_by' => Auth::id(),
                    'inspect_confirmed_at' => now(),
                ],
                'approved'  => $fill = $fill + ['approved_by' => Auth::id(), 'approved_at' => now()],
                default     => null,
            };

            $orderReturn->update($fill);
        });

        $this->withworks->pushStatus($orderReturn);

        app(\App\Services\ReturnNotice::class)->askApproval($orderReturn->fresh());
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

        /* 조정 금액을 적지 않고 금액조정으로 넘어가면, 얼마로 조정한 것인지 아무 데도
           남지 않는다. 창고에 세우는 조정 주문의 적요에도 빈칸이 가고, 그 뒤로는
           원 주문과 반품 줄을 놓고 다시 셈하는 수밖에 없다(2026-09-02 유형표). */
        /* 0 원도 적지 않은 것으로 본다 (2026-09-11 고침). 조정 방향은 환불이거나
           추가 입금인데, 그 금액이 0 이면 어느 쪽도 아니다 — 조정할 것이 없다. */
        if ($to === 'adjusted' && ! (int) $orderReturn->adjust_amount) {
            return back()->withErrors(['to_status' =>
                '조정 금액을 먼저 적어 주십시오 — 아래 「금액조정」 칸에 얼마를 돌려주는지(또는 더 받는지) 적고 저장합니다.']);
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
               부분은 다르다 — 남는 수량이 있어 주문 자체는 살아 있다.

               자격 변경도 다르다. 물건은 환자에게 그대로 있고 되돌려 받지 않는다 —
               바뀐 것은 누가 얼마를 내는가뿐이라, 그 주문으로 **다시 청구해야** 한다.
               취소로 적으면 정산·청구가 셈에서 빼버려 새 자격의 청구가 영영 안 나간다
               (3차 4회 13번에서 실제로 그렇게 되었다). */
            if ($to === 'refunded' && !$orderReturn->is_partial
                && $orderReturn->scenario() !== OrderReturn::SC_REFUND_ONLY
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
            /* 안 된 까닭을 그대로 보여 준다. 예전에는 「다시 시도해 주십시오」만
               띄웠는데, 까닭이 창고가 거절한 것이면 몇 번을 눌러도 같은 자리다 —
               까닭은 상세의 적요에만 적혀 아무도 읽지 않았다(3차 4회 13번). */
            $extra = $this->settlement->adjust($orderReturn->fresh(['order.patient', 'items']))
                ? ' 금액조정 주문을 세웠습니다.'
                : ' 금액조정 주문을 생성하지 못했습니다 — ' . ($orderReturn->fresh()->credit_note ?: '사유를 알 수 없습니다') . '.';
        }

        if ($to === 'credited') {
            $out   = $this->settlement->credit($orderReturn->fresh(['order', 'items']));
            $extra = ' ' . $out['note'];
        }

        return back()->with('status', '상태를 옮겼습니다.' . $extra);
    }

    /**
     * 부분ㆍ자격 변경 건의 조정 금액을 적는다(2026-09-02 유형표).
     *
     * 「조정 필요」라 적힌 셋 — 부분 교환ㆍ부분 반품ㆍ자격 변경 — 은 되돌린 뒤에도
     * 돈이 남는다. 얼마가 남는지를 여기 적지 않으면 청구할 사람이 원 주문과 반품
     * 줄을 놓고 다시 셈해야 하고, 그 셈이 사람마다 달라진다.
     *
     * 방향도 함께 적는다. 자격 변경은 돌려주기도 하고 더 받기도 한다 — 일반에서
     * 차상위경감으로 바뀌면 돌려주고, 반대로 바뀌면 더 받는다.
     */
    public function adjustAmount(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        if (! $orderReturn->needsAdjust()) {
            return back()->withErrors(['adjust' => '조정할 것이 없는 건입니다 — 전부를 되돌리는 건은 발행을 통째로 무릅니다.']);
        }

        /* 0 원은 받지 않는다 (2026-09-11 고침). 여태 min:0 이라 0 이 저장됐고, 단계를
           옮기는 자리에서는 「적지 않은 것」과 갈리지 않아 그대로 지나갔다. */
        $data = $request->validate([
            'adjust_amount'    => ['required', 'integer', 'min:1', 'max:100000000'],
            'adjust_direction' => ['required', Rule::in(array_keys(OrderReturn::ADJ_DIRECTIONS))],
        ], [
            'adjust_amount.min' => '조정 금액은 1원 이상이어야 합니다 — 돌려주거나 더 받을 것이 없으면 이 단계를 밟지 않습니다.',
        ]);

        $orderReturn->update($data);

        activity()->causedBy(Auth::user())->performedOn($orderReturn->order)
            ->log(sprintf('조정 금액 %s %s원 (%s)',
                OrderReturn::ADJ_DIRECTIONS[$data['adjust_direction']],
                number_format($data['adjust_amount']),
                $orderReturn->receipt_no));

        return back()->with('status', sprintf('조정 금액을 적었습니다 — %s %s원.',
            OrderReturn::ADJ_DIRECTIONS[$data['adjust_direction']],
            number_format($data['adjust_amount'])));
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
    /**
     * 금액조정 주문을 다시 세운다.
     *
     * 창고가 거절하면 「상세에서 다시 시도해 주십시오」라 말해 놓고, 정작 상세에는
     * 그 단추가 없었다 — 걸음은 이미 「금액조정」으로 옮겨져 있어 다시 누를 자리도
     * 없었다. 담당자는 조정 주문 없이 다음으로 넘어갈 수밖에 없었다(3차 4회 13번).
     */
    public function retryAdjust(OrderReturn $orderReturn): RedirectResponse
    {
        if (! perm('order-returns', 'send')) {
            return back()->withErrors(['adjust' => '창고로 보낼 권한이 있어야 누를 수 있습니다.']);
        }

        $ok = $this->settlement->adjust($orderReturn->fresh(['order.patient', 'items']));

        return $ok
            ? back()->with('status', '금액조정 주문을 세웠습니다 — ' . ($orderReturn->fresh()->adjust_so_no ?: '창고 번호는 곧 들어옵니다') . '.')
            : back()->withErrors(['adjust' => $orderReturn->fresh()->credit_note ?: '금액조정 주문을 세우지 못했습니다.']);
    }

    public function issueCredit(OrderReturn $orderReturn): RedirectResponse
    {
        $out = $this->settlement->credit($orderReturn->load(['order', 'items']));

        return $out['ok']
            ? back()->with('status', $out['note'])
            : back()->withErrors(['credit' => $out['note']]);
    }
}
