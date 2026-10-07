<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 운영에서 보낸 오류ㆍSR 을 받는 자리 (2026-10-02 지시).
 *
 * 보내는 쪽은 `App\Support\AgentNotifier` 다. 같은 코드가 두 서버에 올라가 있어,
 * **운영(75.2.99.52)이 보내고 이 자리(www.ceadmin.co.kr)가 받는다.**
 *
 * 받는 일만 한다 — 열쇠를 가리고, 자취를 남기고, 200 으로 답한다. 소스를 읽고
 * 고치는 일은 이 뒤에 붙는 Agent 작업자가 한다. 받는 자리를 먼저 세우는 까닭은,
 * 짐이 제대로 오는지부터 눈으로 볼 수 있어야 하기 때문이다.
 *
 * **열쇠가 없으면 받지 않는다.** 주소만 알면 아무나 짐을 넣을 수 있는 자리가
 * 되어서는 안 된다 — 들어온 짐은 사람이 보는 화면에 서고, 나중에는 Agent 가 그
 * 말을 믿고 코드를 고친다.
 */
class AgentHookController extends Controller
{
    /** 받는 짐의 크기 한도 — 캡처가 박힌 SR 글이 커질 수 있다 */
    private const 최대바이트 = 2_000_000;

    public function __invoke(Request $request): JsonResponse
    {
        $열쇠 = (string) config('services.agent.token');

        if ($열쇠 === '') {
            return $this->거절($request, '열쇠가 설정되지 않았습니다', 503);
        }

        $글 = $request->getContent();

        if (strlen($글) > self::최대바이트) {
            return $this->거절($request, '짐이 너무 큽니다', 413);
        }

        /* 머리글의 열쇠와, 짐 전체를 그 열쇠로 서명한 값을 함께 본다.
           열쇠만 보면 중간에서 짐이 바뀐 것을 가릴 수 없다. */
        $머리열쇠 = (string) $request->header('X-Agent-Token', '');
        $서명     = (string) $request->header('X-Agent-Sign', '');
        $바른서명 = hash_hmac('sha256', $글, $열쇠);

        if (! hash_equals($열쇠, $머리열쇠) || ! hash_equals($바른서명, $서명)) {
            return $this->거절($request, '열쇠나 서명이 맞지 않습니다', 401);
        }

        $몸 = json_decode($글, true);

        if (! is_array($몸) || ! isset($몸['event'])) {
            return $this->거절($request, '짐을 읽을 수 없습니다', 422);
        }

        $갈래 = (string) $몸['event'];
        $짐   = (array) ($몸['data'] ?? []);
        $자리 = (string) ($몸['site'] ?? '');

        /* 들어온 짐을 그대로 남긴다 — Agent 작업자가 이 자취를 읽어 일한다.
           SR 글에 캡처가 박혀 있을 수 있어 크기를 적어 두고, 글은 그대로 담는다. */
        $기록 = WebhookLog::create([
            'provider'     => 'agent',
            'event_code'   => $갈래,
            'direction'    => 'in',
            'url'          => $request->fullUrl(),
            'http_method'  => $request->method(),
            'ok'           => true,
            'http_status'  => 200,
            'signature_ok' => true,
            'headers'      => ['site' => $자리, 'bytes' => strlen($글)],
            'payload'      => $글,
            'ip'           => $request->ip(),
            'ref'          => $this->가리킴($갈래, $짐),
            'occurred_at'  => now(),
        ]);

        Log::info('[Agent] 짐을 받았습니다', [
            'event' => $갈래,
            'ref'   => $기록->ref,
            'site'  => $자리,
            'bytes' => strlen($글),
        ]);

        $this->일을띄운다($기록->id, $갈래);

        /* 받았다는 것만 답한다. 분석은 뒤에 붙는 작업자가 하고, 그 결과는 SR 의
           답변과 상태로 돌아간다 — 보내는 쪽은 그것을 기다리지 않는다. */
        return response()->json([
            'success'  => true,
            'received' => $기록->id,
        ]);
    }

