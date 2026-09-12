<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM v1 API 전송 헬퍼 (Composer 추가 패키지 없이 순수 PHP openssl 사용)
 * 서비스 계정 JSON → JWT → OAuth2 액세스 토큰 → FCM v1 API 호출
 */
class FcmHelper
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * 단일 기기에 채팅 알림 전송
     */
    public static function sendChatMessage(
        string $fcmToken,
        string $senderName,
        string $body,
        int    $roomId,
        ?int   $userId = null
    ): bool {
        return self::send($fcmToken, $senderName, $body, [
            'type'    => 'chat',
            'room_id' => (string) $roomId,
        ], $userId);
    }

    /**
     * FCM v1 API로 알림 전송
     */
    /**
     * @param ?int $userId 받는 사람. 주면 보낸 자취를 남긴다(FcmNotification).
     *                     푸시는 알림창에서 지우면 사라져, 남겨 두지 않으면
     *                     무엇이 왔는지 다시 볼 길이 없다.
     */
    public static function send(
        string $fcmToken,
        string $title,
        string $body,
        array  $data = [],
        ?int   $userId = null
    ): bool {
        $sent = false;

        try {
            $accessToken = self::getAccessToken();
            $projectId   = self::getServiceAccount()['project_id'];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token'        => $fcmToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data'         => array_map('strval', $data),
                        'android'      => [
                            'priority'     => 'high',
                            'notification' => [
                                'channel_id'    => 'chat_messages',
                                /* FCM v1 의 android.notification 에는 priority 가 없다.
                                   notification_priority 에 PRIORITY_* 를 준다 — 여태
                                   priority:max 로 보내 400 INVALID_ARGUMENT 로 죄다
                                   튕겼다("Unknown name \"priority\"", 2026-09-12 확인).
                                   알림이 한 건도 나가지 않고 있었다. */
                                'notification_priority' => 'PRIORITY_MAX',
                                'default_sound' => true,
                            ],
                        ],
                        'apns' => [
                            'headers' => ['apns-priority' => '10'],
                            'payload' => [
                                'aps' => [
                                    'alert' => ['title' => $title, 'body' => $body],
                                    'sound' => 'default',
                                    'badge' => 1,
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('[FCM] 전송 실패', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'token'  => substr($fcmToken, 0, 20) . '...',
                ]);
            } else {
                $sent = true;
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] 오류', ['error' => $e->getMessage()]);
        }

        /* 보낸 자취를 남긴다. 실패한 것도 남긴다 — 「알림이 안 왔다」고 할 때
           보내려 했는지조차 모르면 짚을 데가 없다.
           남기다 잘못되어도 전송 결과를 바꾸지 않는다. */
        if ($userId !== null) {
            try {
                \App\Models\FcmNotification::create([
                    'user_id' => $userId,
                    'title'   => $title,
                    'body'    => $body,
                    'type'    => $data['type'] ?? null,
                    'payload' => $data,
                    'sent'    => $sent,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[FCM] 이력 기록 실패', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    // ── 내부 메서드 ──────────────────────────────────────────

    private static function getAccessToken(): string
    {
        // 액세스 토큰은 1시간 유효 — 58분 캐시
        return Cache::remember('fcm_access_token', 3480, function () {
            return self::generateAccessToken();
        });
    }

    private static function generateAccessToken(): string
    {
        $sa  = self::getServiceAccount();
        $now = time();

        $header  = self::b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = self::b64u(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => self::FCM_SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $unsigned   = "{$header}.{$payload}";
        $privateKey = openssl_pkey_get_private($sa['private_key']);
        openssl_sign($unsigned, $signature, $privateKey, 'sha256WithRSAEncryption');

        $jwt = $unsigned . '.' . self::b64u($signature);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        $token = $response->json('access_token');
        if (empty($token)) {
            throw new \RuntimeException('FCM 액세스 토큰 발급 실패: ' . $response->body());
        }

        return $token;
    }

    private static function getServiceAccount(): array
    {
        $path = storage_path('app/firebase/service-account.json');
        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Firebase 서비스 계정 파일 없음: ' . $path
            );
        }
        return json_decode(file_get_contents($path), true);
    }

    private static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
