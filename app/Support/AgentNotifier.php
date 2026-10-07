<?php

namespace App\Support;

use App\Models\ServiceRequest;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 오류와 SR 을 Agent 에게 넘긴다 (2026-10-02 지시).
 *
 * **설정에서 켜야만 나간다.** 쓰는 자리를 둘로 갈라 두었다 (2026-10-02 지시) —
 * 환경 설정 › Agent 연계의 「SR 관리 — Agent 사용」과 「오류 기록 — Agent 사용」이다.
 * 한쪽만 켜고 다른 쪽은 닫아 둘 수 있고, 주소와 열쇠까지 채워야 열린다.
 * 기본은 둘 다 꺼짐이다 — 이 길로 나가는 짐에는 담당자가 적은 글과 붙여넣은 화면
 * 캡처가 실리고, 그 캡처에는 환자 이름ㆍ주민등록번호가 보일 수 있다.
 *
 * 보내는 일이 본래 일을 막지 않는다. SR 등록도 오류 기록도 이 자리가 실패해도
 * 그대로 끝난다 — 짐을 못 보낸 것은 로그와 웹훅 기록에만 남는다.
 */
class AgentNotifier
{
    /** 같은 잘못을 하루 한 번만 보낸다 — 한 번 어긋나면 같은 줄이 수천 번 난다 */
    private const 오류_묶는시간 = 86400;

    /** 저쪽이 늦어도 우리 일이 늦지 않게 — 짧게 끊는다 */
    private const 제한초 = 3;

    // ── 밖에서 부르는 자리 ────────────────────────────────

    /** SR 이 등록되었다 */
    public static function sr등록(ServiceRequest $sr): void
    {
        if (! self::쓰는가('sr')) {
            return;
        }

        self::보낸다('sr.created', [
            'id'         => $sr->id,
            'title'      => $sr->title,
            'content'    => self::글만($sr->content),
            'category'   => $sr->category,
            'priority'   => $sr->priority,
            'status'     => $sr->status,
            /* 어느 화면에서 적은 것인지 — Agent 가 소스에서 그 자리를 찾는 실마리다 */
            'page_label' => $sr->page_label,
            'page_url'   => $sr->page_url,
            'user'       => $sr->user?->only(['id', 'name', 'email']),
            'created_at' => $sr->created_at?->toIso8601String(),
        ], ref: "sr:{$sr->id}");
    }

    /** 서버에서 잘못이 났다 — ErrorRecorder 가 담은 뒤에 부른다 */
    public static function 오류(Throwable $e, ?int $errorLogId = null): void
    {
        if (! self::쓰는가('error')) {
            return;
        }

        /* **우리가 멈춘 것은 우리에게 보내지 않는다** (2026-10-07 확인).
           Agent 가 일하다 멈추면 그 잘못이 오류 기록에 담기고, 담기면 또 Agent 에게
           넘어가 또 Claude 를 부른다. 묶기(하루 한 번)가 있어 돌지는 않았지만, 자기
           실패를 자기가 분석하는 일거리가 실제로 하나 더 생겼다. */
        if (self::우리가멈춘것인가($e)) {
            return;
        }

        /* 같은 잘못은 하루 한 번. 자리(파일:줄)와 갈래로 묶는다 — 메시지에 번호나
           이름이 섞여 들어가는 잘못이 많아, 메시지까지 넣으면 묶이지 않는다. */
        $열쇠 = 'agent:error:' . substr(hash('sha256', $e::class . '|' . $e->getFile() . ':' . $e->getLine()), 0, 24);

        if (! Cache::add($열쇠, 1, self::오류_묶는시간)) {
            return;
        }

        self::보낸다('error.raised', [
            'error_log_id' => $errorLogId,
            'class'        => $e::class,
            'message'      => $e->getMessage(),
            'file'         => $e->getFile(),
            'line'         => $e->getLine(),
            /* 자취는 우리 코드만 — vendor 를 다 보내면 짐이 커지고 읽을 거리가 묻힌다 */
            'trace'        => self::우리자취($e),
            'url'          => request()?->fullUrl(),
            'method'       => request()?->method(),
            'route'        => request()?->route()?->getName(),
            'user_id'      => auth()->id(),
            'occurred_at'  => now()->toIso8601String(),
        ], ref: $errorLogId ? "error:{$errorLogId}" : null);
    }

    // ── 안쪽 ─────────────────────────────────────────────

