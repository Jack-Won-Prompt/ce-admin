<?php

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agent 작업자 — 받은 짐을 읽고 Claude 에게 물어 결과를 돌려보낸다 (2026-10-02 지시).
 *
 * 돌아가는 자리는 www.ceadmin.co.kr 이다. 받은 짐은 `webhook_logs` 에 담겨 있고
 * (provider=agent · direction=in), 아직 손대지 않은 줄은 `response` 가 비어 있다 —
 * 일을 마치면 그 자리에 한 줄 적어 두는 것으로 「끝냈다」를 표시한다. 표를 새로
 * 만들지 않은 까닭은, 받은 것과 한 일이 한자리에 있어야 사람이 따라 읽기 쉽기
 * 때문이다.
 *
 * ## 이 자리가 하는 일과 하지 않는 일
 *
 * 한다 — 짐을 읽고, 관련된 소스를 떠서 함께 보여 주고, 원인과 고칠 자리를 받아
 * 보내 온 쪽(운영)에 되돌려 적는다(`/agent/reply`).
 *
 * **하지 않는다 — 코드를 고치거나 배포하지 않는다.** 고치는 일은 다음 걸음으로
 * 따로 둔다. 돈(결제)ㆍ국세청 신고ㆍ주민등록번호ㆍ표 바꾸기(마이그레이션)에
 * 닿는 것은 애초에 사람이 봐야 한다고 적어 보낸다.
 *
 * ## 열쇠
 *
 * Claude 열쇠와 모델은 **설정(환경 설정 › Agent 연계)** 에서 읽는다. .env 에 두지
 * 않는 까닭은 서버를 만지지 않고 바꿀 수 있어야 하기 때문이다. 열쇠가 없으면
 * 아무 일도 하지 않고 조용히 지나간다 — 켜 두기만 하고 열쇠를 넣지 않은 동안
 * 로그가 잘못으로 가득 차면 안 된다.
 */
class AgentWorker
{
    /** 한 번에 몇 건까지 — 값이 비싸서 천천히 돈다 */
    public const 한번에 = 3;

    /** Claude 에게 주는 시간 */
    private const 제한초 = 120;

    /** 되돌려 적을 수 있는 자리 — 짐에 적힌 주소를 그대로 믿지 않는다 */
    private const 아는자리 = [
        'https://75.2.99.52',
        'https://www.ceadmin.co.kr',
    ];

    /** 사람이 봐야 하는 갈래 — 이 말이 돌아오면 고치는 쪽으로 넘기지 않는다 */
    public const 사람몫 = ['money', 'nts', 'personal', 'migration'];

    /**
     * 손대지 않은 짐을 집어 일한다.
     *
     * @return array{처리:int, 건너뜀:int, 글:array<string>}
     */
    public function 돌린다(int $한번에 = self::한번에): array
    {
        $열쇠 = (string) config('services.agent.api_key');

        if ($열쇠 === '') {
            return ['처리' => 0, '건너뜀' => 0, '글' => ['Claude 열쇠가 설정되지 않아 지나갑니다']];
        }

        $줄들 = WebhookLog::where('provider', 'agent')
            ->where('direction', 'in')
            ->whereIn('event_code', ['error.raised', 'sr.created'])
            ->whereNull('response')
            ->oldest('id')
            ->take($한번에)
            ->get();

        $글 = [];
        $처리 = 0;
        $건너뜀 = 0;

        foreach ($줄들 as $줄) {
            try {
                $한말 = $this->한건($줄, $열쇠);
                $처리++;
            } catch (Throwable $e) {
                $한말 = '일하다 멈췄습니다 — ' . $e->getMessage();
                $건너뜀++;
                Log::warning('[Agent] 일하다 멈췄습니다', ['log' => $줄->id, 'error' => $e->getMessage()]);
            }

            /* 끝냈다는 표시 — 이 자리가 비어 있는 줄만 다시 집는다 */
            $줄->forceFill(['response' => mb_substr($한말, 0, 1000)])->save();
            $글[] = "#{$줄->id} {$줄->event_code} — {$한말}";
        }

        return ['처리' => $처리, '건너뜀' => $건너뜀, '글' => $글];
    }

    // ── 한 건 ────────────────────────────────────────────

    private function 한건(WebhookLog $줄, string $열쇠): string
    {
        $몸 = json_decode((string) $줄->payload, true);
        $짐 = (array) ($몸['data'] ?? []);
        $자리 = (string) ($몸['site'] ?? '');

        if (! in_array($자리, self::아는자리, true)) {
            return "모르는 자리에서 온 짐입니다 ({$자리})";
        }

        [$물음, $소스] = $줄->event_code === 'error.raised'
            ? $this->오류물음($짐)
            : $this->sr물음($짐);

        $답 = $this->묻는다($열쇠, $물음, $소스);

        return $줄->event_code === 'error.raised'
            ? $this->오류회신($자리, $짐, $답)
            : $this->sr회신($자리, $짐, $답);
    }

