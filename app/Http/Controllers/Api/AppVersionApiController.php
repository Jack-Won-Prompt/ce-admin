<?php
// app/Http/Controllers/Api/AppVersionApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * 앱이 「내가 낡았는지」를 묻는 자리 (2026-09-29 지시).
 *
 * 스토어를 쓰지 않고 APK 를 직접 나눠 주기로 했다. 그러면 스토어가 해 주던
 * 「새 판이 나왔습니다」를 우리가 해야 한다 — 앱은 열릴 때와 다시 앞으로 올 때
 * 이 자리를 물어보고, 낡았으면 받을 곳을 안내한다.
 *
 * 값은 모두 **환경 설정 › 모바일 앱** 에서 고친다(설정 화면에 이미 있는 칸들이다).
 * 코드를 배포하지 않고 판올림 안내를 낼 수 있어야 하기 때문이다.
 *
 *   mobile.latest_version — 새로 낸 판 (예: 1.3.4). 비우면 안내하지 않는다
 *   mobile.min_version    — 이보다 낮으면 **쓸 수 없다**(건너뛸 수 없는 안내)
 *   mobile.apk_url        — APK 를 받을 주소. 스토어를 쓰지 않는 동안 이것이 받는 길이다
 *   mobile.store_url      — 스토어로 돌아갈 때 쓸 주소. 있으면 앱이 이쪽을 먼저 쓴다
 *   mobile.notice         — 무엇이 달라졌는지 한두 줄
 *
 * 로그인 앞이라 토큰을 요구하지 않는다 — 낡은 판은 로그인조차 못 하는 경우가 있어,
 * 그 자리에서도 안내가 떠야 한다.
 */
class AppVersionApiController extends Controller
{
    public function show(): JsonResponse
    {
        $최신   = $this->값('mobile.latest_version');
        $최소   = $this->값('mobile.min_version');
        $apk    = $this->값('mobile.apk_url');
        $스토어 = $this->값('mobile.store_url');

        return response()->json([
            'success'      => true,
            // 판 번호는 글자로 준다 — 앱이 1.3.4 처럼 마디로 견준다
            'latest'       => $최신,
            'min'          => $최소,
            /* 받을 곳. 스토어를 쓰면 스토어가 먼저다 — 지금은 APK 를 직접 나눠 준다.
               둘 다 비면 앱은 안내만 띄우고 받을 단추를 세우지 않는다. */
            'download_url' => $스토어 ?: $apk,
            'is_store'     => (bool) $스토어,
            'notice'       => $this->값('mobile.notice'),
        ]);
    }

    /** 비어 있는 설정은 null 로 돌려준다 — 앱이 「없음」과 「빈 글」을 가리지 않아도 되게 */
    private function 값(string $키): ?string
    {
        $값 = trim((string) config($키, ''));

        return $값 === '' ? null : $값;
    }
}
