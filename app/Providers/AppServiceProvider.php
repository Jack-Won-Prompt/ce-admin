<?php

namespace App\Providers;

use App\Models\UserActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // 로그인 이벤트 → 사용자 활동 로그 기록
        Event::listen(Login::class, function (Login $event) {
            try {
                UserActivityLog::create([
                    'user_id'    => $event->user->id,
                    'type'       => 'login',
                    'menu_name'  => '로그인',
                    'route_name' => 'login',
                    'url'        => request()->fullUrl(),
                    'ip_address' => request()->ip(),
                    'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 300),
                ]);
            } catch (\Throwable) {}
        });
    }
}