    // ── 물음 짓기 ────────────────────────────────────────

    /** @return array{0:string, 1:string} 물음과 함께 보일 소스 */
    private function 오류물음(array $짐): array
    {
        $줄거리 = collect($짐['trace'] ?? [])
            ->map(fn ($t) => '  ' . ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?') . ' — ' . ($t['function'] ?? ''))
            ->implode("\n");

        $물음 = <<<GLU
        운영에서 난 잘못입니다. 원인과 고칠 자리를 짚어 주십시오.

        갈래   : {$짐['class']}
        글월   : {$짐['message']}
        자리   : {$짐['file']}:{$짐['line']}
        주소   : {$짐['url']}
        라우트 : {$짐['route']}

        자취(우리 코드만):
        {$줄거리}
        GLU;

        return [$물음, $this->소스를뜬다((string) ($짐['file'] ?? ''), (int) ($짐['line'] ?? 0))];
    }

    /** @return array{0:string, 1:string} */
    private function sr물음(array $짐): array
    {
        $글 = strip_tags((string) ($짐['content'] ?? ''));

        $물음 = <<<GLU
        담당자가 올린 요청(SR)입니다. 무엇을 어떻게 고치면 되는지 짚어 주십시오.

        제목   : {$짐['title']}
        갈래   : {$짐['category']} / 급함 {$짐['priority']}
        적은 화면: {$짐['page_label']} ({$짐['page_url']})

        내용:
        {$글}
        GLU;

        return [$물음, ''];
    }

    /**
     * 그 파일의 그 줄 앞뒤를 뜬다.
     *
     * 짐에 적힌 자리는 운영 서버의 경로(/opt/ce-admin/…)다. 우리 저장소의 같은
     * 파일을 찾아 띄워 준다 — 파일 전체를 보내면 짐이 커지고, 읽을 곳이 묻힌다.
     */
    private function 소스를뜬다(string $자리, int $줄번호, int $앞뒤 = 40): string
    {
        $상대 = preg_replace('~^.*?(?=app/|routes/|resources/|config/|database/)~', '', str_replace('\\', '/', $자리));
        $길 = base_path((string) $상대);

        if ($상대 === '' || ! is_file($길)) {
            return '';
        }

        $줄들 = file($길, FILE_IGNORE_NEW_LINES);
        $처음 = max(0, $줄번호 - $앞뒤 - 1);
        $끝   = min(count($줄들) - 1, $줄번호 + $앞뒤 - 1);

        $뜬것 = [];
        for ($i = $처음; $i <= $끝; $i++) {
            $뜬것[] = sprintf('%5d| %s', $i + 1, $줄들[$i]);
        }

        return "— {$상대} ({$처음}~{$끝}줄) —\n" . implode("\n", $뜬것);
    }

    // ── Claude 에게 묻기 ─────────────────────────────────

    /**
     * 물음을 보내고 짜인 답을 받는다.
     *
     * 답은 **JSON 한 덩이**로 받는다 — 사람이 읽을 글을 그대로 받아 적으면,
     * 뒤에 고치는 걸음을 붙일 때 다시 글을 헤집어야 한다.
     */
    private function 묻는다(string $열쇠, string $물음, string $소스): array
    {
        $틀 = <<<'GLU'
        당신은 Laravel 11 + Flutter 로 된 의료기기 주문ㆍ청구 시스템(CE Admin)의
        코드를 보는 사람입니다. 이 시스템은 실제 결제(토스), 국세청 실신고(팝빌),
        환자 주민등록번호를 다룹니다.

        받은 내용을 읽고 **JSON 한 덩이만** 답하십시오. 설명을 덧붙이지 마십시오.

        {
          "원인": "무엇 때문에 이렇게 되었는가 — 두세 문장",
          "고칠자리": "파일과 줄, 또는 '모르겠음'",
          "고치는법": "무엇을 어떻게 바꾸면 되는가 — 두세 문장",
          "위험갈래": "money | nts | personal | migration | none",
          "사람확인필요": true | false,
          "확신": "높음 | 보통 | 낮음"
        }

        위험갈래는 고치는 자리가 결제ㆍ국세청 신고ㆍ환자 개인정보ㆍ표 구조(마이그레이션)에
        닿을 때 그 이름을 적습니다. 하나라도 닿으면 사람확인필요는 반드시 true 입니다.
        확신이 낮으면 사람확인필요를 true 로 두십시오.

        글은 모두 한국어로, 담당자가 읽을 말로 적으십시오.
        GLU;

        $답 = Http::withHeaders([
                'x-api-key'         => $열쇠,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->timeout(self::제한초)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => (string) config('services.agent.model', 'claude-opus-5'),
                /* 넉넉히 둔다 — 생각하는 덩이가 앞에 오고 그 길이도 이 셈에 든다.
                   좁게 두면 답이 중간에 끊겨 JSON 이 깨진다. */
                'max_tokens' => 4000,
                'system'     => $틀,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => $물음 . ($소스 !== '' ? "\n\n" . $소스 : ''),
                ]],
            ]);

