<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\Cashbill;
use Linkhub\Popbill\PopbillCashbill;
use Linkhub\Popbill\PopbillException;

class CashbillService extends PopbillBaseService
{
    private PopbillCashbill $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillCashbill($this->linkId, $this->secretKey);
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
     * 팝빌 현금영수증 연결 URL
     */
    public function getUrl(string $corpNum, string $userId, string $togo): string
    {
        try {
            return $this->api->GetURL($corpNum, $userId, $togo);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 즉시발행
     */
    public function registIssue(string $corpNum, Cashbill $cashbill, ?string $userId = null): object
    {
        try {
            return $this->api->RegistIssue($corpNum, $cashbill, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 임시저장
     */
    public function register(string $corpNum, Cashbill $cashbill, ?string $userId = null): object
    {
        try {
            return $this->api->Register($corpNum, $cashbill, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 발행
     */
    public function issue(string $corpNum, string $mgtKey, ?string $userId = null): object
    {
        try {
            return $this->api->Issue($corpNum, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 삭제
     */
    public function delete(string $corpNum, string $mgtKey, ?string $userId = null): object
    {
        try {
            return $this->api->Delete($corpNum, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 취소현금영수증 즉시발행
     *
     * @param string $orgMgtKey    원본 국세청승인번호(confirmNum)
     * @param string $orgTradeDate 원본 거래일자 (Ymd)
     */
    public function revokeRegistIssue(
        string $corpNum,
        string $mgtKey,
        string $orgMgtKey,
        string $orgTradeDate = '',
        ?string $userId = null
    ): object {
        try {
            return $this->api->RevokeRegistIssue($corpNum, $mgtKey, $orgMgtKey, $orgTradeDate, false, null, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 상태확인
     */
    public function getInfo(string $corpNum, string $mgtKey): object
    {
        try {
            return $this->api->GetInfo($corpNum, $mgtKey);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 상세정보 — GetInfo(상태/승인번호) + GetDetailInfo(가맹점·품목 원본) 병합
     */
    public function getFullInfo(string $corpNum, string $mgtKey): object
    {
        $merged = new \stdClass();

        try {
            $info = $this->api->GetInfo($corpNum, $mgtKey);
            foreach ((array) $info as $k => $v) {
                $merged->$k = $v;
            }
        } catch (PopbillException $e) {
            $this->handleException($e);
        }

        try {
            $detail = $this->api->GetDetailInfo($corpNum, $mgtKey);
            foreach ((array) $detail as $k => $v) {
                if (!isset($merged->$k) || $merged->$k === null || $merged->$k === '') {
                    $merged->$k = $v;
                }
            }
            // 가맹점 정보는 Detail 우선
            foreach (['franchiseCorpNum','franchiseCorpName','franchiseCEOName','franchiseAddr','franchiseTEL'] as $fk) {
                if (isset($detail->$fk)) {
                    $merged->$fk = $detail->$fk;
                }
            }
        } catch (\Throwable) {
            // 상세 조회 실패 시 기본 정보만 반환
        }

        return $merged;
    }

    /**
     * 목록조회
     *
     * @param string $dType 일자유형: 'R'(등록일) | 'T'(거래일)
     */
    public function search(
        string $corpNum,
        string $dType = 'R',
        string $startDate = '',
        string $endDate = '',
        array  $stateCode = [],
        array  $tradeType = [],
        array  $tradeUsage = [],
        int    $page = 1,
        int    $perPage = 20,
        string $order = 'D'
    ): object {
        try {
            return $this->api->Search(
                $corpNum, $dType, $startDate, $endDate,
                $stateCode, $tradeType, $tradeUsage,
                [], $page, $perPage, $order
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 팝업 URL
     */
    public function getPopupUrl(string $corpNum, string $mgtKey, ?string $userId = null): string
    {
        try {
            return $this->api->GetPopUpURL($corpNum, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 인쇄 URL
     */
    public function getPrintUrl(string $corpNum, string $mgtKey, ?string $userId = null): string
    {
        try {
            return $this->api->GetPrintURL($corpNum, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 새 Cashbill 객체 생성 헬퍼
     */
    public function newCashbill(): Cashbill
    {
        return new Cashbill();
    }
}
