<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use App\Models\ServiceRequest;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Agent 가 보낸 결과를 받아 적는 자리 (2026-10-02 지시).
 *
 * 두 서버가 **서로 다른 DB** 를 쓴다. 오류 기록과 SR 은 운영(75.2.99.52)에 있고
 * Agent 는 www.ceadmin.co.kr 에서 돈다 — 그래서 Agent 가 그 줄을 직접 고칠 수
 * 없다. 보내는 길(AgentNotifier → /agent/hook)의 반대 방향이 이 자리다.
 *
 * 열쇠와 서명을 가리는 잣대는 받는 자리와 같다. **같은 열쇠를 쓴다** — 양쪽
 * 설정에 같은 값을 넣어 두어야 한다.
 *
 * 하는 일은 둘뿐이다.
 *   · SR 에 해결 내용을 적고 상태를 옮긴다 (sr.answer)
 *   · 오류 기록에 분석을 적고 상태를 옮긴다 (error.memo)
 *
 * 코드를 고치거나 배포하는 일은 여기서 하지 않는다. 그 일은 Agent 쪽에서 하고,
 * 결과만 이 자리로 돌아온다.
 */
class AgentReplyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $열쇠 = (string) config('services.agent.token');

        if ($열쇠 === '') {
            return response()->json(['success' => false, 'message' => '열쇠가 설정되지 않았습니다'], 503);
        }

        $글 = $request->getContent();

        if (! hash_equals($열쇠, (string) $request->header('X-Agent-Token', ''))
            || ! hash_equals(hash_hmac('sha256', $글, $열쇠), (string) $request->header('X-Agent-Sign', ''))) {
            $this->남긴다($request, 'rejected', '열쇠나 서명이 맞지 않습니다', 401);

            return response()->json(['success' => false, 'message' => '열쇠나 서명이 맞지 않습니다'], 401);
        }

        $몸 = json_decode($글, true);

        if (! is_array($몸) || ! isset($몸['event'])) {
            return response()->json(['success' => false, 'message' => '짐을 읽을 수 없습니다'], 422);
        }

        $갈래 = (string) $몸['event'];
        $짐   = (array) ($몸['data'] ?? []);

        $결과 = match ($갈래) {
            'sr.answer'  => $this->sr에적는다($짐),
            'error.memo' => $this->오류에적는다($짐),
            default      => ['ok' => false, 'message' => "모르는 갈래입니다 ({$갈래})"],
        };

        $this->남긴다($request, $갈래, $결과['message'], $결과['ok'] ? 200 : 422);

        return response()->json([
            'success' => $결과['ok'],
            'message' => $결과['message'],
        ], $결과['ok'] ? 200 : 422);
    }

    /**
     * SR 에 해결 내용을 적고 상태를 옮긴다.
     *
     * 담당자가 이미 답을 적어 두었으면 덮지 않는다 — 사람이 적은 글이 기계의
     * 글보다 뒤에 와야 할 까닭이 없다.
     */
    private function sr에적는다(array $짐): array
    {
        $sr = ServiceRequest::find($짐['id'] ?? 0);

        if (! $sr) {
            return ['ok' => false, 'message' => 'SR 을 찾지 못했습니다'];
        }

        if (trim((string) $sr->answer) !== '') {
            return ['ok' => false, 'message' => '이미 답변이 적혀 있어 덮지 않았습니다'];
        }

        $답 = trim((string) ($짐['answer'] ?? ''));

        if ($답 === '') {
            return ['ok' => false, 'message' => '적을 내용이 없습니다'];
        }

        $상태 = (string) ($짐['status'] ?? 'in_progress');

        $sr->forceFill([
            'answer'      => \App\Support\RichText::정리($답),
            'status'      => array_key_exists($상태, ServiceRequest::STATUSES) ? $상태 : 'in_progress',
            'answered_at' => now(),
            // 사람이 아니라 Agent 가 적었다 — 적은 사람 자리는 비워 둔다
            'answered_by' => null,
        ])->save();

        activity()->performedOn($sr)->log('Agent 가 답변을 적었습니다');

        return ['ok' => true, 'message' => "SR {$sr->id} 에 적었습니다"];
    }

    /** 오류 기록에 분석을 적고 상태를 옮긴다 */
    private function 오류에적는다(array $짐): array
    {
        $기록 = ErrorLog::find($짐['error_log_id'] ?? 0);

        if (! $기록) {
            return ['ok' => false, 'message' => '오류 이력을 찾지 못했습니다'];
        }

        $글 = trim((string) ($짐['memo'] ?? ''));

        if ($글 === '') {
            return ['ok' => false, 'message' => '적을 내용이 없습니다'];
        }

        /* 사람이 적어 둔 메모를 지우지 않는다 — 아래에 덧붙인다 */
        $예전 = trim((string) ($기록->memo ?? ''));
        $상태 = (string) ($짐['status'] ?? 'checked');

        /* **이 칸은 500자뿐이다** (varchar(500) · 2026-10-02 확인). 넘겨 담으면
           「Data too long for column 'memo'」로 터지고, 그 500 이 다시 Agent 로
           넘어가 같은 일이 되풀이된다. 사람이 적어 둔 글을 앞에 두고 뒤를 자른다. */
        $엮음 = $예전 === '' ? $글 : $예전 . "\n\n" . $글;

        if (mb_strlen($엮음) > 500) {
            $엮음 = mb_substr($엮음, 0, 497) . '...';
        }

        $기록->forceFill([
            'memo'       => $엮음,
            'status'     => array_key_exists($상태, ErrorLog::상태) ? $상태 : 'checked',
            'checked_at' => now(),
        ])->save();

        return ['ok' => true, 'message' => "오류 이력 {$기록->id} 에 적었습니다"];
    }

    /** 들어온 회신도 자취에 남긴다 */
    private function 남긴다(Request $request, string $갈래, string $글, int $status): void
    {
        try {
            WebhookLog::create([
                'provider'     => 'agent',
                'event_code'   => $갈래,
                'direction'    => 'in',
                'url'          => $request->fullUrl(),
                'http_method'  => $request->method(),
                'ok'           => $status === 200,
                'http_status'  => $status,
                'signature_ok' => $status !== 401,
                'response'     => $글,
                'ip'           => $request->ip(),
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Agent] 회신 자취를 남기지 못했습니다', ['error' => $e->getMessage()]);
        }
    }
}
