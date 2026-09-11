<?php

namespace App\Http\Controllers;

use App\Models\DelegationSign;
use App\Services\Nice\DelegationNiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * 운영 데이터 › 위임장 서명 — 환자가 보는 공개 화면 (2026-09-11 지시).
 *
 * 로그인 없이 연다. 문자로 받은 링크 하나가 열쇠이고, 30분이 지나면 닫힌다.
 *
 * 주문 등록의 /consent/{token} 과 겉모습은 같되 코드는 따로다 — 그쪽은 처방전ㆍ
 * 청구전략ㆍ서류 셋을 물고 있어 거래처만으로는 설 수 없다.
 */
class DelegationSignPublicController extends Controller
{
    public function show(string $token): View
    {
        $sign = DelegationSign::where('token', $token)->firstOrFail();

        /* 이미 서명한 링크는 다시 열지 않는다 — 두 번 받으면 어느 것이 참인지
           가릴 수 없다. 만료된 것도 마찬가지로 닫는다. */
        $닫힘 = $sign->status === 'signed' ? '이미 서명을 마친 링크입니다.'
              : ($sign->status === 'declined' ? '동의하지 않음으로 마친 링크입니다.'
              : (! $sign->열려있나() ? '링크 유효 시간이 지났습니다. 담당자에게 다시 요청해 주십시오.' : null));

        $nice = app(DelegationNiceService::class);

        return view('delegation-signs.sign', [
            'sign'        => $sign,
            '닫힘'        => $닫힘,
            'niceEnabled' => $nice->enabled(),
            'niceEnforce' => $nice->enforce(),
            'verified'    => (bool) $sign->nice_verified_at,
        ]);
    }

