<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 브라우저에서 난 오류를 받는 자리 (2026-09-11 지시).
 *
 * 화면이 조용히 망가지는 일은 서버 로그에 흔적이 없다 — 단추를 눌렀는데 아무 일도
 * 일어나지 않는 그 순간이 JS 오류다.
 *
 * 여기는 누구나 부를 수 있어야 한다. 오류는 권한이 없는 사람의 화면에서도 나기
 * 때문이다. 그래서 오류 기록 화면의 권한과 따로 두고, 대신 두 가지로 지킨다 —
 * 로그인한 사람만 받고, 한 사람이 짧은 새에 많이 보내면 막는다.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request)
    {
        /* 로컬 화면에서 난 오류는 운영 표에 담지 않는다 (2026-09-13) — 서버 쪽과 같은 규칙 */
        if (\App\Support\ErrorRecorder::로컬이면넘기나()) {
            return response()->json(['success' => true, 'skipped' => 'local']);
        }

        /* 한 사람이 1분에 30건까지. 넘으면 조용히 받아 준 척한다 —
           오류가 난 화면에 또 오류를 띄우면 쓰는 사람만 괴롭다. */
        $열쇠 = 'client-error:' . (Auth::id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($열쇠, 30)) {
            return response()->json(['success' => true, 'skipped' => 'too_many']);
        }
        RateLimiter::hit($열쇠, 60);

        $값 = $request->validate([
            'message' => 'required|string|max:2000',
            'file'    => 'nullable|string|max:300',
            'line'    => 'nullable|integer|min:0|max:9999999',
            'col'     => 'nullable|integer|min:0|max:9999999',
            'stack'   => 'nullable|string|max:20000',
            'url'     => 'nullable|string|max:500',
            'kind'    => 'nullable|string|max:80',
            'route'   => 'nullable|string|max:120',
        ]);

        $글월 = trim($값['message']);

        /* 「Script error.」 는 다른 곳에서 불러온 스크립트가 낸 것이라 내용을 알 수 없다.
           브라우저 확장이 낸 것도 여기로 섞여 든다 — 우리가 고칠 수 없는 것은 담지 않는다. */
        if ($글월 === '' || str_starts_with($글월, 'Script error')) {
            return response()->json(['success' => true, 'skipped' => 'opaque']);
        }
        if (preg_match('~^(chrome|moz|safari)-extension://~', (string) ($값['file'] ?? ''))) {
            return response()->json(['success' => true, 'skipped' => 'extension']);
        }

        /* 「ResizeObserver loop …」 은 브라우저가 자리를 다시 재느라 내는 소리다 (2026-09-11).

           화면이 그려지는 동안 크기가 한 번 더 바뀌면 브라우저가 다음 차례로 미루면서
           이 말을 낸다. 잘못이 아니라 알림이고, 우리가 고칠 것도 없다. 그런데도 화면을
           옮길 때마다 쌓여, 하루 만에 담당자 목록의 절반을 차지했다. 담지 않는다. */
        if (str_starts_with($글월, 'ResizeObserver loop')) {
            return response()->json(['success' => true, 'skipped' => 'resize_observer']);
        }

        /* 「Transition was skipped」 도 같은 갈래다 (2026-09-11).

           주문 등록 화면은 `@view-transition { navigation: auto; }` 로 화면 넘김을
           브라우저에 맡긴다 — 흰 화면이 끼지 않는다. 앞 넘김이 끝나기 전에 다음으로
           옮기면 브라우저가 앞것을 접으며 이 말을 낸다. 우리가 부르는 자리가 없어
           잡을 수도 없고, 빨리 옮길수록 자주 난다. 담지 않는다. */
        if (str_contains($글월, 'Transition was skipped')) {
            return response()->json(['success' => true, 'skipped' => 'view_transition']);
        }

        $갈래 = $값['kind'] ?: '브라우저 오류';
        $파일 = (string) ($값['file'] ?? '');
        $줄   = (int) ($값['line'] ?? 0);

        /* 서버 쪽과 같은 잣대로 묶는다 — 번호만 다른 것은 한 줄이 되게 뼈대로 견준다 */
        $뼈대 = preg_replace('/\d+/', 'N', $글월);
        $열쇠2 = hash('sha256', 'browser|' . $갈래 . '|' . $파일 . '|' . $줄 . '|' . mb_substr((string) $뼈대, 0, 300));

        $이미 = ErrorLog::where('fingerprint', $열쇠2)
            ->where('last_at', '>=', now()->subDays((int) config('errors.merge_days', 7)))
            ->first();

        if ($이미) {
            $이미->forceFill([
                'hit'       => $이미->hit + 1,
                'last_at'   => now(),
                'url'       => mb_substr((string) ($값['url'] ?? ''), 0, 500) ?: $이미->url,
                'user_id'   => Auth::id(),
                'user_name' => Auth::user()?->name,
            ])->saveQuietly();

            return response()->json(['success' => true, 'merged' => $이미->id]);
        }

        $줄기록 = ErrorLog::create([
            'fingerprint' => $열쇠2,
            'source'      => 'browser',
            'level'       => 'error',
            'kind'        => $갈래,
            'exception'   => null,
            'http_status' => null,
            'message'     => mb_substr($글월, 0, 4000),
            'file'        => mb_substr($파일, 0, 300) ?: null,
            'line'        => $줄 ?: null,
            'col'         => (int) ($값['col'] ?? 0) ?: null,
            'trace'       => mb_substr((string) ($값['stack'] ?? ''), 0, 60000) ?: null,
            'url'         => mb_substr((string) ($값['url'] ?? ''), 0, 500) ?: null,
            'http_method' => 'GET',
            'route_name'  => mb_substr((string) ($값['route'] ?? ''), 0, 120) ?: null,
            'ip'          => $request->ip(),
            'user_agent'  => mb_substr((string) $request->userAgent(), 0, 300),
            'user_id'     => Auth::id(),
            'user_name'   => Auth::user()?->name,
            'input'       => null,
            'hit'         => 1,
            'first_at'    => now(),
            'last_at'     => now(),
            'status'      => 'open',
        ]);

        return response()->json(['success' => true, 'id' => $줄기록->id]);
    }
}
