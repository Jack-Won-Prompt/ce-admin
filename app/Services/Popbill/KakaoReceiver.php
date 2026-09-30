<?php

namespace App\Services\Popbill;

/**
 * 알림톡ㆍ친구톡 한 사람분 전송정보 (2026-09-30).
 *
 * ## 왜 우리가 만드나
 *
 * 팝빌 문서는 이 자리를 `Linkhub\Popbill\KakaoReceiver` 로 적는데, **설치된 SDK
 * 1.65.0 에는 그 클래스가 없다**(운영ㆍ로컬 모두 `class_exists` 가 거짓이다).
 * 그래서 알림톡을 보내려 하면 「Class not found」로 걸려 한 통도 나가지 못했다.
 *
 * SDK 의 `SendATS` 는 이 객체들을 그대로 `json_encode` 해 `msgs` 로 싣는다. 그러니
 * 규격대로 된 이름만 가지면 우리 것이어도 똑같이 선다.
 *
 * ## 비어 있는 칸은 싣지 않는다
 *
 * 그냥 객체로 두면 `altsjt: null` 같은 칸까지 실려 나간다. 팝빌이 그것을 어떻게
 * 읽을지 근거가 없으므로, 채운 것만 싣는다.
 */
class KakaoReceiver implements \JsonSerializable
{
    /** 수신번호 */
    public string $rcv = '';

    /** 수신자명 */
    public string $rcvnm = '';

    /** 알림톡 내용 — 승인된 템플릿의 변수를 모두 치환한 글 */
    public string $msg = '';

    /** 대체문자 제목 */
    public string $altsjt = '';

    /** 대체문자 내용 */
    public string $altmsg = '';

    /** 버튼 목록 — 비워 두면 템플릿에 등록된 버튼이 그대로 나간다 */
    public array $btns = [];

    public function jsonSerialize(): array
    {
        $담을것 = [];

        foreach (['rcv', 'rcvnm', 'msg', 'altsjt', 'altmsg'] as $칸) {
            if (trim($this->{$칸}) !== '') {
                $담을것[$칸] = $this->{$칸};
            }
        }

        if ($this->btns !== []) {
            $담을것['btns'] = $this->btns;
        }

        return $담을것;
    }
}
