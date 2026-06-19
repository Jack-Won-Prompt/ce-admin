<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $onlineThreshold = \Carbon\Carbon::now('UTC')->subMinutes(5);

        $sessions = DB::table('shop_user_sessions')
            ->orderByDesc('last_activity_at')
            ->get()
            ->map(function ($s) use ($onlineThreshold) {
                $s->online = $s->last_activity_at
                    && \Carbon\Carbon::parse($s->last_activity_at, 'UTC') >= $onlineThreshold;
                return $s;
            });

        $query = DB::table('shop_product_logs')->orderByDesc('created_at');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('shop_user_name', 'like', "%{$q}%")
                  ->orWhere('shop_user_email', 'like', "%{$q}%")
                  ->orWhere('product_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('shop_user_id', $request->user_id);
        }

        $logs = $query->paginate(50)->withQueryString();

        $todayUtc = \Carbon\Carbon::now('UTC')->toDateString();
        $todayCount = DB::table('shop_product_logs')
            ->whereDate('created_at', $todayUtc)->count();

        return view('shop-monitoring.index', compact('sessions', 'logs', 'todayCount'));
    }
}
