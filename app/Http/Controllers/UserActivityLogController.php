<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserActivityLogController extends Controller
{
    private const ADMIN_EMAIL = 'admin@ce-admin.co.kr';

    public function index(Request $request)
    {
        abort_unless(Auth::user()->email === self::ADMIN_EMAIL, 403);

        $query = UserActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(function ($sub) use ($kw) {
                $sub->where('menu_name', 'like', "%{$kw}%")
                    ->orWhere('ip_address', 'like', "%{$kw}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$kw}%")
                                                      ->orWhere('email', 'like', "%{$kw}%"));
            });
        }

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // wwGrid: 필터된 전체 로그를 그리드용 배열로 전달 (클라이언트사이드)
        $gridData = $query->get()->map(fn ($log) => [
            'id'        => $log->id,
            'created'   => $log->created_at->format('Y-m-d H:i:s'),
            'type'      => $log->type === 'login' ? '로그인' : '방문',
            'user'      => $log->user?->name ?? '-',
            'email'     => $log->user?->email ?? '',
            'ip'        => $log->ip_address ?? '-',
            'menu'      => $log->menu_name ?? '-',
            'route'     => ($log->route_name && $log->route_name !== $log->menu_name) ? $log->route_name : '',
            'url'       => $log->url ?? '-',
            'ua'        => $log->user_agent ?? '-',
        ])->values();
        $total = $gridData->count();

        return view('user-logs.index', compact('gridData', 'users', 'total'));
    }
}
