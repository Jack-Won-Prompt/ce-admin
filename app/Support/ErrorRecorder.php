<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * 서버에서 난 잘못을 표에 담는다 (2026-09-11 지시).
 *
 * 지켜야 할 것 셋.
 *
 * ① 여기서 터지면 안 된다. 잘못을 담다 난 잘못이 화면을 덮으면 본래 잘못을 잃는다 —
 *    모든 것을 try 로 감싸고, 안 되면 파일 로그에만 적고 조용히 물러난다.
 * ② 스스로를 담지 않는다. 표가 없거나 DB 가 끊겼을 때 다시 표에 적으러 들어가면
 *    끝없이 돈다.
 * ③ 담을 것과 담지 않을 것을 가린다. 없는 주소(404)ㆍ로그인 안 함ㆍ입력값 어긋남은
 *    잘못이 아니라 일상이다. 그것까지 쌓으면 정작 볼 것이 묻힌다.
 */
class ErrorRecorder
{
    /** 지금 담는 중인가 — 스스로를 담지 않기 위한 빗장 */
    private static bool $담는중 = false;

    /** 표가 있는지 한 번만 묻는다 — 잘못이 날 때마다 물으면 그것도 짐이다 */
    private static ?bool $표있나 = null;

    /** 담지 않을 갈래 — 잘못이 아니라 일상인 것들 */
    private const 넘길것 = [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    ];

    /** 값이 이런 이름으로 오면 가린다 — 로그는 담당자가 보는 자리다 */
    private const 가릴이름 = [
        'password', 'password_confirmation', 'current_password', 'secret', 'token',
        'api_key', 'apikey', 'secret_key', 'access_token', 'refresh_token',
        'authorization', 'cert_key', 'resident_no', 'rrn', 'card_no', 'cvc',
    ];

    public static function 담기(Throwable $e): void
    {
        if (self::$담는중) {
            return;
        }

        try {
            if (self::넘길까($e)) {
                return;
            }

            self::$담는중 = true;

            if (! self::담을수있나()) {
                return;
            }

            self::적기($e);
        } catch (Throwable $속) {
            /* 담는 데 실패해도 본래 잘못을 잃지 않는다. 파일 로그에만 남긴다 —
               여기서 다시 표를 부르면 끝없이 돈다. */
            try {
                Log::error('[오류 기록] 표에 담지 못했습니다', [
                    'why'  => $속->getMessage(),
                    'orig' => $e::class . ': ' . $e->getMessage(),
                ]);
            } catch (Throwable) {
                // 파일 로그마저 안 되면 그냥 물러난다
            }
        } finally {
            self::$담는중 = false;
        }
    }

    private static function 넘길까(Throwable $e): bool
    {
        foreach (self::넘길것 as $갈래) {
            if ($e instanceof $갈래) {
                return true;
            }
        }

        /* 4xx 는 대체로 부르는 쪽 사정이다. 다만 419ㆍ429 는 자주 나면 얼개 문제라
           남긴다 — 설정으로 넓히거나 좁힐 수 있게 둔다. */
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $s = $e->getStatusCode();
            if ($s < 500 && ! in_array($s, config('errors.keep_status', [419, 429]), true)) {
                return true;
            }
        }

