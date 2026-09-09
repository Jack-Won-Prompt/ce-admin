<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\SsoSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CE Admin 웹 SSO(Entra · OIDC) — 지시서 LTL-UNICORN-20260909-02 §7.
 *
 * **회귀를 가장 먼저 본다.** 이 기능은 켜지 않은 상태가 기본이고, 그때 기존 로그인이
 * 조금도 달라지지 않아야 한다(§0-3).
 */
class SsoWebTest extends TestCase
{
    /**
     * 이 테스트가 쓰는 표만 손수 세운다.
     *
     * RefreshDatabase 를 쓰지 못한다 — 이 저장소의 마이그레이션은 sqlite 에서 통째로
     * 돌지 않는다(prescriptions 가 서기 전에 그 표에 칸을 더하는 줄이 있다). 그것은
     * 이 기능이 만든 문제가 아니고 여기서 고칠 것도 아니라, 필요한 넷만 세운다.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role')->default('manager');
            $t->boolean('is_active')->default(true);
            $t->string('phone')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group');
            $t->string('key');
            $t->text('value')->nullable();
            $t->boolean('is_secret')->default(false);
            $t->timestamps();
        });

        Schema::create('user_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('type')->nullable();
            $t->string('action')->nullable();
            $t->string('target_type')->nullable();
            $t->string('target_id')->nullable();
            $t->integer('record_count')->nullable();
            $t->string('reason_code')->nullable();
            $t->text('reason_text')->nullable();
            $t->date('retention_until')->nullable();
            $t->string('menu_name')->nullable();
            $t->string('route_name')->nullable();
            $t->text('url')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('event')->nullable();
            $t->string('causer_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->text('properties')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });

        \App\Support\SsoSettings::forget();
    }

    /** 설정 넷을 채워 켠다 */
    private function SSO를켠다(): void
    {
        SsoSettings::put([
            'tenant_id'     => '11111111-2222-3333-4444-555555555555',
            'client_id'     => 'client-id-for-test',
            'client_secret' => 'super-secret-value-1234',
            'redirect_uri'  => 'https://example.test/auth/entra/callback',
            'enabled'       => true,
        ]);
    }

    // ── 회귀 ────────────────────────────────────────────────

