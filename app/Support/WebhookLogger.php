<?php

namespace App\Support;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 오간 웹훅을 적는다 (2026-09-10 지시).
 *
 * 적는 일이 본디 하려던 일을 방해하지 않는다 — 여기서 나는 어떤 오류도 삼킨다.
 * 로그를 못 남겨 결제 처리가 통째로 되돌아가면 그것이 더 큰일이다.
 */
final class WebhookLogger
{
    /**
     * 받은 것을 적는다.
     *
     * 쓰는 법 — 받자마자 한 줄 열어 두고(줄이 남는다), 다 끝난 뒤 그 줄에 결과를 적는다.
     *   $log = WebhookLogger::inbound('toss', $eventType, $request);
     *   … 처리 …
     *   WebhookLogger::finish($log, ok: true, status: 200, response: [...], ref: '주문번호');
     */
    public static function inbound(string $provider, ?string $eventCode, Request $request, ?bool $signatureOk = null): ?WebhookLog
    {
        try {
            $본문 = json_decode($request->getContent(), true);

            return WebhookLog::create([
                'webhook_id'   => Webhook::찾기($provider, $eventCode, 'inbound')?->id,
                'provider'     => $provider,
                'event_code'   => $eventCode,
                'direction'    => 'inbound',
                'url'          => $request->path(),
                'http_method'  => $request->method(),
                'ok'           => false,          // 끝나면 고쳐 적는다
                'signature_ok' => $signatureOk,
                'headers'      => WebhookLog::가리기(self::머리($request)),
                'payload'      => self::글로(is_array($본문) ? WebhookLog::가리기($본문) : $request->getContent()),
                'ip'           => $request->ip(),
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[웹훅] 받은 것을 적지 못했습니다', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 보낸 것을 적는다 — 팝빌ㆍNICE 처럼 우리가 부르는 쪽이다.
     */
    public static function outbound(
        string $provider,
        ?string $eventCode,
        string $url,
        mixed $payload,
        bool $ok,
        ?int $status = null,
        mixed $response = null,
        ?string $error = null,
        ?int $durationMs = null,
        ?string $ref = null,
        string $method = 'POST',
    ): ?WebhookLog {
        try {
            return WebhookLog::create([
                'webhook_id'  => Webhook::찾기($provider, $eventCode, 'outbound')?->id,
                'provider'    => $provider,
                'event_code'  => $eventCode,
                'direction'   => 'outbound',
                'url'         => $url,
                'http_method' => $method,
                'ok'          => $ok,
                'http_status' => $status,
                'payload'     => self::글로(is_array($payload) ? WebhookLog::가리기($payload) : $payload),
                'response'    => self::글로(is_array($response) ? WebhookLog::가리기($response) : $response),
                'error'       => $error,
                'duration_ms' => $durationMs,
                'ref'         => $ref,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[웹훅] 보낸 것을 적지 못했습니다', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** 다 끝난 뒤 그 줄에 결과를 적는다 */
    public static function finish(
        ?WebhookLog $log,
        bool $ok,
        ?int $status = null,
        mixed $response = null,
        ?string $error = null,
        ?string $ref = null,
        ?bool $signatureOk = null,
    ): void {
        if (! $log) {
            return;
        }

        try {
            /* 성공 여부는 false 도 적어야 하므로 거르지 않는다.
               나머지는 값이 있을 때만 덮는다 — 열어 둘 때 적어 둔 것을 지우지 않는다. */
            $고칠것 = ['ok' => $ok];

            foreach ([
                'http_status'  => $status,
                'error'        => $error,
                'ref'          => $ref,
                'signature_ok' => $signatureOk,
            ] as $칸 => $값) {
                if ($값 !== null) {
                    $고칠것[$칸] = $값;
                }
            }

            if ($response !== null) {
                $고칠것['response'] = self::글로(is_array($response) ? WebhookLog::가리기($response) : $response);
            }

            if ($log->occurred_at) {
                $고칠것['duration_ms'] = (int) max(0, $log->occurred_at->diffInMilliseconds(now()));
            }

            $log->update($고칠것);
        } catch (\Throwable $e) {
            Log::warning('[웹훅] 결과를 적지 못했습니다', ['id' => $log->id, 'error' => $e->getMessage()]);
        }
    }

    /** 볼 만한 머리만 골라 담는다 — 다 담으면 읽히지 않는다 */
    private static function 머리(Request $request): array
    {
        $쓸것 = [
            'content-type', 'user-agent', 'x-forwarded-for',
            'tosspayments-webhook-signature', 'tosspayments-webhook-transmission-time',
            'x-signature', 'authorization',
        ];

        $담을것 = [];
        foreach ($쓸것 as $이름) {
            $값 = $request->header($이름);
            if ($값 !== null && $값 !== '') {
                $담을것[$이름] = $값;
            }
        }

        return $담을것;
    }

    /** 무엇이 오든 글로 만든다 — 표에는 글로 담는다 */
    private static function 글로(mixed $값): ?string
    {
        if ($값 === null) {
            return null;
        }

        if (is_string($값)) {
            return mb_substr($값, 0, 60000);
        }

        return mb_substr((string) json_encode($값, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 60000);
    }
}