    /**
     * 받은 그 자리에서 작업자를 띄운다 (2026-10-02 지시).
     *
     * 스케줄로 1분마다 빈 표를 들여다보지 않는다 — 웹훅이 들어온 자리가 그 줄
     * 번호를 들고 작업자를 띄운다.
     *
     * **기다리지 않는다.** Claude 는 1~2분이 걸리고, 보내는 쪽은 3초에 끊는다 —
     * 여기서 기다리면 운영 쪽 웹훅이 늘 「보내지 못했습니다」가 된다. 그래서
     * 명령을 떼어 내보내고(`&`) 우리는 곧바로 200 으로 답한다.
     *
     * 열쇠가 없으면 띄우지 않는다. 띄워도 아무 일도 하지 않을 것을 알기 때문이다.
     */
    private function 일을띄운다(int $자취번호, string $갈래): void
    {
        if (! in_array($갈래, ['error.raised', 'sr.created'], true)) {
            return;
        }

        if ((string) config('services.agent.api_key') === '') {
            return;
        }

        /* 줄에 쌓는다 — 일꾼이 차례로 집는다. 여러 건이 한꺼번에 와도 동시에
           터지지 않고, 어긋나면 다시 시도하고, 끝내 안 되면 failed_jobs 에 남는다
           (2026-10-07 지시). */
        try {
            \App\Jobs\AgentWorkJob::dispatch($자취번호);
        } catch (\Throwable $e) {
            /* 줄 자체를 쓸 수 없는 때(설정이 어긋났을 때)다 — 옛 길로 떼어 띄운다.
               아무 일도 안 하는 것보다 낫다. */
            Log::warning('[Agent] 줄에 쌓지 못해 떼어 띄웁니다', [
                'log'   => $자취번호,
                'error' => $e->getMessage(),
            ]);

            $this->떼어띄운다("agent:work --log={$자취번호}");

            return;
        }

        /* **일꾼이 죽어 있으면 줄만 길어지고 아무 일도 일어나지 않는다.** 웹훅은
           200 으로 받아 놓고 조용히 멈추는 것이 가장 나쁘다 — 집히지 않은 일거리가
           다섯 분을 넘겼으면 일꾼이 없다고 보고, 한 번 돌고 끝나는 일꾼을 떼어
           띄워 줄을 비운다. 줄에서 집을 때 자리를 잡으므로(reserved) 두 번 일하는
           일은 없다. */
        if ($this->밀려있나()) {
            Log::warning('[Agent] 줄이 밀려 있습니다 — 일꾼을 보십시오', ['log' => $자취번호]);

            $this->떼어띄운다('queue:work --queue=agent --stop-when-empty --tries=3 --timeout=600');
        }
    }

    /** 집히지 않은 일거리가 오래 묵었나 — 일꾼이 죽었다는 낌새다 */
    private function 밀려있나(): bool
    {
        try {
            if (config('queue.default') !== 'database') {
                return false;
            }

            $오래된 = \Illuminate\Support\Facades\DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('queue', 'agent')
                ->min('available_at');

            return $오래된 !== null && (int) $오래된 < now()->subMinutes(5)->getTimestamp();
        } catch (\Throwable) {
            // 표를 못 읽으면 괜한 일을 벌이지 않는다
            return false;
        }
    }

    /**
     * artisan 명령을 떼어 내보낸다 — 이 요청이 끝나도 그쪽은 계속 돈다.
     *
     * FPM 에서 PHP_BINARY 는 php-fpm 을 가리킨다 — CLI 를 따로 적어야 한다. 값은
     * 설정에서 고칠 수 있게 둔다(서버마다 자리가 다를 수 있다).
     */
    private function 떼어띄운다(string $명령): void
    {
        $php = (string) config('services.agent.php_bin', '/usr/bin/php');

        try {
            exec(sprintf(
                '%s %s %s > /dev/null 2>&1 &',
                escapeshellarg($php),
                escapeshellarg(base_path('artisan')),
                $명령
            ));
        } catch (\Throwable $e) {
            Log::warning('[Agent] 작업자를 띄우지 못했습니다', [
                'command' => $명령,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /** 무엇에 대한 짐인가 — 「sr:12」ㆍ「error:340」 */
    private function 가리킴(string $갈래, array $짐): ?string
    {
        return match ($갈래) {
            'sr.created'    => isset($짐['id']) ? 'sr:' . $짐['id'] : null,
            'error.raised'  => isset($짐['error_log_id']) ? 'error:' . $짐['error_log_id'] : null,
            default         => null,
        };
    }

    /** 받지 않은 짐도 남긴다 — 누가 어떤 까닭으로 막혔는지 보여야 한다 */
    private function 거절(Request $request, string $까닭, int $status): JsonResponse
    {
        try {
            WebhookLog::create([
                'provider'     => 'agent',
                'event_code'   => 'rejected',
                'direction'    => 'in',
                'url'          => $request->fullUrl(),
                'http_method'  => $request->method(),
                'ok'           => false,
                'http_status'  => $status,
                'signature_ok' => false,
                'error'        => $까닭,
                'ip'           => $request->ip(),
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Agent] 거절 자취를 남기지 못했습니다', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => false, 'message' => $까닭], $status);
    }
}
