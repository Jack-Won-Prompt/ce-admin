<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\KakaoReceiver;
use Linkhub\Popbill\PopbillException;
use Linkhub\Popbill\PopbillKakao;

class KakaoService extends PopbillBaseService
{
    private PopbillKakao $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillKakao($this->linkId, $this->secretKey);
        $svc->IsTest($this->isTest);
        $svc->IPRestrictOnOff($this->ipRestrictOnOff);
        $svc->UseStaticIP($this->useStaticIp);
        $svc->UseLocalTimeYN($this->useLocalTimeYn);
        return $svc;
    }

    /**
     * 잔여포인트 조회
     */
    public function getBalance(string $corpNum): float
    {
        try {
            return $this->api->GetBalance($corpNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 알림톡 템플릿 목록 확인
     */
    public function listTemplates(string $corpNum): array
    {
        try {
            return $this->api->ListATSTemplate($corpNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 카카오톡 채널 목록 확인
     */
    public function listPlusFriends(string $corpNum): array
    {
        try {
            return $this->api->ListPlusFriendID($corpNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 알림톡 전송
     *
     * @param  array<KakaoReceiver>  $messages
     */
    public function sendAts(
        string $corpNum,
        string $templateCode,
        string $sender,
        string $content,
        array  $messages,
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendATS(
                $corpNum, $templateCode, $sender, $content,
                null, null, $messages, $reserveDt, $userId, $requestNum
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 친구톡(텍스트) 전송
     *
     * @param  array<KakaoReceiver>  $messages
     */
    public function sendFts(
        string $corpNum,
        string $plusFriendId,
        string $sender,
        string $content,
        array  $messages,
        array  $btns = [],
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendFTS(
                $corpNum, $plusFriendId, $sender, $content,
                null, null, false, $messages, $btns,
                $reserveDt, $userId, $requestNum
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 전송내역 확인 (접수번호)
     */
    public function getMessages(string $corpNum, string $receiptNum, ?string $userId = null): object
    {
        try {
            return $this->api->GetMessages($corpNum, $receiptNum, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 전송내역 목록 조회
     */
    public function search(
        string $corpNum,
        string $startDate,
        string $endDate,
        array  $state,
        array  $item = [],
        int    $page = 1,
        int    $perPage = 20,
        string $order = 'D',
        ?string $userId = null
    ): object {
        try {
            return $this->api->Search(
                $corpNum, $startDate, $endDate,
                $state, $item, null, false,
                $page, $perPage, $order, $userId
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 예약전송 취소
     */
    public function cancelReserve(string $corpNum, string $receiptNum, ?string $userId = null): object
    {
        try {
            return $this->api->CancelReserve($corpNum, $receiptNum, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 전송내역 팝업 URL
     */
    public function getSentListUrl(string $corpNum, ?string $userId = null): string
    {
        try {
            return $this->api->GetSentListURL($corpNum, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 알림톡 템플릿관리 팝업 URL
     */
    public function getTemplateUrl(string $corpNum, ?string $userId = null): string
    {
        try {
            return $this->api->GetATSTemplateMgtURL($corpNum, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 새 KakaoReceiver 객체 생성 헬퍼
     */
    public function newReceiver(): KakaoReceiver
    {
        return new KakaoReceiver();
    }
}
