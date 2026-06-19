<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\PopbillException;
use Linkhub\Popbill\PopbillFax;

class FaxService extends PopbillBaseService
{
    private PopbillFax $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillFax($this->linkId, $this->secretKey);
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
     * 팩스 전송
     *
     * @param  array<\stdClass>  $receivers  [['rcv'=>'수신번호','rcvnm'=>'수신자명'], ...]
     * @param  array<string>       $filePaths  전송할 파일 경로 배열
     */
    public function sendFax(
        string $corpNum,
        string $sender,
        array  $receivers,
        array  $filePaths,
        ?string $reserveDt = null,
        ?string $senderName = null,
        ?string $title = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendFAX(
                $corpNum, $sender, $receivers, $filePaths,
                $reserveDt, $userId, $senderName, false, $title, $requestNum
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 팩스 재전송
     */
    public function reSendFax(
        string $corpNum,
        string $receiptNum,
        ?string $sender = null,
        array  $receivers = [],
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->ResendFAX(
                $corpNum, $receiptNum, $sender, null,
                $receivers, $reserveDt, $userId, $requestNum
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 전송내역 확인 (접수번호)
     */
    public function getMessages(string $corpNum, string $receiptNum, ?string $userId = null): array
    {
        try {
            return $this->api->GetFaxDetail($corpNum, $receiptNum, $userId);
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
        array  $state = [],
        int    $page = 1,
        int    $perPage = 20,
        string $order = 'D',
        ?string $userId = null
    ): object {
        try {
            return $this->api->Search(
                $corpNum, $startDate, $endDate,
                $state, null, false, $page, $perPage, $order, $userId
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
     * 발신번호 목록 확인
     */
    public function getSenderNumberList(string $corpNum): array
    {
        try {
            return $this->api->GetSenderNumberList($corpNum);
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
     * 새 \stdClass 객체 생성 헬퍼
     */
    public function newReceiver(): \stdClass
    {
        return new \stdClass();
    }
}
