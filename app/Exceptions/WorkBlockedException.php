<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 사람이 화면에서 막힌 자리 (2026-10-06 지시).
 *
 * 던지지 않는다 — 막은 쪽이 `ErrorRecorder::담기()` 에 손수 건네서 오류 이력 표에
 * 한 줄 남기려고 두는 갈래다.
 *
 * 입력값이 어긋나 되돌려 보내는 것은 본래 잘못이 아니라 일상이라, 오류 이력은
 * `ValidationException` 을 통째로 거른다(ErrorRecorder::넘길것). 맞는 잣대다 —
 * 그러나 그 때문에 **담당자가 두 번 눌러 두 번 막히고 일을 포기한 일**이 아무 데도
 * 남지 않았다(2026-10-06 09:31 · 등록신청서 신청인란). 화면에 뜬 것은 영어 검사
 * 문구 한 줄이었고, 응답 본문은 어디에도 저장되지 않아 되살릴 수 없었다.
 *
 * 그래서 거르기는 그대로 두고, **사람이 실제로 막힌 자리만** 스스로 적는다.
 *
 * 422 로 둔다 — 표에 500 으로 적히면 서버가 터진 것으로 읽혀, 정작 봐야 할 서버
 * 잘못과 섞인다. 4xx 는 기본으로 담지 않으므로 `config/errors.php` 의 `keep_status`
 * 에 422 를 넣어 두었다.
 */
class WorkBlockedException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}
