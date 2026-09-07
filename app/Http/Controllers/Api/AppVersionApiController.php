<?php
// app/Http/Controllers/Api/AppVersionApiController.php
//
// 앱이 「지금 쓰는 판을 그대로 써도 되는가」를 묻는 자리.
// 로그인 없이 열어 둔다 — 너무 낡아 로그인조차 막힌 판도 물어볼 수 있어야 한다.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppVersionApiController extends Controller
{
    public function show(): JsonResponse
    {
        $trim = fn (?string $v) => ($v = trim((string) $v)) === '' ? null : $v;

        return response()->json([
            'success'        => true,
            'latest_version' => $trim(config('mobile.latest_version')),
            'min_version'    => $trim(config('mobile.min_version')),
            'store_url'      => $trim(config('mobile.store_url')),
            'notice'         => $trim(config('mobile.notice')),
        ]);
    }
}
