<?php

namespace App\Support;

/**
 * Agent 되돌이를 끝까지 보기 위한 자리 (2026-10-07 시험).
 *
 * 운영에서 `/dev/agent-error-test` 를 열면 이 셈이 돌다 멈춘다. 그 잘못이 오류
 * 기록에 담기고, 웹훅으로 Agent 에게 가고, Agent 가 고쳐 올리는지를 본다.
 *
 * 돈ㆍ국세청 신고ㆍ환자 개인정보에 닿지 않는다 — 쪽수만 센다. **확인이 끝나면
 * 이 파일과 라우트를 걷는다.** 운영에 오래 둘 자리가 아니다.
 */
class AgentSelfTest
{
    /** 한 쪽에 몇 건씩 보일까 */
    private const 한쪽에 = 0;

    /** 몇 쪽이 되는가 */
    public static function 쪽수(int $모두): int
    {
        return intdiv($모두, self::한쪽에);
    }
}
