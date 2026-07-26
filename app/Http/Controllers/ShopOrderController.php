<?php

namespace App\Http\Controllers;

use App\Models\ShopOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class ShopOrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = ShopOrder::latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn ($s) =>
                $s->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_name', 'like', "%{$q}%")
                  ->orWhere('customer_company', 'like', "%{$q}%")
            );
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $gridData = $query->get()->map(function (ShopOrder $o) {
            $items       = is_array($o->items) ? $o->items : [];
            $firstName   = $items[0]['product_name'] ?? '';
            $product     = $firstName;
            if (count($items) > 1) {
                $product = trim($firstName . ' +' . (count($items) - 1));
            }

            return [
                'id'        => $o->id,
                'order_no'  => $o->order_number ?? '',
                'created'   => $o->created_at?->format('Y-m-d H:i') ?? '',
                'customer'  => $o->customer_name ?? '',
                'company'   => $o->customer_company ?? '',
                'product'   => $product,
                'amount'    => (int) $o->total_amount,
                'delivery'  => $o->delivery_method === 'quick' ? '퀵' : '택배',
                'status'    => $o->statusLabel(),
                'withworks' => $o->withworks_so_no ?? '-',
            ];
        });

        $total = $gridData->count();

        $statusCounts = ShopOrder::selectRaw('status, count(*) as cnt')
            ->groupBy('status')->pluck('cnt', 'status');

        return view('shop-orders.index', compact('gridData', 'total', 'statusCounts'));
    }

    public function show(ShopOrder $shopOrder): View
    {
        $withworksStatus = null;
        if ($shopOrder->withworks_so_no) {
            $baseUrl = rtrim(config('services.todoworks.api_url', ''), '/');
            $token   = config('services.todoworks.token');
            if ($baseUrl && $token) {
                try {
                    $res = Http::withToken($token)->timeout(5)
                        ->get("{$baseUrl}/api/v1/ce-admin/so_show", [
                            'ce_order_number' => $shopOrder->order_number,
                        ]);
                    if ($res->successful() && ($res->json('success') ?? false)) {
                        $withworksStatus = $res->json('result');
                    }
                } catch (\Throwable) {}
            }
        }
        return view('shop-orders.show', compact('shopOrder', 'withworksStatus'));
    }

    public function updateStatus(Request $request, ShopOrder $shopOrder): \Illuminate\Http\JsonResponse
    {
        $request->validate(['status' => 'required|in:confirmed,processing,shipped,delivered,cancelled']);
        $shopOrder->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    public function updateMemo(Request $request, ShopOrder $shopOrder): \Illuminate\Http\JsonResponse
    {
        $request->validate(['admin_memo' => 'nullable|string|max:1000']);
        $shopOrder->update(['admin_memo' => $request->admin_memo]);
        return response()->json(['success' => true]);
    }
}
