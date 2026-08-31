<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\PopbillEasyFinBank;
use Linkhub\Popbill\PopbillException;

/**
 * 팝빌 계좌조회 — 통장에 무엇이 들어왔는지 (요청서 5쪽, 2026-08-31).
 *
 * 지금까지 입금은 토스가 알려 주는 가상계좌뿐이었다. 무통장으로 곧장 보낸 돈, 공단과
 * 지자체가 보내는 환급금은 담당자가 인터넷뱅킹을 열어 눈으로 맞춰야 했다.
 *
 * 팝빌은 두 걸음으로 준다 — 「모아 달라」 하고(RequestJob) 다 모이면 읽는다(Search).
 * 한 번에 돌려주지 않는 까닭은 은행에서 긁어 오는 데 시간이 걸리기 때문이다. 그래서
 * 작업번호를 받아 두고 상태를 물어본다(GetJobState).
 *
 * 다른 팝빌 서비스와 같은 계정·같은 인증을 쓴다. 계좌는 팝빌 사이트에서 미리 등록하고
 * 정액제를 신청해 두어야 한다 — 그것이 안 돼 있으면 RequestJob 이 오류로 돌아온다.
 */
class EasyFinBankService extends PopbillBaseService
{
    /** 수집이 끝난 상태 — 이때만 읽을 수 있다 */
    public const JOB_DONE = 3;

    private PopbillEasyFinBank $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillEasyFinBank($this->linkId, $this->secretKey);
        $svc->IsTest($this->isTest);
        $svc->IPRestrictOnOff($this->ipRestrictOnOff);
        $svc->UseStaticIP($this->useStaticIp);
        $svc->UseLocalTimeYN($this->useLocalTimeYn);

        return $svc;
    }

    /** 팝빌에 등록해 둔 계좌 목록 — 무엇을 조회할 수 있는지 여기서 본다 */
    public function listAccounts(string $corpNum): array
    {
        try {
            return $this->api->ListBankAccount($corpNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 「이 기간을 모아 달라」 — 작업번호를 돌려준다.
     *
     * 날짜는 yyyyMMdd 여덟 자리다.
     */
    public function requestJob(string $corpNum, string $bankCode, string $accountNumber,
                               string $from, string $to): string
    {
        try {
            return $this->api->RequestJob($corpNum, $bankCode, $accountNumber, $from, $to);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /** 다 모였는가 — jobState 가 3 이면 읽을 수 있다 */
    public function jobState(string $corpNum, string $jobId): object
    {
        try {
            return $this->api->GetJobState($corpNum, $jobId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 모아 둔 거래내역을 읽는다.
     *
     * @param list<string> $tradeType I 입금 · O 출금. 비우면 둘 다.
     */
    public function search(string $corpNum, string $jobId, array $tradeType = [],
                           ?string $keyword = null, int $page = 1, int $perPage = 500): object
    {
        try {
            return $this->api->Search($corpNum, $jobId, $tradeType, $keyword, $page, $perPage, 'D');
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /** 아직 살아 있는 수집 작업들 — 같은 기간을 두 번 걸지 않으려고 본다 */
    public function activeJobs(string $corpNum): array
    {
        try {
            return $this->api->ListActiveJob($corpNum);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /** 팝빌 계좌조회 화면을 그대로 여는 주소 — 계좌 등록·정액제 신청을 여기서 한다 */
    public function manageUrl(string $corpNum, ?string $userId = null): string
    {
        try {
            return $this->api->GetBankAccountMgtURL($corpNum, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }
}
