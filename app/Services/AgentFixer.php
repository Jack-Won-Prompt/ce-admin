<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agent 가 고쳐서 내보내는 자리 (2026-10-02 지시).
 *
 * 분석까지는 AgentWorker 가 한다. 그 분석에 「자동으로 고쳐도 되는 갈래」라고
 * 적혔을 때만 이 자리가 이어 받는다.
 *
 * ## 어디서 고치나 — 사는 집을 건드리지 않는다
 *
 * `~/www/lcpoint` 은 www.ceadmin.co.kr 이 **지금 띄우고 있는** 코드다. 거기서
 * 브랜치를 바꿔 끼우면 그 사이 그 화면을 보는 사람이 시험 코드를 본다. 그래서
 * 따로 받아 둔 확인 자리(`~/www/agent-verify`)에서 고치고 검사한다.
 *
 * ## 걸음
 *
 *   ① 확인 자리를 main 으로 맞춘다
 *   ② Claude 에게 **통째 파일**을 받는다 — 조각 수정(diff)은 빗나가기 쉽다
 *   ③ 그 파일을 써 보고 `php -l` 로 문법을 본다
 *   ④ 바뀐 것이 한 파일ㆍ적은 줄인지 가린다 (크면 사람에게 넘긴다)
 *   ⑤ agent/… 브랜치로 커밋하고 main 에 합쳐 밀어 올린다 → Action 이 운영에 배포
 *
 * ## 절대 손대지 않는 것
 *
 * 마이그레이션ㆍ결제ㆍ국세청 신고ㆍ개인정보ㆍ설정ㆍ.env ㆍ이 Agent 자신의 코드.
 * 분석이 「none」이라 해도 **파일 경로로 다시 한 번 막는다** — 글로 판단한 것을
 * 믿고 운영에 올리지 않는다.
 */
class AgentFixer
{
    /** 확인 자리 — 사는 집과 따로 받아 둔 저장소 */
    private const 확인자리 = '/home/ubuntu/www/agent-verify';

    /** 한 번에 고칠 수 있는 크기 — 넘으면 사람에게 넘긴다 */
    private const 최대바뀐줄 = 40;

    /**
     * 손대면 안 되는 자리 — **파일 이름에 이 낱말이 있으면 막는다** (2026-10-02 확인).
     *
     * 처음에는 폴더 몇 개만 적어 두었는데, 같은 일을 하는 화면ㆍ컨트롤러ㆍ명령이
     * 그 밖에 있었다 — 위드웍스는 아예 빠져 있었고, 팝빌도 서비스 폴더만 막혀
     * 세금계산서ㆍ현금영수증 화면은 열려 있었다. 폴더로 세면 반드시 빠진다.
     *
     * 그래서 낱말로 막는다. 경로를 소문자로 내려 견주므로 `WithworksLink`ㆍ
     * `app/Http/Controllers/Withworks…`ㆍ`withworks:sync` 가 한꺼번에 걸린다.
     *
     * 이 울타리 때문에 Agent 가 고칠 수 있는 자리는 좁다. 그것이 맞다 — 이 저장소의
     * 큰 자리는 대개 돈이거나 신고이거나 환자 자료다.
     */
    private const 금지 = [
        // 표 구조ㆍ설정ㆍ열쇠
        'database/migrations/', 'config/', '.env',
        // Agent 가 자기 자신을 고치는 일
        'agent',
        // 창고 연계 — 주문이 나가고 취소되는 길
        'withworks',
        // 국세청 신고 — 세금계산서ㆍ현금영수증ㆍ팝빌 전부
        'popbill', 'taxinvoice', 'cashbill',
        // 결제 — 토스ㆍ결제 링크ㆍ정산ㆍ입금ㆍ환불
        'toss', 'payment', 'settlement', 'deposit', 'refund', 'finance',
        // 공단 청구
        'nhis',
        // 환자 개인정보
        'residentno', 'patient', 'consent', 'delegation',
        // 한 번 나가면 거둘 수 없는 것 — 문자ㆍ알림톡ㆍ팩스ㆍ푸시
        'message', 'kakao', 'fax', 'sms', 'fcm', 'notice', 'notif',
        // 주문ㆍ교환반품 — 돈과 창고가 함께 걸린다
        'order', 'return',
    ];

