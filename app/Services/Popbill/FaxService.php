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
        /* 어디로 보내는가 — 세 갈래 (config/popbill.php 의 fax_mode).

           **우리에게만**(redirect)이면 받는 곳을 시험 팩스로 갈아 끼운다. 여태
           이것이 없어, 시험할 때마다 기준정보의 팩스번호를 바꿔 두었다 —
           되돌리기를 잊으면 운영에서 공단으로 팩스가 안 갔다. */
        $mode = config('popbill.fax_mode', 'live');

        if ($mode === 'redirect') {
            $testFax = preg_replace('/\D/', '', (string) config('popbill.test.receiver_fax'));

            if ($testFax === '') {
                throw new \RuntimeException(
                    '테스트 받는 팩스번호가 비어 있습니다 — 설정 › 서비스 연동 설정 › 테스트 설정에서 적어 주십시오.'
                );
            }

            \Illuminate\Support\Facades\Log::info('[Popbill][FAX][우리에게만] 받는 곳을 돌린다', [
                'original' => $receivers,
                'to'       => $testFax,
            ]);

            /* 받는 이름은 그대로 둔다 — 표지에 「누구에게 갈 것이었나」가 남아야
               받아 보고 어느 건인지 안다.

               **줄의 꼴을 지킨다.** 팝빌 SDK 에 넘기는 받는 곳은 rcv·rcvnm 을 가진
               객체(stdClass)다. 배열인 줄로만 알고 아니면 번호 문자열로 갈아 끼웠더니,
               객체가 통째로 문자열이 되어 저쪽이 「전송 요청의 JSON 구성이 유효하지
               않습니다(-16010001)」로 되돌려 보냈다 — 「우리에게만」 팩스는 그래서
               한 번도 나가지 못했다. 온 꼴 그대로 번호만 바꾼다. */
            $receivers = array_map(function ($r) use ($testFax) {
                if (is_array($r))  { $r['rcv'] = $testFax; return $r; }
                if (is_object($r)) { $r->rcv   = $testFax; return $r; }

                return $testFax;
            }, $receivers);
        }

        /* 시늉 — 마지막 한 걸음만 막는다. 여기까지 온 것은 합본이 만들어졌고
           받는 곳도 정해졌다는 뜻이라, 시험에서 볼 것은 이미 다 본 뒤다. */
        if ($mode === 'simulate') {
            $receipt = 'SIMFAX-' . now()->format('YmdHis') . '-' . rand(1000, 9999);
            \Illuminate\Support\Facades\Log::info('[Popbill][FAX][시뮬레이션] 발송', [
                'sender'    => $sender,
                'receivers' => $receivers,
                'files'     => array_map('basename', $filePaths),
                'title'     => $title,
                'receipt'   => $receipt,
            ]);

            return $receipt;
        }

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
