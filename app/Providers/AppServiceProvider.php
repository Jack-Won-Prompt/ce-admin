<?php

namespace App\Providers;

use App\Models\UserActivityLog;
use App\Services\PermissionService;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 권한 판정 캐시를 요청 단위로 공유하기 위해 싱글턴으로 둔다
        $this->app->singleton(PermissionService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        /* 관리자 화면에서 저장한 외부 서비스 키·설정을 런타임 config() 에 얹는다.
           저장한 항목만 덮어쓰므로, 손대지 않은 값은 .env 그대로다.
           DB 를 아직 못 쓰는 상황이면 조용히 넘어간다. */
        \App\Support\ServiceSettings::applyToConfig();

        /* 토스는 시험ㆍ운영 키를 두 벌 담아 두고 한 칸으로 고른다. 설정을 읽어 온
           바로 뒤에 고른 것을 쓰이는 자리에 앉힌다 — 순서가 뒤바뀌면 화면에서 바꾼
           갈래가 반영되지 않는다. */
        \App\Support\TossEnvironment::apply();

        /* 위드웍스 연동 — 테스트(데모웍스)·운영(위드웍스) 중 어디에 붙을지가 화면 설정에
           있다. 부르는 쪽·받는 쪽이 전부 config 를 보므로 여기서 한 번 올려 둔다.
           설치 직후처럼 표가 아직 없을 수도 있어 조용히 넘어간다. */
        try {
            \App\Models\WithworksSetting::applyToConfig();
        } catch (\Throwable) {
        }

        /* 자산 URL에 파일 수정시각을 붙여 캐시를 자동으로 무효화한다.
           사용:  <link rel="stylesheet" href="@assetv('vendor/wwgrid/wwGrid.css')"> */
        Blade::directive('assetv', fn (string $expr) =>
            "<?php echo \\App\\Support\\Asset::versioned({$expr}); ?>");

        /* Figma DS 아이콘을 인라인 SVG 로 출력한다. 이름은 Figma 레이어 이름과 같다.
           사용:  @dsicon('home-04')  ·  @dsicon('bell-02', 'ds-icon hdr-icon') */
        Blade::directive('dsicon', fn (string $expr) =>
            "<?php echo \\App\\Support\\DsIcon::svg({$expr}); ?>");

        $this->bootPermissions();

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

    /**
     * 권한 그룹 체계 부트스트랩.
     *  - Blade  : @perm('페이지','액션') ... @endperm / @permany(...) ... @endpermany
     *  - Gate   : Gate::allows('page', ['페이지','액션'])
     *  - admin 은 Gate::before 로 무조건 통과 (PermissionService 와 동일한 정책)
     */
    private function bootPermissions(): void
    {
        Blade::if('perm', function (string $page, string $action = 'view') {
            return perm($page, $action);
        });

        // 액션 중 하나라도 가능하면 통과 (그룹 헤더·섹션 표시 판단용)
        Blade::if('permany', function (string $page, array $actions = ['view']) {
            return perm_any($page, $actions);
        });

        Gate::before(fn ($user) => $user?->role === 'admin' ? true : null);

        Gate::define('page', function ($user, string $page, string $action = 'view') {
            return app(PermissionService::class)->allows($user, $page, $action);
        });
    }
}
