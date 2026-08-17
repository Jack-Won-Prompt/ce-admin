<?php

namespace App\Http\Controllers;

use App\Models\SampleOrder;
use App\Models\SampleOrderItem;
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
    public function index(Request $request): View
    {
        $query = SampleOrder::with('creator')->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('sample_no', 'like', "%{$kw}%")
                ->orWhere('account_name', 'like', "%{$kw}%")
                ->orWhere('recipient_name', 'like', "%{$kw}%")
                ->orWhere('withworks_so_no', 'like', "%{$kw}%"));
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
            'type'      => SampleOrder::TYPE_SHORT[$s->type] ?? $s->type,
            'status'    => $s->statusLabel(),
            'account'   => $s->account_name ?: '-',
            'recipient' => $s->recipient_name ?: '-',
            'mobile'    => $s->mobile ?: '-',
            'address'   => trim(($s->address ?? '') . ' ' . ($s->address_detail ?? '')) ?: '-',
            'qty'       => (int) $s->total_qty,
            'amount'    => (int) $s->total_amount,
            'so_no'     => $s->withworks_so_no ?: ($s->withworks_error ? '실패' : '미전달'),
            'order_date'=> $s->order_date?->format('Y-m-d') ?? '',
            'creator'   => $s->creator?->name ?? '-',
        ])->values();

        $counts = SampleOrder::selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        return view('sample-orders.index', [
            'gridData' => $gridData,
            'total'    => $gridData->count(),
            'counts'   => $counts,
        ]);
    }

    /** 상세 — 머리 정보와 제품 목록을 함께 준다. 화면이 탭 안에서 그린다. */
    public function show(SampleOrder $sampleOrder): JsonResponse
    {
        $sampleOrder->load(['items', 'creator']);

        return response()->json([
            'head' => [
                'id'          => $sampleOrder->id,
                'sample_no'   => $sampleOrder->sample_no,
                'type'        => $sampleOrder->typeLabel(),
                'status'      => $sampleOrder->statusLabel(),
                'status_badge'=> $sampleOrder->statusBadge(),
                'account'     => $sampleOrder->account_name ?: '-',
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'                 => 'required|string|in:' . implode(',', array_keys(SampleOrder::TYPES)),
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

        return response()->json([
            'success'   => true,
            'id'        => $sample->id,
            'sample_no' => $sample->sample_no,
            'message'   => "등록했습니다. 번호 {$sample->sample_no}",
        ]);
    }
}