        if ($답->failed()) {
            throw new \RuntimeException('Claude 가 답하지 않았습니다 — ' . $답->status() . ' ' . mb_substr($답->body(), 0, 200));
        }

        /* **첫 덩이가 글이 아니다.** Opus 5 는 생각하는 덩이(thinking)를 앞에 보내므로
           content[0].text 는 비어 있다 — 글 덩이만 골라 잇는다(2026-10-02 확인). */
        $글 = collect($답->json('content') ?? [])
            ->filter(fn ($덩이) => ($덩이['type'] ?? '') === 'text')
            ->pluck('text')
            ->implode("
");

        /* 앞뒤에 울타리(```)가 붙어 오는 때가 있다 — 가운데만 꺼낸다 */
        if (preg_match('~\{.*\}~s', $글, $m)) {
            $글 = $m[0];
        }

        $짜인것 = json_decode($글, true);

        if (! is_array($짜인것)) {
            $덩이들 = collect($답->json('content') ?? [])->pluck('type')->implode(', ');

            throw new \RuntimeException('답을 읽을 수 없습니다 — 받은 덩이: [' . $덩이들 . '] · 글: '
                . ($글 === '' ? '(빈 글)' : mb_substr($글, 0, 200)));
        }

        $짜인것['_쓴토큰'] = (int) ($답->json('usage.input_tokens') ?? 0)
                          + (int) ($답->json('usage.output_tokens') ?? 0);

        return $짜인것;
    }

    // ── 되돌려 적기 ──────────────────────────────────────

    private function 오류회신(string $자리, array $짐, array $답): string
    {
        $글 = $this->읽을글($답);

        $결과 = $this->보낸다($자리, 'error.memo', [
            'error_log_id' => $짐['error_log_id'] ?? null,
            'memo'         => $글,
            // 고치지는 않았다 — 「확인」까지만 옮긴다
            'status'       => 'checked',
        ]);

        return "오류 분석을 적었습니다 ({$결과}) · 토큰 " . ($답['_쓴토큰'] ?? 0);
    }

    private function sr회신(string $자리, array $짐, array $답): string
    {
        $글 = $this->읽을글($답);

        $결과 = $this->보낸다($자리, 'sr.answer', [
            'id'     => $짐['id'] ?? null,
            'answer' => $글,
            // 사람이 골라야 할 것이 남았으므로 「완료」로 옮기지 않는다
            'status' => 'in_progress',
        ]);

        return "SR 에 답을 적었습니다 ({$결과}) · 토큰 " . ($답['_쓴토큰'] ?? 0);
    }

    /** 담당자가 읽을 글로 엮는다 */
    private function 읽을글(array $답): string
    {
        $위험 = (string) ($답['위험갈래'] ?? 'none');
        $사람 = ! empty($답['사람확인필요']) || in_array($위험, self::사람몫, true);

        $갈래말 = [
            'money'     => '결제ㆍ환불',
            'nts'       => '국세청 신고',
            'personal'  => '환자 개인정보',
            'migration' => '표 구조 변경',
        ][$위험] ?? null;

        $줄 = [
            '[Agent 분석]',
            '',
            '■ 원인',
            (string) ($답['원인'] ?? '-'),
            '',
            '■ 고칠 자리',
            (string) ($답['고칠자리'] ?? '-'),
            '',
            '■ 고치는 법',
            (string) ($답['고치는법'] ?? '-'),
            '',
            '■ 확신: ' . ((string) ($답['확신'] ?? '-')),
        ];

        if ($갈래말) {
            $줄[] = '■ ' . $갈래말 . ' 에 닿는 고침입니다 — 사람이 보고 결정해 주십시오.';
        } elseif ($사람) {
            $줄[] = '■ 사람 확인이 필요합니다.';
        } else {
            $줄[] = '■ 자동으로 고쳐도 되는 갈래입니다.';
        }

        return implode("\n", $줄);
    }

    /** 보내 온 쪽에 되돌려 적는다 */
    private function 보낸다(string $자리, string $갈래, array $짐): string
    {
        $열쇠 = (string) config('services.agent.token');
        $몸 = ['event' => $갈래, 'site' => config('app.url'), 'data' => $짐];
        $글 = json_encode($몸, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $답 = Http::withHeaders([
                'X-Agent-Token' => $열쇠,
                'X-Agent-Sign'  => hash_hmac('sha256', $글, $열쇠),
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->withBody($글, 'application/json')
            ->post(rtrim($자리, '/') . '/agent/reply');

        return $답->successful()
            ? (string) ($답->json('message') ?? '적었습니다')
            : '되돌려 적지 못했습니다 — ' . $답->status();
    }
}
