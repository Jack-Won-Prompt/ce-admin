<?php

namespace Tests\Feature;

use App\Support\SupportWorksReporter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * SupportWorks 오류 보고 — 가림 규칙과 「보고가 사이트를 망가뜨리지 않는다」.
 *
 * 토큰 값은 코드에 적지 않고 매번 만들어 쓴다. DB 는 이 테스트만 쓰는
 * 메모리 sqlite 연결에서만 건드린다 (로컬 .env 는 운영 DB 를 가리킨다).
 */
class SupportWorksReporterTest extends TestCase
{
    private const MASK = '[숨김]';

    private string $url = 'https://supportworks.invalid/api/errors';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = 'test-' . Str::random(12);
        Http::preventStrayRequests();
    }

    private function configure(): void
    {
        config([
            'services.supportworks.error_url'   => $this->url,
            'services.supportworks.error_token' => $this->token,
        ]);
    }

    private function masked(string $uri): string
    {
        return urldecode(SupportWorksReporter::maskUrl(Request::create('http://ce.test' . $uri)));
    }

    /* ── looksLikeSecret ─────────────────────────── */

    public function test_looks_like_secret(): void
    {
        $this->assertTrue(SupportWorksReporter::looksLikeSecret(Str::random(20)));
        $this->assertTrue(SupportWorksReporter::looksLikeSecret(Str::random(10) . '_-' . Str::random(10)));

        $this->assertFalse(SupportWorksReporter::looksLikeSecret(Str::random(19)));
        $this->assertFalse(SupportWorksReporter::looksLikeSecret('123'));
        $this->assertFalse(SupportWorksReporter::looksLikeSecret('App\\Http\\Controllers\\OrderController'));
        $this->assertFalse(SupportWorksReporter::looksLikeSecret('/var/www/app/Http/Controller.php'));
        $this->assertFalse(SupportWorksReporter::looksLikeSecret(Str::random(20) . '.php'));
    }

    /* ── maskUrl: 가림 ─────────────────────────── */

    public function test_query_token_value_is_masked_name_kept(): void
    {
        $this->assertSame('http://ce.test/orders?token=' . self::MASK, $this->masked('/orders?token=SECRET'));
    }

    public function test_query_names_on_the_list_are_masked(): void
    {
        foreach (['api_key', 'access-token', 'apiKey', 'refresh.token', 'password', 'X-Signature'] as $name) {
            $out = $this->masked('/orders?' . $name . '=SECRET&page=2');

            // PHP 는 쿼리 이름의 `.` 을 `_` 로 바꿔 받는다 (refresh.token → refresh_token).
            $this->assertStringNotContainsString('SECRET', $out, $name);
            $this->assertStringContainsString(str_replace('.', '_', $name) . '=' . self::MASK, $out, $name);
            $this->assertStringContainsString('page=2', $out, $name);
        }
    }

    public function test_array_query_is_masked_recursively(): void
    {
        $out = $this->masked('/orders?user[password]=SECRET&user[name]=kim&a[b][token]=SECRET');

        $this->assertStringNotContainsString('SECRET', $out);
        $this->assertStringContainsString('user[password]=' . self::MASK, $out);
        $this->assertStringContainsString('user[name]=kim', $out);
        $this->assertStringContainsString('a[b][token]=' . self::MASK, $out);
    }

    public function test_token_in_path_is_masked(): void
    {
        $secret = Str::random(64);

        $this->assertSame(
            'http://ce.test/reset-password/' . self::MASK,
            $this->masked('/reset-password/' . $secret)
        );
    }

    /* ── maskUrl: 안 가림 ─────────────────────────── */

    public function test_ordinary_query_names_are_kept(): void
    {
        foreach (['keyword=shoes', 'author=kim', 'monkey=1', 'keynote=1'] as $pair) {
            $this->assertSame('http://ce.test/orders?' . $pair, $this->masked('/orders?' . $pair), $pair);
        }
    }

    public function test_ordinary_path_is_kept(): void
    {
        // 다 가려 버리면 어느 화면에서 난 오류인지 알 수 없다.
        $this->assertSame('http://ce.test/orders/123', $this->masked('/orders/123'));
        $this->assertSame('http://ce.test/reset-password', $this->masked('/reset-password'));
    }

    /* ── maskMessage ─────────────────────────── */

    public function test_message_masks_sql_binding(): void
    {
        $secret = Str::random(32);
        $msg = "SQLSTATE[42S22]: Column not found (Connection: mysql, SQL: select * from users where token = {$secret} and id = 123)";

        $out = SupportWorksReporter::maskMessage($msg);

        $this->assertStringNotContainsString($secret, $out);
        $this->assertStringContainsString('where token = ' . self::MASK . ' and id = 123)', $out);
    }

    public function test_message_keeps_surrounding_punctuation(): void
    {
        $secret = Str::random(40);

        $this->assertSame(
            "value '" . self::MASK . "', (" . self::MASK . ')',
            SupportWorksReporter::maskMessage("value '{$secret}', ({$secret})")
        );
    }

    public function test_message_keeps_class_names_and_paths(): void
    {
        $msg = 'Call to undefined method App\\Http\\Controllers\\OrderController::show() in '
             . 'E:\\xampp\\htdocs\\ce-admin\\app\\Http\\Controllers\\OrderController.php:42 '
             . 'see /var/www/lcpoint/app/Support/SupportWorksReporter.php';

        $this->assertSame($msg, SupportWorksReporter::maskMessage($msg));
    }

    public function test_real_query_exception_message_loses_binding(): void
    {
        config(['database.connections.sw_probe' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);

        $secret = Str::random(32);

        try {
            DB::connection('sw_probe')->select('select * from users where token = ? and id = ?', [$secret, 123]);
            $this->fail('QueryException 이 나야 한다');
        } catch (QueryException $e) {
            // 원본에는 바인딩 값이 그대로 들어 있다 — 그래서 가림이 필요하다.
            $this->assertStringContainsString($secret, $e->getMessage());

            $out = SupportWorksReporter::maskMessage($e->getMessage());
            $this->assertStringNotContainsString($secret, $out);
            $this->assertStringContainsString('id = 123', $out);
        } finally {
            DB::purge('sw_probe');
        }
    }

    /* ── trace ─────────────────────────── */

    private function throwWith(string $value): void
    {
        throw new \RuntimeException('boom');
    }

    public function test_trace_never_carries_arguments(): void
    {
        $secret = Str::random(40);
        $sentence = '비밀번호는 이것 입니다 길게 적은 문장';

        try {
            $this->throwWith($secret);
        } catch (\RuntimeException $e) {
        }

        try {
            $this->throwWith($sentence);
        } catch (\RuntimeException $e2) {
        }

        // 인자를 적는 설정이면 getTraceAsString 은 앞 15자를 흘린다 — 그래서 쓰지 않는다.
        if (! ini_get('zend.exception_ignore_args')) {
            $this->assertStringContainsString(substr($secret, 0, 15), $e->getTraceAsString());
        }

        $trace = SupportWorksReporter::trace($e);
        $this->assertStringNotContainsString(substr($secret, 0, 15), $trace);
        $this->assertStringContainsString(static::class . '->throwWith()', $trace);
        $this->assertMatchesRegularExpression('/^#0 .+\(\d+\): .+\(\)$/m', $trace);

        $this->assertStringNotContainsString('비밀번호는', SupportWorksReporter::trace($e2));
    }

    /* ── report ─────────────────────────── */

    public function test_report_skips_quietly_without_config(): void
    {
        Http::fake();
        config(['services.supportworks.error_url' => null, 'services.supportworks.error_token' => null]);

        SupportWorksReporter::report(new \RuntimeException('boom'), Request::create('http://ce.test/orders/1'));

        Http::assertNothingSent();
    }

    public function test_report_skips_404(): void
    {
        Http::fake();
        $this->configure();

        SupportWorksReporter::report(new NotFoundHttpException(), Request::create('http://ce.test/wp-login.php'));

        Http::assertNothingSent();
    }

    public function test_report_sends_masked_payload(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $this->configure();

        $secret = Str::random(32);
        $request = Request::create('http://ce.test/reset-password/' . Str::random(64) . '?token=SECRET&page=2');

        SupportWorksReporter::report(new \RuntimeException("where token = {$secret}"), $request);

        Http::assertSent(function (ClientRequest $r) use ($secret) {
            $body = $r->data();

            return $r->url() === $this->url
                && $r->hasHeader('Authorization', 'Bearer ' . $this->token)
                && $body['level'] === 'error'
                && $body['exception'] === \RuntimeException::class
                && $body['message'] === 'where token = ' . self::MASK
                && ! str_contains($body['url'], 'SECRET')
                && str_contains(urldecode($body['url']), '/reset-password/' . self::MASK)
                && str_contains($body['url'], 'page=2')
                && is_int($body['line'])
                && ! str_contains($body['trace'], $secret);
        });
    }

    public function test_report_never_throws_when_sending_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));
        $this->configure();

        SupportWorksReporter::report(new \RuntimeException('boom'), Request::create('http://ce.test/orders/1'));

        $this->assertTrue(true, '보고 실패가 밖으로 새지 않았다');
    }

    /* ── 사이트가 정상으로 도는가 ─────────────────────────── */

    public function test_site_keeps_running_when_an_exception_is_reported(): void
    {
        Route::get('/__sw_boom', fn () => throw new \RuntimeException('boom'));
        Route::get('/__sw_ok', fn () => 'ok');

        // Http::fake 는 스텁을 뒤에 덧붙이므로 한 스텁 안에서 받는 쪽 상태를 바꾼다.
        $down = true;
        Http::fake(function () use (&$down) {
            if ($down) {
                throw new ConnectionException('down');
            }

            return Http::response([], 200);
        });
        $this->configure();

        // 받는 쪽이 죽어 있어도
        $this->get('/__sw_boom')->assertStatus(500);
        $this->get('/__sw_ok')->assertOk()->assertSee('ok');

        // 받는 쪽이 살아 있으면 보고가 간다
        $down = false;

        $this->get('/__sw_boom?token=SECRET')->assertStatus(500);

        Http::assertSent(fn (ClientRequest $r) => $r->url() === $this->url
            && $r['exception'] === \RuntimeException::class
            && str_contains(urldecode($r['url']), '/__sw_boom?token=' . self::MASK));
    }
}
