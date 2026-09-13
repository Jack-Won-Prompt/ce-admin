<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 운영에서 난 예외를 SupportWorks 로 보낸다.
 *
 * 보고가 사이트를 망가뜨리면 안 된다. 그래서 예외를 절대 밖으로 내보내지 않고,
 * 응답을 돌려준 뒤에 보내고, 시간 제한을 짧게 둔다.
 */
class SupportWorksReporter
{
    /** 가려야 할 이름. 값이 그대로 남으면 오류 기록이 곧 자격증명 창고가 된다. */
    private const SECRET_KEYS = [
        'token', 'access_token', 'refresh_token', 'api_key', 'apikey',
        'secret', 'password', 'passwd', 'pw', 'signature', 'auth', 'key',
        'authorization', 'credential', 'credentials', 'bearer', 'otp', 'pin', 'session',
    ];

    private const MASK = '[숨김]';

    public static function report(Throwable $e, ?Request $request = null): void
    {
        try {
            $url = config('services.supportworks.error_url');
            $token = config('services.supportworks.error_token');

            if (! $url || ! $token) {
                return;
            }

            // 404 는 보내지 않는다. 고칠 것이 없고, 봇이 훑고 갈 때마다 쌓인다.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return;
            }

            $payload = [
                'level'     => 'error',
                'exception' => get_class($e),
                'message'   => mb_substr(self::maskMessage($e->getMessage()), 0, 2000),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'url'       => $request ? self::maskUrl($request) : null,
                'trace'     => mb_substr(self::trace($e), 0, 8000),
            ];

            // 응답을 돌려준 뒤에 보낸다. 콘솔에는 그 시점이 없으므로 바로 보낸다.
            $send = function () use ($url, $token, $payload) {
                try {
                    Http::withToken($token)->timeout(2)->connectTimeout(2)->post($url, $payload);
                } catch (Throwable) {
                    // 보고가 실패해도 사이트는 계속 돈다.
                }
            };

            app()->runningInConsole() ? $send() : app()->terminating($send);
        } catch (Throwable) {
            // 여기서 예외가 새어 나가면 예외 처리기 자체가 깨진다.
        }
    }

    /**
     * 요청 주소. 쿼리스트링은 이름이 목록에 걸리면 값만 가리고,
     * 경로는 토큰처럼 생긴 조각만 가린다. `/orders/123` 은 남아야 어느 화면인지 안다.
     */
    public static function maskUrl(Request $request): string
    {
        $segments = array_map(
            fn (string $seg) => self::looksLikeSecret(rawurldecode($seg)) ? self::MASK : $seg,
            explode('/', $request->getPathInfo())
        );

        $url = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/') . implode('/', $segments);

        $query = $request->query->all();
        if ($query) {
            $url .= '?' . urldecode(http_build_query(self::maskQuery($query)));
        }

        return $url;
    }

    /** 배열 파라미터는 재귀로 들어간다. 이름이 걸리면 그 아래는 통째로 가린다. */
    private static function maskQuery(array $query): array
    {
        foreach ($query as $name => $value) {
            if (self::isSecretKey((string) $name)) {
                $query[$name] = self::MASK;
            } elseif (is_array($value)) {
                $query[$name] = self::maskQuery($value);
            }
        }

        return $query;
    }

    /**
     * 이름 전체가 목록과 같거나(`apiKey` → `apikey`), 영숫자 아닌 글자로 쪼갠
     * 조각 하나가 목록과 정확히 같을 때만 가린다. `keyword`·`monkey`·`author` 는 남는다.
     */
    private static function isSecretKey(string $name): bool
    {
        $name = strtolower($name);

        if (in_array($name, self::SECRET_KEYS, true)) {
            return true;
        }

        foreach (preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (in_array($part, self::SECRET_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 오류 메시지. 예외 종류를 따지지 않고 전체에 건다 — QueryException 은
     * SQL 과 바인딩 값을 그대로 담기 때문이다. 공백으로 끊고, 앞뒤 문장부호만 뗀
     * 가운데를 looksLikeSecret 에 넣는다. 역슬래시·슬래시·점이 섞인 낱말
     * (클래스 이름, 파일 경로)은 한 낱말이 아니라서 남는다.
     */
    public static function maskMessage(string $message): string
    {
        $parts = preg_split('/(\s+)/u', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $i => $word) {
            if ($word === '' || preg_match('/^\s+$/u', $word)) {
                continue;
            }

            // `_`·`-` 는 토큰의 몸이므로 떼지 않는다. 그 밖의 앞뒤 문장부호만 뗀다.
            if (preg_match('/^([^\p{L}\p{N}_-]*)(.*?)([^\p{L}\p{N}_-]*)$/us', $word, $m)
                && $m[2] !== ''
                && self::looksLikeSecret($m[2])
            ) {
                $parts[$i] = $m[1] . self::MASK . $m[3];
            }
        }

        return implode('', $parts);
    }

    /**
     * 스택. getTraceAsString() 은 인자 앞 15자를 적으므로 쓰지 않는다.
     * 파일·줄·클래스·함수만 모으고 인자는 아예 넣지 않는다.
     */
    public static function trace(Throwable $e): string
    {
        $lines = [];

        foreach ($e->getTrace() as $i => $frame) {
            $where = isset($frame['file'])
                ? $frame['file'] . '(' . ($frame['line'] ?? '?') . ')'
                : '[internal function]';

            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');

            $lines[] = '#' . $i . ' ' . $where . ': ' . $call . '()';
        }

        $lines[] = '#' . count($lines) . ' {main}';

        return implode("\n", $lines);
    }

    /** 토큰처럼 생긴 낱말인가 — 20자 이상, 영숫자·`_`·`-` 로만. 경로와 message 가 함께 쓴다. */
    public static function looksLikeSecret(string $word): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{20,}$/', $word);
    }
}