    /** 고칠 수 있는 자리 — 여기 아래만 본다 */
    private const 허용 = ['app/', 'routes/', 'resources/views/'];

    /**
     * 고쳐서 내보낸다.
     *
     * @return array{했나:bool, 글:string, 커밋:?string}
     */
    public function 고친다(array $짐, array $분석, string $열쇠): array
    {
        $자리 = $this->고칠파일($분석, $짐);

        if ($자리 === null) {
            return ['했나' => false, '글' => '고칠 파일을 가리지 못했습니다', '커밋' => null];
        }

        if ($막힘 = $this->막혔나($자리)) {
            return ['했나' => false, '글' => $막힘, '커밋' => null];
        }

        try {
            $this->맞춘다();

            $원본 = $this->읽는다($자리);

            if ($원본 === null) {
                return ['했나' => false, '글' => "확인 자리에 {$자리} 가 없습니다", '커밋' => null];
            }

            $새것 = $this->새파일을받는다($열쇠, $자리, $원본, $짐, $분석);

            if ($새것 === null || trim($새것) === '' || $새것 === $원본) {
                return ['했나' => false, '글' => '고친 내용을 받지 못했습니다', '커밋' => null];
            }

            $this->쓴다($자리, $새것);

            if ($잘못 = $this->문법본다($자리)) {
                $this->되돌린다();

                return ['했나' => false, '글' => '고친 것이 문법에 걸려 되돌렸습니다 — ' . $잘못, '커밋' => null];
            }

            [$바뀐줄, $바뀐파일] = $this->얼마나();

            if ($바뀐파일 !== 1 || $바뀐줄 > self::최대바뀐줄) {
                $this->되돌린다();

                return ['했나' => false,
                        '글' => "고침이 큽니다 (파일 {$바뀐파일}개 · {$바뀐줄}줄) — 사람이 보아야 합니다",
                        '커밋' => null];
            }

            $커밋 = $this->내보낸다($자리, $짐, $분석, $바뀐줄);

            return ['했나' => true,
                    '글' => "고쳐 배포했습니다 — {$자리} ({$바뀐줄}줄) · {$커밋}",
                    '커밋' => $커밋];
        } catch (Throwable $e) {
            try {
                $this->되돌린다();
            } catch (Throwable) {
                // 되돌리다 또 걸리면 그대로 둔다 — 다음 맞춤이 씻는다
            }

            Log::warning('[Agent] 고치다 멈췄습니다', ['error' => $e->getMessage()]);

            return ['했나' => false, '글' => '고치다 멈췄습니다 — ' . $e->getMessage(), '커밋' => null];
        }
    }

    // ── 가리기 ───────────────────────────────────────────

    /** 분석이 짚은 자리에서 파일 경로만 꺼낸다 */
    private function 고칠파일(array $분석, array $짐): ?string
    {
        $글 = (string) ($분석['고칠자리'] ?? '');

        if (preg_match('~((?:app|routes|resources|config|database)/[\w./-]+\.(?:php|blade\.php))~', $글, $m)) {
            return $m[1];
        }

        /* 분석이 자리를 못 적었으면 잘못이 난 파일을 쓴다 — 운영 경로를 떼어 낸다 */
        $자리 = str_replace('\\', '/', (string) ($짐['file'] ?? ''));

        return preg_match('~(?:app|routes|resources|config|database)/[\w./-]+$~', $자리, $m) ? $m[0] : null;
    }

    /** 손대면 안 되는 자리인가 */
    private function 막혔나(string $자리): ?string
    {
        /* 소문자로 내려 견준다 — 파일 이름의 대소문자에 기대지 않는다 */
        $낮춘자리 = mb_strtolower($자리);

        foreach (self::금지 as $막을것) {
            if (str_contains($낮춘자리, mb_strtolower($막을것))) {
                return "손대지 않는 자리입니다 ({$막을것}) — 사람이 보아야 합니다";
            }
        }

        foreach (self::허용 as $열린것) {
            if (str_starts_with($자리, $열린것)) {
                return null;
            }
        }

        return "고칠 수 있는 자리 밖입니다 ({$자리})";
    }

