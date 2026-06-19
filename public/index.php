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
