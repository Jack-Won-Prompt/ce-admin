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

        $orders = $query->paginate(10)->withQueryString();

        $statusCounts = ShopOrder::selectRaw('status, count(*) as cnt')
            ->groupBy('status')->pluck('cnt', 'status');

        return view('shop-orders.index', compact('orders', 'statusCounts'));
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
