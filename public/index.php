<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Apache mod_php shares a process across projects; force-override env vars that
// may have been contaminated by putenv() calls from sibling apps (e.g. supportworks).
// Must run before Dotenv boots so its immutable check sees our values first.
(function () {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Strip surrounding quotes
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        // Remove inline comments for unquoted values
        if (str_contains($val, ' #')) {
            $val = trim(explode(' #', $val, 2)[0]);
        }
        putenv("{$key}={$val}");
    }
})();

/* 메모리가 바닥나도 오류 기록에 남게 한다 (2026-10-01 지시
   「모든 화면과 서버에서 오류가 발행하면 => 오류 기록 화면에서 확인 가능해야」).

   PHP 치명 오류(Allowed memory size exhausted)는 예외가 아니다. 그래도 Laravel 은
   종료 때 그것을 FatalError 로 바꾸어 report() 로 넘기도록 되어 있고, 우리 기록기
   (App\Support\ErrorRecorder)가 그 자리에 걸려 있다 — **그런데 메모리가 한 조각도
   남지 않으면 그 처리기조차 돌지 못한다.** 그래서 2026-10-01 메시지 관리 화면이
   128MB 를 넘겨 죽었을 때, 오류 기록에도 파일 로그에도 한 줄이 남지 않고 500 만 떴다.

   **여유를 미리 잡아 두고 종료 때 놓아 준다.** 종료 함수는 등록한 차례로 돌므로
   이것을 Laravel 보다 먼저 — 틀을 올리기 전에 — 등록해야 한다. 그러면 바닥난
   순간에도 Laravel 의 종료 처리기가 쓸 자리가 생기고, 그 길로 오류 기록에 담긴다.

   **4MB 다.** 운영에서 일부러 바닥내어 재어 보고 정했다(2026-10-01) — 512KB 와 1MB
   에서는 기록을 쓰는 중에 또 바닥났고(MySqlGrammarㆍReflectionClosure), 4MB 에서
   오류 기록에 「FatalError · Allowed memory size exhausted」가 담겼다. 요청마다
   들고 있어도 부담이 되지 않는 크기이고, 보통 요청에서는 종료 때 그냥 놓아 준다. */
$__여유메모리 = str_repeat(' ', 4 * 1024 * 1024);
register_shutdown_function(static function () use (&$__여유메모리) {
    $__여유메모리 = null;
});

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