    // ── 확인 자리 다루기 ─────────────────────────────────

    private function 맞춘다(): void
    {
        $this->달린다('git fetch --quiet origin main');
        $this->달린다('git checkout --quiet -B agent-work origin/main');
        $this->달린다('git reset --hard --quiet origin/main');
    }

    private function 되돌린다(): void
    {
        $this->달린다('git checkout -- .');
    }

    private function 읽는다(string $자리): ?string
    {
        $길 = self::확인자리 . '/' . $자리;

        return is_file($길) ? (string) file_get_contents($길) : null;
    }

    private function 쓴다(string $자리, string $글): void
    {
        file_put_contents(self::확인자리 . '/' . $자리, $글);
    }

    private function 문법본다(string $자리): ?string
    {
        if (! str_ends_with($자리, '.php')) {
            return null;
        }

        $php = (string) config('services.agent.php_bin', '/usr/bin/php');
        $답 = Process::path(self::확인자리)->run($php . ' -l ' . escapeshellarg($자리));

        return $답->successful() ? null : trim($답->output() . ' ' . $답->errorOutput());
    }

    /** @return array{0:int, 1:int} 바뀐 줄 수와 파일 수 */
    private function 얼마나(): array
    {
        $답 = $this->달린다('git diff --numstat');
        $줄들 = array_filter(explode("\n", trim($답)));
        $줄 = 0;

        foreach ($줄들 as $한줄) {
            [$더함, $뺌] = array_pad(preg_split('~\s+~', trim($한줄)), 2, '0');
            $줄 += (int) $더함 + (int) $뺌;
        }

        return [$줄, count($줄들)];
    }

    private function 내보낸다(string $자리, array $짐, array $분석, int $바뀐줄): string
    {
        $가지 = 'agent/fix-' . now()->format('ymd-His') . '-' . Str::lower(Str::random(4));

        $글월 = sprintf(
            "%s (Agent 자동 고침)\n\n%s\n\n고친 자리: %s (%d줄)\n원천 잘못: %s %s\n%s",
            Str::limit((string) ($분석['고치는법'] ?? '고침'), 60, ''),
            (string) ($분석['원인'] ?? ''),
            $자리,
            $바뀐줄,
            (string) ($짐['class'] ?? ''),
            (string) ($짐['message'] ?? ''),
            '오류 기록 ' . (string) ($짐['error_log_id'] ?? '-') . ' · 확신 ' . (string) ($분석['확신'] ?? '-')
        );

        $this->달린다('git checkout --quiet -b ' . escapeshellarg($가지));
        $this->달린다('git add ' . escapeshellarg($자리));
        $this->달린다("git -c user.name='CE Admin Agent' -c user.email='agent@ce-admin.co.kr' commit --quiet -F -", $글월);

        $해시 = trim($this->달린다('git rev-parse --short HEAD'));

        /* main 으로 합쳐 밀어 올린다 — 올라가는 순간 Action 이 운영에 배포한다.
           합치기는 fast-forward 만 받는다. 그 사이 사람이 main 을 밀었으면 멈추고
           사람에게 넘긴다 — 남의 일 위에 덮어쓰지 않는다. */
        $this->달린다('git checkout --quiet main 2>/dev/null || git checkout --quiet -B main origin/main');
        $this->달린다('git reset --hard --quiet origin/main');
        $this->달린다('git merge --ff-only --quiet ' . escapeshellarg($가지));
        $this->달린다('git push --quiet origin main');

        return $해시 . ' (' . $가지 . ')';
    }