    public function test_꺼져_있으면_기존_로그인이_그대로다(): void
    {
        $this->assertFalse(SsoSettings::usable());

        $this->get(route('login'))->assertOk();

        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        $this->post(route('login.store'), [
            'email'    => $user->email,
            'password' => 'secret1234',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_꺼져_있으면_로그인_화면으로_되돌린다(): void
    {
        /* 404 로 답하지 않는다 — 그러면 「아직 안 켰다」와 「배포가 안 됐다」를
           가릴 수 없다. 로그인 화면의 단추가 예전부터 하던 말과 같은 말을 한다. */
        foreach (['/auth/entra/redirect', '/auth/entra/callback'] as $길) {
            $this->get($길)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }
    }

    public function test_설정이_하나도_없어도_화면이_선다(): void
    {
        // 신규 DB — settings 표에 SSO 줄이 하나도 없다
        $this->assertSame(0, Setting::where('group', SsoSettings::GROUP)->count());

        /* 화면을 통째로 그리지 않고 컨트롤러가 내주는 값으로 본다 — 레이아웃이
           메뉴 배지를 세느라 처방전ㆍ주문 표를 읽는데, 그 표를 다 세우는 것은
           이 기능의 시험이 아니다. */
        $view = app(\App\Http\Controllers\SsoSettingController::class)->edit();
        $d    = $view->getData();

        $this->assertFalse($d['usable']);
        $this->assertFalse($d['enabled']);
        $this->assertNull($d['tenantId']);
        $this->assertNull($d['secretMasked']);
        // HQ 에 등록할 주소는 값이 없어도 만들어져야 한다
        $this->assertStringContainsString('/auth/entra/callback', $d['suggestRedirect']);
        $this->assertStringContainsString('/auth/entra/frontchannel-logout', $d['suggestLogout']);
    }

    // ── 관리 화면 ───────────────────────────────────────────

    public function test_관리자가_아니면_설정_화면을_못_연다(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($user)->get(route('sso-settings.edit'))->assertForbidden();
    }

    public function test_Secret은_뒤_넉_자만_보인다(): void
    {
        $this->SSO를켠다();

        $this->assertSame('••••••••1234', SsoSettings::maskedSecret());

        /* 화면으로 내려가는 값에 원문이 섞이지 않는다 — write-only 다 */
        $d = app(\App\Http\Controllers\SsoSettingController::class)->edit()->getData();

        $this->assertArrayNotHasKey('clientSecret', $d);
        $this->assertSame('••••••••1234', $d['secretMasked']);
        $this->assertStringNotContainsString(
            'super-secret-value-1234',
            json_encode($d, JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_Secret은_DB에_평문으로_남지_않는다(): void
    {
        $this->SSO를켠다();

        $row = Setting::where('group', SsoSettings::GROUP)->where('key', 'client_secret')->first();

        $this->assertNotNull($row);
        $this->assertNotSame('super-secret-value-1234', $row->getRawOriginal('value'));
        $this->assertTrue($row->is_secret);
        $this->assertSame('super-secret-value-1234', $row->plainValue());
    }

    public function test_필수값이_비면_켤_수_없다(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('sso-settings.edit'))
            ->put(route('sso-settings.update'), [
                'tenant_id' => 'tenant-only',
                'enabled'   => '1',
            ])
            ->assertSessionHasErrors('enabled');

        $this->assertFalse(SsoSettings::usable());
    }

    public function test_Secret을_비워_보내면_담긴_값이_그대로다(): void
    {
        $this->SSO를켠다();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('sso-settings.update'), [
            'tenant_id'     => '11111111-2222-3333-4444-555555555555',
            'client_id'     => '바뀐-client-id',
            'client_secret' => '',                       // 「그대로 두겠다」
            'redirect_uri'  => 'https://example.test/auth/entra/callback',
            'enabled'       => '1',
        ]);

        $v = SsoSettings::all();
        $this->assertSame('바뀐-client-id', $v['client_id']);
        $this->assertSame('super-secret-value-1234', $v['client_secret']);
    }

    public function test_설정_변경_자취에_값은_남지_않는다(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('sso-settings.update'), [
            'tenant_id'     => 'tenant-abc',
            'client_secret' => 'secret-abc',
        ]);

        $log = \Spatie\Activitylog\Models\Activity::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('SSO 설정 변경', $log->description);
        $this->assertStringNotContainsString('secret-abc', $log->description);
        $this->assertStringNotContainsString('tenant-abc', $log->description);
    }

    // ── 로그인 길 ───────────────────────────────────────────

    public function test_켜면_인가_주소로_보낸다(): void
    {
        $this->SSO를켠다();

        $res = $this->get('/auth/entra/redirect');

        $res->assertRedirect();
        $url = $res->headers->get('Location');

        $this->assertStringContainsString('login.microsoftonline.com', $url);
        $this->assertStringContainsString('11111111-2222-3333-4444-555555555555', $url);
        $this->assertStringContainsString('client_id=client-id-for-test', $url);
        $this->assertStringContainsString('response_type=code', $url);
        // 우리가 청하는 것 — openid·profile·email·offline_access
        $this->assertStringContainsString('openid', urldecode($url));
        $this->assertStringContainsString('offline_access', urldecode($url));
        // state 는 Socialite 가 붙인다(끄지 않는다)
        $this->assertStringContainsString('state=', $url);
    }

    public function test_front_channel_logout_은_세션이_없어도_200이다(): void
    {
        $this->get('/auth/entra/frontchannel-logout')->assertOk();
    }

    public function test_front_channel_logout_이_세션을_끊는다(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $this->get('/auth/entra/frontchannel-logout')->assertOk();

        $this->assertGuest();
    }
}