        return false;
    }

    private static function 적기(Throwable $e): void
    {
        $갈래  = class_basename($e);
        $파일  = (string) $e->getFile();
        $줄    = (int) $e->getLine();
        $열쇠  = hash('sha256', $갈래 . '|' . $파일 . '|' . $줄 . '|' . self::뼈대($e->getMessage()));

        $상태 = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            ? $e->getStatusCode() : 500;

        $이미 = ErrorLog::where('fingerprint', $열쇠)
            ->where('last_at', '>=', now()->subDays((int) config('errors.merge_days', 7)))
            ->first();

        if ($이미) {
            /* 같은 잘못이 또 났다 — 줄을 새로 세우지 않고 셈만 올린다.
               마지막에 난 때와 자리는 새것으로 바꾼다. */
            $이미->forceFill([
                'hit'     => $이미->hit + 1,
                'last_at' => now(),
                'url'     => self::주소(),
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name,
            ])->saveQuietly();

            return;
        }

        ErrorLog::create([
            'fingerprint' => $열쇠,
            'level'       => $상태 >= 500 ? 'critical' : 'error',
            'kind'        => $갈래,
            'exception'   => $e::class,
            'http_status' => $상태,
            'message'     => mb_substr((string) $e->getMessage(), 0, 4000),
            'file'        => mb_substr($파일, 0, 300),
            'line'        => $줄,
            'trace'       => mb_substr(self::자취($e), 0, 60000),
            'url'         => self::주소(),
            'http_method' => self::안전하게(fn () => Request::method()),
            'route_name'  => self::안전하게(fn () => Request::route()?->getName()),
            'ip'          => self::안전하게(fn () => Request::ip()),
            'user_agent'  => mb_substr((string) self::안전하게(fn () => Request::userAgent()), 0, 300),
            'user_id'     => Auth::id(),
            'user_name'   => Auth::user()?->name,
            'input'       => self::들어온값(),
            'hit'         => 1,
            'first_at'    => now(),
            'last_at'     => now(),
            'status'      => 'open',
        ]);
    }

    /**
     * 같은 잘못인지 가리려면 글월에서 그때그때 달라지는 것을 걷어내야 한다.
     * 번호ㆍ아이디가 든 글월은 부를 때마다 달라서 그대로 두면 줄이 묶이지 않는다.
     */
    private static function 뼈대(string $글): string
    {
        $글 = preg_replace('/\d+/', 'N', $글);
        $글 = preg_replace('/[0-9a-f]{8,}/i', 'X', (string) $글);

        return mb_substr((string) $글, 0, 300);
    }

    /** 자취는 우리 코드 줄만 남긴다 — vendor 안쪽까지 담으면 읽을 수 없다 */
    private static function 자취(Throwable $e): string
    {
        $줄 = [];
        foreach (explode("\n", $e->getTraceAsString()) as $l) {
            $줄[] = $l;
            if (count($줄) >= 40) {
                $줄[] = '  … (아래 줄임)';
                break;
            }
        }

        $앞 = $e->getPrevious();
        if ($앞) {
            $줄[] = '';
            $줄[] = '— 앞선 잘못 —';
            $줄[] = $앞::class . ': ' . $앞->getMessage();
            $줄[] = $앞->getFile() . ':' . $앞->getLine();
        }

        return implode("\n", $줄);
    }

    private static function 주소(): ?string
    {
        return mb_substr((string) self::안전하게(fn () => Request::fullUrl()), 0, 500) ?: null;
    }

    /** 보낸 값 — 가릴 이름은 가리고, 길면 자른다 */
    private static function 들어온값(): ?string
    {
        $값 = self::안전하게(fn () => Request::except(self::가릴이름));
        if (! is_array($값) || ! $값) {
            return null;
        }

        foreach (self::가릴이름 as $이름) {
            if (array_key_exists($이름, $값)) {
                $값[$이름] = '(가림)';
            }
        }

        $글 = json_encode($값, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $글 === false ? null : mb_substr($글, 0, 8000);
    }

    /** 화면 밖(콘솔ㆍ큐)에서 나면 Request 가 없다 — 물어보다 터지지 않게 감싼다 */
    private static function 안전하게(callable $할것): mixed
    {
        try {
            return $할것();
        } catch (Throwable) {
            return null;
        }
    }

    /** 표가 아직 없을 때는 담지 않는다 — 마이그레이션 전이거나 DB 가 끊겼을 때 */
    public static function 담을수있나(): bool
    {
        if (self::$표있나 !== null) {
            return self::$표있나;
        }

        try {
            self::$표있나 = DB::getSchemaBuilder()->hasTable('error_logs');
        } catch (Throwable) {
            self::$표있나 = false;
        }

        return self::$표있나;
    }
}