    /**
     * 올린 것을 되돌린다 (2026-10-02 지시).
     *
     * 사람 확인 없이 올리기로 했으면 **되돌리는 길이 반드시 있어야 한다.** 올린 뒤
     * 같은 잘못이 다시 나거나 새 500 이 늘면 이 자리를 불러 그 커밋만 물린다.
     * 되물린 것도 올라가므로 Action 이 다시 배포한다.
     */
    public function 되물린다(string $해시, string $까닭): array
    {
        try {
            $this->맞춘다();
            $this->달린다('git revert --no-edit --no-commit ' . escapeshellarg($해시));
            $this->달린다(
                "git -c user.name='CE Admin Agent' -c user.email='agent@ce-admin.co.kr' commit --quiet -F -",
                "Agent 고침을 되물린다 ({$해시})\n\n까닭: {$까닭}\n\n올린 뒤 같은 잘못이 다시 났다. 사람이 보아야 한다."
            );
            $this->달린다('git push --quiet origin HEAD:main');

            return ['했나' => true, '글' => "되물렸습니다 ({$해시})"];
        } catch (Throwable $e) {
            return ['했나' => false, '글' => '되물리지 못했습니다 — ' . $e->getMessage()];
        }
    }

    /** 확인 자리에서 명령을 돌린다 */
    private function 달린다(string $명령, ?string $들어갈글 = null): string
    {
        $돌리기 = Process::path(self::확인자리)->timeout(120);

        $답 = $들어갈글 === null
            ? $돌리기->run($명령)
            : $돌리기->input($들어갈글)->run($명령);

        if (! $답->successful()) {
            throw new \RuntimeException(trim($명령 . ' → ' . $답->errorOutput() . $답->output()));
        }

        return $답->output();
    }

    // ── 통째 파일 받기 ───────────────────────────────────

    /**
     * 고친 파일을 **통째로** 받는다.
     *
     * 조각 수정(diff)을 받아 붙이면 줄 번호가 한 줄만 어긋나도 빗나간다. 파일을
     * 그대로 받아 덮어쓰고, 얼마나 바뀌었는지는 git 이 세게 한다.
     */
    private function 새파일을받는다(string $열쇠, string $자리, string $원본, array $짐, array $분석): ?string
    {
        $틀 = <<<'GLU'
        당신은 Laravel 11 로 된 의료기기 주문ㆍ청구 시스템의 코드를 고칩니다.

        받은 파일에서 **그 잘못만** 고쳐, 고친 파일을 통째로 돌려주십시오.

        지킬 것
          · 다른 줄은 한 글자도 건드리지 마십시오. 서식ㆍ주석ㆍ줄 순서를 그대로 두십시오
          · 고치는 범위는 몇 줄이어야 합니다. 구조를 바꾸거나 새 자리를 만들지 마십시오
          · 주석을 더할 때는 왜 그렇게 고쳤는지 한 줄만, 한국어로 적으십시오
          · 결제ㆍ국세청 신고ㆍ주민등록번호ㆍ표 구조에 닿는 고침은 하지 마십시오.
            그런 자리라면 파일을 그대로(한 글자도 바꾸지 않고) 돌려주십시오

        답은 **파일 내용만** 보내십시오. 앞뒤에 설명도, ``` 울타리도 붙이지 마십시오.
        GLU;

        $물음 = "고칠 파일: {$자리}\n\n"
              . "잘못: {$짐['class']} — {$짐['message']}\n"
              . "자리: {$짐['file']}:{$짐['line']}\n\n"
              . "분석\n  원인: " . ($분석['원인'] ?? '') . "\n"
              . '  고치는 법: ' . ($분석['고치는법'] ?? '') . "\n\n"
              . "— 파일 —\n" . $원본;

        $답 = Http::withHeaders([
                'x-api-key'         => $열쇠,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->timeout(300)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => (string) config('services.agent.model', 'claude-opus-5'),
                'max_tokens' => 16000,
                'system'     => $틀,
                'messages'   => [['role' => 'user', 'content' => $물음]],
            ]);

        if ($답->failed()) {
            throw new \RuntimeException('고침을 받지 못했습니다 — ' . $답->status());
        }

        /* 글 덩이만 골라 잇는다 — 첫 덩이는 생각하는 덩이다 */
        $글 = collect($답->json('content') ?? [])
            ->filter(fn ($덩이) => ($덩이['type'] ?? '') === 'text')
            ->pluck('text')
            ->implode('');

        /* 울타리를 붙여 보내는 때가 있다 — 벗긴다 */
        if (preg_match('~^```[a-z]*\n(.*)\n```\s*$~s', trim($글), $m)) {
            $글 = $m[1];
        }

        return $글 === '' ? null : rtrim($글) . "\n";
    }
}
