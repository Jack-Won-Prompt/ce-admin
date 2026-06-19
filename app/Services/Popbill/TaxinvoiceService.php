<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\PopbillException;
use Linkhub\Popbill\PopbillTaxinvoice;
use Linkhub\Popbill\Taxinvoice;
use Linkhub\Popbill\TaxinvoiceDetail;

class TaxinvoiceService extends PopbillBaseService
{
    private PopbillTaxinvoice $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->newService();
    }

    protected function newService(): object
    {
        $svc = new PopbillTaxinvoice($this->linkId, $this->secretKey);
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
     * 팝빌 세금계산서 연결 URL
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
    public function registIssue(string $corpNum, Taxinvoice $invoice, ?string $userId = null): object
    {
        try {
            return $this->api->RegistIssue($corpNum, $invoice, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 임시저장
     */
    public function register(string $corpNum, Taxinvoice $invoice, ?string $userId = null): object
    {
        try {
            return $this->api->Register($corpNum, $invoice, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 발행
     */
    public function issue(string $corpNum, string $mgtKeyType, string $mgtKey, ?string $memo = null, ?string $userId = null): object
    {
        try {
            return $this->api->Issue($corpNum, $mgtKeyType, $mgtKey, $memo, null, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 삭제
     */
    public function delete(string $corpNum, string $mgtKeyType, string $mgtKey, ?string $userId = null): object
    {
        try {
            return $this->api->Delete($corpNum, $mgtKeyType, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 발행 취소
     */
    public function cancelIssue(string $corpNum, string $mgtKeyType, string $mgtKey, ?string $memo = null, ?string $userId = null): object
    {
        try {
            return $this->api->CancelIssue($corpNum, $mgtKeyType, $mgtKey, $memo, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 상태확인
     */
    public function getInfo(string $corpNum, string $mgtKeyType, string $mgtKey): object
    {
        try {
            return $this->api->GetInfo($corpNum, $mgtKeyType, $mgtKey);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 목록조회
     */
    public function search(
        string $corpNum,
        string $mgtKeyType,
        string $startDate,
        string $endDate,
        array  $stateCode = [],
        array  $typeCode = [],
        array  $taxTypeCode = [],
        int    $page = 1,
        int    $perPage = 20,
        string $order = 'D',
        string $dType = 'W',
        ?string $userId = null
    ): object {
        try {
            return $this->api->Search(
                $corpNum, $mgtKeyType, $dType, $startDate, $endDate,
                $stateCode, $typeCode, $taxTypeCode,
                null, $page, $perPage, $order,
                null, null, null, null, null, $userId
            );
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 목록조회 + 전체 항목 반환 (DB 동기화용, 페이징 없이 최대 수집)
     */
    public function searchAll(
        string $corpNum,
        string $mgtKeyType,
        string $startDate,
        string $endDate,
        string $dType = 'W',
    ): array {
        $all     = [];
        $page    = 1;
        $perPage = 100;

        try {
            do {
                $result = $this->api->Search(
                    $corpNum, $mgtKeyType, $dType, $startDate, $endDate,
                    [], [], [],
                    null, $page, $perPage, 'D',
                    null, null, null, null, null, null
                );
                $list = $result->list ?? [];
                $all  = array_merge($all, is_array($list) ? $list : (array) $list);
                $page++;
            } while (count($list) === $perPage);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }

        return $all;
    }

    /**
     * 세금계산서 팝업 URL
     */
    public function getPopupUrl(string $corpNum, string $mgtKeyType, string $mgtKey, ?string $userId = null): string
    {
        try {
            return $this->api->GetPopUpURL($corpNum, $mgtKeyType, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 인쇄 URL
     */
    public function getPrintUrl(string $corpNum, string $mgtKeyType, string $mgtKey, ?string $userId = null): string
    {
        try {
            return $this->api->GetPrintURL($corpNum, $mgtKeyType, $mgtKey, $userId);
        } catch (PopbillException $e) {
            $this->handleException($e);
        }
    }

    /**
     * 새 Taxinvoice 객체 생성 헬퍼
     */
    public function newInvoice(): Taxinvoice
    {
        return new Taxinvoice();
    }

    /**
     * 새 TaxinvoiceDetail 객체 생성 헬퍼
     */
    public function newDetail(): TaxinvoiceDetail
    {
        return new TaxinvoiceDetail();
    }
}