    // ── NICE 표준창 파라미터 발급 ─────────────────────────
    public function niceStart(string $token): JsonResponse
    {
        $sign = DelegationSign::where('token', $token)->firstOrFail();

        if (! $sign->열려있나()) {
            return response()->json(['success' => false, 'message' => '링크 유효 시간이 지났습니다.'], 410);
        }

        /* 시험 중에는 NICE 에 묻지 않고 통과시킨다(NICE_SIMULATE). 실제 인증에서
           확인할 것은 그대로 두되, 시험 자리에서 남의 이름으로 인증하지 않는다. */
        if (config('nice.simulate')) {
            $sign->forceFill([
                'nice_verified_at' => now(),
                'nice_name'        => $sign->customer_name,
                'nice_mobile'      => $sign->sent_to,
            ])->save();

            return response()->json(['success' => true, 'simulated' => true]);
        }

        try {
            $out = app(DelegationNiceService::class)->startVerification(
                $sign,
                route('delegation.nice.callback', ['token' => $token])
            );

            return response()->json(['success' => true, 'auth_url' => $out['auth_url']]);
        } catch (\Throwable $e) {
            Log::warning('[위임장 서명] 본인확인 시작 실패', ['id' => $sign->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── NICE 가 돌아오는 자리 ─────────────────────────────
    public function niceCallback(Request $request, string $token)
    {
        $sign = DelegationSign::where('token', $token)->firstOrFail();

        try {
            $r = app(DelegationNiceService::class)->handleCallback($sign, $request->all());

            $sign->forceFill([
                'nice_verified_at' => now(),
                'nice_name'        => $r['name'] ?: null,
                'nice_birthdate'   => $r['birthdate'] ?: null,
                'nice_gender'      => $r['gender'] ?: null,
                'nice_mobile'      => $r['mobile'] ?: null,
                'nice_ci'          => $r['ci'] ?: null,
                'nice_di'          => $r['di'] ?: null,
            ])->save();

            $말 = null;
        } catch (\Throwable $e) {
            Log::warning('[위임장 서명] 본인확인 실패', ['id' => $sign->id, 'error' => $e->getMessage()]);
            $말 = $e->getMessage();
        }

        /* 표준창은 팝업으로 열린다 — 결과를 부모 창에 건네고 스스로 닫는다 */
        return response()->view('delegation-signs.nice-done', ['말' => $말]);
    }

    // ── 서명 받기 ─────────────────────────────────────────
    public function submit(Request $request, string $token): JsonResponse
    {
        $sign = DelegationSign::where('token', $token)->firstOrFail();

        if (! $sign->열려있나()) {
            return response()->json(['success' => false, 'message' => '링크 유효 시간이 지났습니다.'], 410);
        }

        $값 = $request->validate([
            'action'           => 'required|in:agreed,declined',
            'agree_delegation' => 'nullable|boolean',
            'agree_privacy'    => 'nullable|boolean',
            'agree_marketing'  => 'nullable|boolean',
            'signature'        => 'nullable|string|max:500000',
        ]);

        /* 동의하지 않음 — 서명도 그림도 남기지 않는다 */
        if ($값['action'] === 'declined') {
            $sign->forceFill([
                'status'     => 'declined',
                'ip'         => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ])->save();

            return response()->json(['success' => true, 'message' => '동의하지 않음으로 접수했습니다.']);
        }

        /* NICE 를 강제하는 설정이면 본인확인 없이는 받지 않는다 */
        if (app(DelegationNiceService::class)->enforce() && ! $sign->nice_verified_at) {
            return response()->json(['success' => false, 'message' => '휴대폰 본인확인을 먼저 해 주십시오.'], 422);
        }

        if (! ($값['agree_delegation'] ?? false)) {
            return response()->json(['success' => false, 'message' => '요양비 청구 위임에 동의해 주십시오.'], 422);
        }

        $그림 = self::그림바이트((string) ($값['signature'] ?? ''));
        if ($그림 === null) {
            return response()->json(['success' => false, 'message' => '서명란에 서명해 주십시오.'], 422);
        }

        /* 파일ㆍ파일명ㆍ그림 셋을 함께 남긴다 (2026-09-11 지시).

           파일은 폴더째 운영 서버로 옮기려고, 파일명은 표만 보고도 어느 파일인지
           알려고, 그림 자체(base64)는 폴더가 어긋나도 표 하나로 되살아나게.

           이름에 때를 넣어 덮어쓰지 않는다 — 다시 보내 새로 서명받아도 옛 그림이
           파일로 남는다. 표는 최신 하나만 가리킨다. */
        $파일명 = $token . '_' . now()->format('YmdHis') . '.png';
        $경로   = DelegationSign::폴더 . '/' . now()->format('Y/m') . '/' . $파일명;

        try {
            Storage::disk('local')->put($경로, $그림);
        } catch (\Throwable $e) {
            /* 파일을 못 써도 서명은 잃지 않는다 — 표에 그림이 함께 들어간다 */
            Log::warning('[위임장 서명] 그림 파일을 쓰지 못했습니다', ['id' => $sign->id, 'error' => $e->getMessage()]);
            $경로 = null;
        }

        $sign->forceFill([
            'status'           => 'signed',
            'signed_at'        => now(),
            'agree_delegation' => true,
            'agree_privacy'    => (bool) ($값['agree_privacy'] ?? false),
            'agree_marketing'  => (bool) ($값['agree_marketing'] ?? false),
            'sign_path'        => $경로,
            'sign_filename'    => $파일명,
            'sign_base64'      => $값['signature'],
            'ip'               => $request->ip(),
            'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
        ])->save();

        return response()->json(['success' => true, 'message' => '서명이 정상적으로 접수되었습니다.']);
    }

    /**
     * 서명 그림을 바이트로. 아무것도 그리지 않은 빈 그림은 받지 않는다.
     *
     * 화면이 canvas 를 그대로 내보내면 손대지 않아도 흰 그림이 온다 — 받아 두면
     * 「서명함」으로 서지만 종이에는 아무것도 없다.
     */
    private static function 그림바이트(string $data): ?string
    {
        if ($data === '' || ! str_starts_with($data, 'data:image/')) {
            return null;
        }

        $조각 = preg_replace('~^data:image/\w+;base64,~', '', $data);
        $바이트 = base64_decode($조각, true);

        if ($바이트 === false || strlen($바이트) < 200) {
            return null;
        }

        /* 칠해진 점이 있는가 — 알파가 0 이 아닌 점을 센다 */
        $그림 = @imagecreatefromstring($바이트);
        if (! $그림) {
            return null;
        }

        $w = imagesx($그림);
        $h = imagesy($그림);
        $칠 = 0;

        for ($y = 0; $y < $h && $칠 < 30; $y += 2) {
            for ($x = 0; $x < $w && $칠 < 30; $x += 2) {
                if ((imagecolorat($그림, $x, $y) >> 24) < 100) {
                    $칠++;
                }
            }
        }
        imagedestroy($그림);

        return $칠 >= 30 ? $바이트 : null;
    }
}
