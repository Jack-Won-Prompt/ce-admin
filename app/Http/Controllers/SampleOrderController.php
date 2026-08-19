<?php

namespace App\Http\Controllers;

use App\Models\SampleOrder;
use App\Models\SampleOrderItem;
use App\Services\WithworksSampleOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CE 샘플주문.
 *
 * 목록·상세·신규를 한 화면의 탭으로 둔다. 샘플은 한 건을 보다가 곧바로 다음 건을
 * 만드는 일이 잦아, 화면을 건너다니면 하던 일이 끊긴다.
 */
class SampleOrderController extends Controller
{
    public function __construct(private readonly WithworksSampleOrders $withworks) {}

    public function index(Request $request): View
    {
        $query = SampleOrder::with(['creator', 'patient'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('sample_no', 'like', "%{$kw}%")
                ->orWhere('account_name', 'like', "%{$kw}%")
                ->orWhere('recipient_name', 'like', "%{$kw}%")
                ->orWhere('withworks_so_no', 'like', "%{$kw}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        $rows = $query->get();

        $gridData = $rows->map(fn (SampleOrder $s) => [
            'id'        => $s->id,
            'sample_no' => $s->sample_no,
            'status'    => $s->statusLabel(),
            // 거래처는 고객이다. 환자로 등록된 사람이면 그 이름을, 아니면 적어 둔 이름을 쓴다.
            'customer'  => $s->patient?->name ?: ($s->account_name ?: '-'),
            'recipient' => $s->recipient_name ?: '-',
            'mobile'    => $s->mobile ?: '-',
            'address'   => trim(($s->address ?? '') . ' ' . ($s->address_detail ?? '')) ?: '-',
            'qty'       => (int) $s->total_qty,
            'amount'    => (int) $s->total_amount,
            'so_no'     => $s->withworks_so_no ?: ($s->withworks_error ? '실패' : '미전달'),
            'order_date'=> $s->order_date?->format('Y-m-d') ?? '',
            'creator'   => $s->creator?->name ?? '-',
        ])->values();

        $counts = SampleOrder::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('sample-orders.index', [
            'gridData' => $gridData,
            'total'    => $gridData->count(),
            'counts'   => $counts,
        ]);
    }

    /**
     * 샘플을 받을 고객을 찾는다.
     *
     * 이름만 적어 두면 같은 사람을 두 번 적을 때 갈리고, 이 사람에게 몇 번 보냈는지
     * 셀 수 없다. 환자를 걸어 두되, 등록되지 않은 사람에게 보내는 일이 있어 직접
     * 적는 길도 남긴다.
     */
    public function customerSearch(Request $request): JsonResponse
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
            ->get(['id', 'name', 'mobile', 'phone', 'address']);

        return response()->json([
            'rows' => $rows->map(fn ($p) => [
                'id'      => $p->id,
                'name'    => $p->name,
                'mobile'  => $p->mobile ?: $p->phone ?: '',
                'address' => $p->address ?: '',
            ])->values(),
        ]);
    }

    /**
     * 요청자 조회 — CE-Admin 에 등록된 담당자.
     *
     * 샘플은 대개 영업 담당자가 달라고 하고 사무실에서 대신 넣는다. 이름을 손으로
     * 적게 두면 같은 사람이 여러 이름으로 남아 나중에 누구 것인지 셀 수 없다.
     * 쉬는 계정은 빼고 준다 — 지금 일하는 사람만 고를 수 있어야 한다.
     */
    public function userSearch(Request $request): JsonResponse
    {
        $kw = trim((string) $request->q);

        $rows = \App\Models\User::query()
            ->where('is_active', true)
            ->when($kw !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$kw}%")
                ->orWhere('email', 'like', "%{$kw}%")
                ->orWhere('phone', 'like', '%' . preg_replace('/[^0-9]/', '', $kw) . '%')))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'phone', 'role']);

        return response()->json([
            'rows' => $rows->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email ?: '',
                'phone' => $u->phone ?: '',
                'role'  => $u->role === 'admin' ? '관리자' : '담당자',
            ])->values(),
        ]);
    }

    /** 상세 — 머리 정보와 제품 목록을 함께 준다. 화면이 탭 안에서 그린다. */
    public function show(SampleOrder $sampleOrder): JsonResponse
    {
        $sampleOrder->load(['items', 'creator', 'patient', 'requester']);

        return response()->json([
            'head' => [
                'id'          => $sampleOrder->id,
                'sample_no'   => $sampleOrder->sample_no,
                'type'        => $sampleOrder->typeLabel(),
                'status'      => $sampleOrder->statusLabel(),
                'status_badge'=> $sampleOrder->statusBadge(),
                'customer'    => $sampleOrder->patient?->name ?: ($sampleOrder->account_name ?: '-'),
                'customer_kind'=> $sampleOrder->patient_id ? '환자' : '직접 입력',
                'recipient'   => $sampleOrder->recipient_name ?: '-',
                'mobile'      => $sampleOrder->mobile ?: '-',
                'address'     => trim(($sampleOrder->address ?? '') . ' ' . ($sampleOrder->address_detail ?? '')) ?: '-',
                'order_date'  => $sampleOrder->order_date?->format('Y-m-d') ?? '',
                'delivery_date' => $sampleOrder->delivery_date?->format('Y-m-d') ?? '',
                'purpose'     => $sampleOrder->purpose ?: '-',
                'note'        => $sampleOrder->note ?: '-',
                'so_no'       => $sampleOrder->withworks_so_no ?: '',
                'so_status'   => $sampleOrder->withworks_status_label ?: '',
                'error'       => $sampleOrder->withworks_error ?: '',
                'creator'     => $sampleOrder->creator?->name ?? '-',
                // 계정이 지워졌어도 그때 누구였는지는 적어 둔 이름으로 남는다
                'requester'   => $sampleOrder->requester?->name ?: ($sampleOrder->requester_name ?: '-'),
                'total_qty'   => (int) $sampleOrder->total_qty,
                'total_amount'=> (int) $sampleOrder->total_amount,
            ],
            'items' => $sampleOrder->items->map(fn (SampleOrderItem $i) => [
                'product_code' => $i->product_code,
                'product_name' => $i->product_name,
                'quantity'     => (int) $i->quantity,
                'unit_price'   => (int) $i->unit_price,
                'amount'       => (int) $i->amount,
            ])->values(),
        ]);
    }

    /**
     * 창고에 다시 알린다.
     *
     * 등록할 때 못 보냈으면 여기서 다시 보낸다. 사람이 눌러야 하는 이유는, 실패한
     * 까닭을 먼저 읽고 고쳐야 하기 때문이다.
     */
    public function resend(SampleOrder $sampleOrder): JsonResponse
    {
        $sent = $this->withworks->push($sampleOrder->load('items'));

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? '위드웍스에 전달했습니다.'
                : '전달하지 못했습니다: ' . ($sampleOrder->fresh()->withworks_error ?: '알 수 없는 까닭'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id'           => 'nullable|exists:patients,id',
            'requester_id'         => 'nullable|exists:users,id',
            'requester_name'       => 'nullable|string|max:100',
            'account_name'         => 'nullable|string|max:100',
            'recipient_name'       => 'required|string|max:100',
            'mobile'               => 'nullable|string|max:30',
            'postcode'             => 'nullable|string|max:10',
            'address'              => 'required|string|max:300',
            'address_detail'       => 'nullable|string|max:200',
            'order_date'           => 'required|date',
            'delivery_date'        => 'nullable|date',
            'purpose'              => 'nullable|string|max:200',
            'note'                 => 'nullable|string|max:500',
            'items'                => 'required|array|min:1',
            'items.*.product_code' => 'required|string|max:50',
            'items.*.product_name' => 'required|string|max:200',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'nullable|integer|min:0',
        ]);

        $sample = DB::transaction(function () use ($data) {
            $sample = SampleOrder::create(array_merge(
                collect($data)->except('items')->all(),
                [
                    'sample_no'  => SampleOrder::generateNo(),
                    // 유형은 하나뿐이라 고르게 하지 않는다 — 고르는 칸이 있으면 틀린 값이 들어올 자리가 생긴다
                    'type'       => SampleOrder::TYPE_SALE,
                    'status'     => 'draft',
                    'created_by' => Auth::id(),
                ]
            ));

            $qty = 0;
            $sum = 0;
            foreach ($data['items'] as $i => $item) {
                $q     = (int) $item['quantity'];
                $price = (int) ($item['unit_price'] ?? 0);
                $qty  += $q;
                $sum  += $q * $price;

                $sample->items()->create([
                    'product_code' => $item['product_code'],
                    'product_name' => $item['product_name'],
                    'quantity'     => $q,
                    'unit_price'   => $price,
                    'amount'       => $q * $price,
                    'sort_order'   => $i,
                ]);
            }

            $sample->update(['total_qty' => $qty, 'total_amount' => $sum]);

            return $sample;
        });

        /* 창고에 알린다. 실패해도 등록은 살려 둔다 — 창고에 알리지 못한 것과 등록하지
           못한 것은 다른 일이다. 못 간 까닭은 화면에 남아 다시 보낼 수 있다. */
        $sent = $this->withworks->push($sample->load('items'));

        return response()->json([
            'success'   => true,
            'id'        => $sample->id,
            'sample_no' => $sample->sample_no,
            'message'   => $sent
                ? "등록했습니다. 번호 {$sample->sample_no} — 위드웍스에 전달했습니다."
                : "등록했습니다. 번호 {$sample->sample_no} — 위드웍스 전달은 실패했습니다.",
        ]);
    }
}