    /** Agent 자신이 멈춘 것인가 — 그 자리에서 난 잘못은 넘기지 않는다 */
    private static function 우리가멈춘것인가(Throwable $e): bool
    {
        $자리 = str_replace('\\', '/', $e->getFile());

        foreach ([
            'app/Services/AgentWorker.php',
            'app/Services/AgentFixer.php',
            'app/Jobs/AgentWorkJob.php',
            'app/Support/AgentNotifier.php',
            'app/Http/Controllers/AgentHookController.php',
            'app/Http/Controllers/AgentReplyController.php',
            'app/Console/Commands/AgentWork',
        ] as $우리자리) {
            if (str_contains($자리, $우리자리)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 보낼 자리인가 — 켜졌는지, 주소와 열쇠가 있는지.
     *
     * 열쇠가 없으면 보내지 않는다. 주소만 맞는 아무 데로나 환자 자료가 나가는 일을
     * 막는 잣대다.
     */
    private static function 쓰는가(string $갈래): bool
    {
        /* 쓰는 자리가 둘이다 — SR 관리와 오류 기록을 따로 켠다 (2026-10-02 지시).
           한쪽만 켜고 다른 쪽은 닫아 둘 수 있다. */
        $열쇠칸 = $갈래 === 'sr' ? 'sr_enabled' : 'error_enabled';

        if (! config("services.agent.{$열쇠칸}", false)) {
            return false;
        }

        return (bool) config('services.agent.url') && (bool) config('services.agent.token');
    }

    /**
     * 글 안의 그림을 뗀다 (설정이 꺼져 있을 때).
     *
     * SR 은 Quill 이라 붙여넣은 화면 캡처가 `data:image/…;base64,…` 로 글 안에
     * 박힌다. 그 캡처에 환자 이름ㆍ주민등록번호가 보일 수 있어, 켜지 않으면 뗀다.
     * 뗀 자리에 무엇이 있었는지는 남겨 둔다 — Agent 가 「그림이 있었다」는 것은
     * 알아야 사람에게 되물을 수 있다.
     */
    private static function 글만(?string $글): string
    {
        $글 = (string) $글;

        if (config('services.agent.send_image', false)) {
            return $글;
        }

        return preg_replace('~<img\b[^>]*>~i', '[화면 캡처 — 보내지 않음]', $글) ?? $글;
    }

    /** 우리 코드에서 난 자리만 골라 스무 줄까지 */
    private static function 우리자취(Throwable $e): array
    {
        $뿌리 = base_path();

        return collect($e->getTrace())
            ->filter(fn ($줄) => isset($줄['file'])
                && str_starts_with($줄['file'], $뿌리)
                && ! str_contains($줄['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR))
            ->take(20)
            ->map(fn ($줄) => [
                'file'     => str_replace($뿌리 . DIRECTORY_SEPARATOR, '', $줄['file']),
                'line'     => $줄['line'] ?? null,
                'function' => ($줄['class'] ?? '') . ($줄['type'] ?? '') . ($줄['function'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /** 보내고, 보낸 자취를 웹훅 기록에 남긴다 */
    private static function 보낸다(string $갈래, array $짐, ?string $ref = null): void
    {
        $주소 = (string) config('services.agent.url');
        $열쇠 = (string) config('services.agent.token');

        $몸 = ['event' => $갈래, 'site' => config('app.url'), 'data' => $짐];
        $글 = json_encode($몸, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $시작 = microtime(true);
        $답 = null;
        $잘못 = null;

        try {
            $답 = Http::withHeaders([
                    'X-Agent-Token' => $열쇠,
                    /* 열쇠를 머리글로 보내는 것만으로는 중간에서 바뀐 짐을 가릴 수 없다 —
                       짐 전체를 열쇠로 서명해 함께 보낸다(받는 쪽이 같은 셈을 한다). */
                    'X-Agent-Sign'  => hash_hmac('sha256', $글, $열쇠),
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(self::제한초)
                ->connectTimeout(self::제한초)
                ->withBody($글, 'application/json')
                ->post($주소);
        } catch (Throwable $e) {
            $잘못 = $e->getMessage();
            Log::warning('[Agent] 보내지 못했습니다', ['event' => $갈래, 'error' => $잘못]);
        }

        try {
            WebhookLog::create([
                'provider'     => 'agent',
                'event_code'   => $갈래,
                'direction'    => 'out',
                'url'          => $주소,
                'http_method'  => 'POST',
                'ok'           => (bool) ($답?->successful()),
                'http_status'  => $답?->status(),
                /* 짐은 그대로 담지 않는다 — 글과 캡처가 다시 한 벌 쌓인다.
                   무엇을 보냈는지 가릴 만큼만 남긴다. */
                'payload'      => json_encode([
                    'event' => $갈래,
                    'ref'   => $ref,
                    'bytes' => strlen($글),
                ], JSON_UNESCAPED_UNICODE),
                'response'     => $답 ? mb_substr((string) $답->body(), 0, 1000) : null,
                'error'        => $잘못,
                'duration_ms'  => (int) ((microtime(true) - $시작) * 1000),
                'ref'          => $ref,
                'occurred_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[Agent] 자취를 남기지 못했습니다', ['error' => $e->getMessage()]);
        }
    }
}
