<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\PopbillException;
use Linkhub\Popbill\PopbillMessaging;

class MessageService extends PopbillBaseService
{
    private PopbillMessaging $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillMessaging($this->linkId, $this->secretKey);
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
     * 단문(SMS) 전송
     *
     * @param  array  $messages  [['rcv'=>'수신번호','rcvnm'=>'수신자명','msg'=>'내용'], ...]
     */
    public function sendSms(
        string $corpNum,
        string $sender,
        string $content,
        array  $messages,
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendSMS($corpNum, $sender, $content, $messages, $reserveDt, false, $userId, null, $requestNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 장문(LMS) 전송
     *
     * @param  array  $messages  [['rcv'=>'수신번호','rcvnm'=>'수신자명','msg'=>'내용'], ...]
     */
    public function sendLms(
        string $corpNum,
        string $sender,
        string $subject,
        string $content,
        array  $messages,
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendLMS($corpNum, $sender, $subject, $content, $messages, $reserveDt, false, $userId, null, $requestNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 자동(SMS/LMS) 전송 — 내용 길이에 따라 자동 선택
     *
     * @param  array  $messages
     */
    public function sendXms(
        string $corpNum,
        string $sender,
        string $subject,
        string $content,
        array  $messages,
        ?string $reserveDt = null,
        ?string $userId = null,
        ?string $requestNum = null
    ): string {
        try {
            return $this->api->SendXMS($corpNum, $sender, $subject, $content, $messages, $reserveDt, false, $userId, null, $requestNum);
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
        string $messageType,
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
                $corpNum, $messageType, $startDate, $endDate,
                $state, null, null, $page, $perPage, $order, $userId
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
     * 기본 설정값으로 단문/장문 자동 발송 (컨트롤러 편의용)
     * 로컬 환경(APP_ENV=local)에서는 실제 API를 호출하지 않고 시뮬레이션.
     */
    public function send(string $to, string $content, ?string $receiverName = null): string
    {
        $toNum = preg_replace('/\D/', '', $to);

        if (config('popbill.sms_simulate', app()->isLocal())) {
            $receipt = 'SIM-' . now()->format('YmdHis') . '-' . rand(1000, 9999);
            \Illuminate\Support\Facades\Log::info('[Popbill][SMS][시뮬레이션] 발송', [
                'to'      => $toNum,
                'name'    => $receiverName,
                'content' => $content,
                'receipt' => $receipt,
            ]);
            return $receipt;
        }

        $sender = $this->resolveRegisteredSenderNum();

        \Illuminate\Support\Facades\Log::info('[Popbill][SMS] 발송 요청', [
            'to'      => $toNum,
            'name'    => $receiverName,
            'sender'  => $sender,
            'content' => $content,
        ]);

        $receipt = $this->sendXms(
            corpNum:    $this->corpNum,
            sender:     $sender,
            subject:    '',
            content:    $content,
            messages:   [['rcv' => $toNum, 'rcvnm' => $receiverName ?? '', 'msg' => $content]],
            userId:     $this->userId,
        );

        \Illuminate\Support\Facades\Log::info('[Popbill][SMS] 발송 완료', [
            'to'      => $toNum,
            'receipt' => $receipt,
        ]);

        return $receipt;
    }

    /**
     * 팝빌에 등록·활성화된 발신번호를 반환.
     * 설정값이 이미 등록되어 있으면 그대로, 아니면 목록 첫 번째 활성 번호를 사용.
     * 목록을 가져오지 못하면 설정값을 그대로 반환(원래 에러가 API에서 발생하도록 위임).
     */
    private function resolveRegisteredSenderNum(): string
    {
        try {
            $list = $this->api->GetSenderNumberList($this->corpNum);

            \Illuminate\Support\Facades\Log::info('[Popbill][SMS] 발신번호 목록', [
                'count' => is_array($list) ? count($list) : 'non-array',
                'items' => is_array($list) ? array_map(fn($i) => ['number' => $i->number ?? null, 'state' => $i->state ?? null], $list) : $list,
            ]);

            if (empty($list)) {
                \Illuminate\Support\Facades\Log::warning('[Popbill][SMS] 등록된 발신번호 없음 — 팝빌 콘솔에서 발신번호를 등록하세요.');
                return $this->senderNum;
            }

            $configured = preg_replace('/\D/', '', $this->senderNum);

            foreach ($list as $item) {
                $num = preg_replace('/\D/', '', $item->number ?? '');
                if ($num === $configured && ($item->state ?? -1) === 1) {
                    return $item->number;
                }
            }

            foreach ($list as $item) {
                if (($item->state ?? -1) === 1) {
                    \Illuminate\Support\Facades\Log::warning('[Popbill][SMS] 설정 발신번호 미등록 → 대체', [
                        'configured' => $this->senderNum,
                        'used'       => $item->number,
                    ]);
                    return $item->number;
                }
            }

            \Illuminate\Support\Facades\Log::warning('[Popbill][SMS] 승인된 발신번호 없음', [
                'items' => array_map(fn($i) => ['number' => $i->number ?? null, 'state' => $i->state ?? null], $list),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Popbill][SMS] 발신번호 목록 조회 실패', ['error' => $e->getMessage()]);
        }

        return $this->senderNum;
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
}
