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

        // ── wwGrid용 매핑 (배지→텍스트, 날짜→KST 포맷, null 안전) ──
        $roleMap = [
            'super_admin'      => '슈퍼관리자',
            'operations_admin' => '운영관리자',
            'company_admin'    => '회사관리자',
            'approver'         => '승인자',
            'caregiver'        => '보호자',
            'patient'          => '환자',
        ];

        $fmtKst = function ($value, $format) {
            if (! $value) {
                return '-';
            }
            return \Carbon\Carbon::parse($value, 'UTC')->setTimezone('Asia/Seoul')->format($format);
        };

        // 사용자 로그인 현황 그리드
        $loginStatusGrid = $sessions->map(fn ($s) => [
            'status'        => $s->online ? '온라인' : '오프라인',
            'name'          => $s->shop_user_name ?: '-',
            'email'         => $s->shop_user_email ?: '-',
            'role'          => $roleMap[$s->shop_user_role] ?? ($s->shop_user_role ?: '-'),
            'last_login'    => $fmtKst($s->last_login_at ?? null, 'm/d H:i'),
            'last_activity' => $fmtKst($s->last_activity_at ?? null, 'm/d H:i:s'),
            'last_logout'   => $fmtKst($s->last_logout_at ?? null, 'm/d H:i'),
            'ip'            => $s->ip ?: '-',
        ])->values();

        // 상품 조회 로그 그리드
        $productLogGrid = collect($logs->items())->map(fn ($log) => [
            'log_date'      => $log->log_date ?? $fmtKst($log->created_at ?? null, 'Y-m-d'),
            'name'          => $log->shop_user_name ?: '-',
            'email'         => $log->shop_user_email ?: '-',
            'product_name'  => $log->product_name ?: '-',
            'product_id'    => $log->product_id ?? '-',
            'shop_user_id'  => $log->shop_user_id ?? null,
        ])->values();

        return view('shop-monitoring.index', compact(
            'sessions', 'logs', 'todayCount', 'loginStatusGrid', 'productLogGrid'
        ));
    }
}
