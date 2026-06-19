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

        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100]) ? (int) $request->input('per_page') : 20;
        $logs    = $query->paginate($perPage)->withQueryString();
        $users   = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('user-logs.index', compact('logs', 'users'));
    }
}
