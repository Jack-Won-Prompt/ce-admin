<?php
// app/Services/TossPayments/TossApiException.php

namespace App\Services\TossPayments;

/**
 * 토스가 돌려준 오류.
 *
 * **오류 코드를 따로 담는다** (2026-10-16). 부르는 쪽이 코드로 갈라야 할 때가 있는데,
 * 여태 메시지 글을 뜯어 보는 수밖에 없었다 — 저쪽이 문구를 바꾸면 그대로 깨진다.
 */
class TossApiException extends \RuntimeException
{
    public function __construct(string $message = '', private string $토스코드 = '', int $http = 0)
    {
        parent::__construct($message, $http);
    }

    /** 토스가 준 오류 코드 — 없으면 빈 글자 */
    public function 토스코드(): string
    {
        return $this->토스코드;
    }
}
