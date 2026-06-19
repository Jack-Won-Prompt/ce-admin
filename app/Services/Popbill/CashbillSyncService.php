<?php

namespace App\Services\Popbill;

use App\Models\CashbillRecord;
use Illuminate\Support\Facades\Log;

class CashbillSyncService
{
    private CashbillService $cashbillSvc;

    public function __construct(CashbillService $cashbillSvc)
    {
        $this->cashbillSvc = $cashbillSvc;
    }

    /**
     * 팝빌 Search API → DB upsert
     *
     * @return array{synced:int, skipped:int, errors:int}
     */
    public function syncFromPopbill(
        string $corpNum,
        string $startDate,
        string $endDate,
        string $dType = 'R',
        int    $perPage = 100
    ): array {
        $synced = 0;
        $errors = 0;
        $page   = 1;

        do {
            try {
                $result = $this->cashbillSvc->search(
                    corpNum:    $corpNum,
                    dType:      $dType,
                    startDate:  $startDate,
                    endDate:    $endDate,
                    stateCode:  [],
                    tradeType:  [],
                    tradeUsage: [],
                    page:       $page,
                    perPage:    $perPage,
                    order:      'D',
                );
            } catch (\Throwable $e) {
                Log::error('[Cashbill Sync] Search 실패', ['error' => $e->getMessage()]);
                break;
            }

            $list  = $result->list ?? [];
            $total = (int) ($result->total ?? 0);

            foreach ($list as $info) {
                try {
                    $this->upsertFromInfo($corpNum, $info);
                    $synced++;
                } catch (\Throwable $e) {
                    Log::warning('[Cashbill Sync] upsert 실패', [
                        'mgt_key' => $info->mgtKey ?? null,
                        'error'   => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            $page++;
        } while (count($list) === $perPage && ($page - 1) * $perPage < $total);

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * DB에 저장된 비최종 상태 레코드의 상태를 팝빌 GetInfo로 갱신
     *
     * @return array{updated:int, errors:int}
     */
    public function refreshPendingStatus(string $corpNum, int $limit = 200): array
    {
        $records = CashbillRecord::where('corp_num', $corpNum)
            ->where(fn($q) => $q->where('nts_result', '!=', 2)->orWhereNull('nts_result'))
            ->where('state_code', '<', 400)
            ->orderBy('trade_dt', 'desc')
            ->limit($limit)
            ->get();

        $updated = 0;
        $errors  = 0;

        foreach ($records as $rec) {
            try {
                $info = $this->cashbillSvc->getInfo($corpNum, $rec->mgt_key);
                $this->applyInfoToRecord($rec, $info);
                $rec->synced_at = now();
                $rec->save();
                $updated++;
            } catch (\Throwable $e) {
                Log::warning('[Cashbill Sync] 상태 갱신 실패', [
                    'mgt_key' => $rec->mgt_key,
                    'error'   => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * 특정 mgtKey 하나를 팝빌에서 재조회해 DB 갱신 후 반환
     */
    public function refreshOne(string $corpNum, string $mgtKey): CashbillRecord
    {
        $rec  = CashbillRecord::firstOrNew(['corp_num' => $corpNum, 'mgt_key' => $mgtKey]);
        $full = $this->cashbillSvc->getFullInfo($corpNum, $mgtKey);
        $this->applyFullToRecord($rec, $corpNum, $mgtKey, $full);
        $rec->synced_at = now();
        $rec->save();
        return $rec;
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function upsertFromInfo(string $corpNum, object $info): void
    {
        $rec = CashbillRecord::firstOrNew([
            'corp_num' => $corpNum,
            'mgt_key'  => $info->mgtKey ?? '',
        ]);

        $rec->fill([
            'item_key'      => $info->itemKey      ?? null,
            'trade_type'    => $info->tradeType    ?? null,
            'trade_usage'   => $info->tradeUsage   ?? null,
            'taxation_type' => $info->taxationType ?? null,
            'total_amount'  => (int) ($info->totalAmount ?? 0),
            'supply_cost'   => (int) ($info->supplyCost  ?? 0),
            'tax'           => (int) ($info->tax          ?? 0),
            'service_fee'   => (int) ($info->serviceFee  ?? 0),
            'issue_dt'      => $info->issueDT      ?? null,
            'trade_dt'      => $info->tradeDT      ?? null,
            'trade_date'    => $info->tradeDate    ?? null,
            'reg_dt'        => $info->regDT        ?? null,
            'state_code'    => (int) ($info->stateCode   ?? 0),
            'state_dt'      => $info->stateDT      ?? null,
            'state_memo'    => $info->stateMemo    ?? null,
            'identity_num'  => $info->identityNum  ?? null,
            'customer_name' => $info->customerName ?? null,
            'item_name'     => $info->itemName     ?? null,
            'order_number'  => $info->orderNumber  ?? null,
            'email'         => $info->email        ?? null,
            'hp'            => $info->hp           ?? null,
            'confirm_num'   => $info->confirmNum   ?? null,
            'org_confirm_num'  => $info->orgConfirmNum  ?? null,
            'org_trade_date'   => $info->orgTradeDate   ?? null,
            'nts_result'       => isset($info->ntsresult) ? (int) $info->ntsresult : null,
            'nts_result_dt'    => $info->ntsresultDT      ?? null,
            'nts_result_code'  => $info->ntsresultCode    ?? null,
            'nts_result_message' => $info->ntsresultMessage ?? null,
            'nts_send_dt'      => $info->ntssendDT        ?? null,
        ]);

        $rec->synced_at = now();
        $rec->save();
    }

    private function applyInfoToRecord(CashbillRecord $rec, object $info): void
    {
        $rec->state_code       = (int) ($info->stateCode      ?? $rec->state_code);
        $rec->state_dt         = $info->stateDT        ?? $rec->state_dt;
        $rec->confirm_num      = $info->confirmNum     ?? $rec->confirm_num;
        $rec->nts_result       = isset($info->ntsresult) ? (int) $info->ntsresult : $rec->nts_result;
        $rec->nts_result_dt    = $info->ntsresultDT    ?? $rec->nts_result_dt;
        $rec->nts_result_code  = $info->ntsresultCode  ?? $rec->nts_result_code;
        $rec->nts_result_message = $info->ntsresultMessage ?? $rec->nts_result_message;
        $rec->nts_send_dt      = $info->ntssendDT      ?? $rec->nts_send_dt;
        $rec->issue_dt         = $info->issueDT        ?? $rec->issue_dt;
        $rec->trade_dt         = $info->tradeDT        ?? $rec->trade_dt;
    }

    private function applyFullToRecord(CashbillRecord $rec, string $corpNum, string $mgtKey, object $full): void
    {
        $rec->corp_num = $corpNum;
        $rec->mgt_key  = $mgtKey;
        $rec->fill(array_filter([
            'item_key'        => $full->itemKey       ?? null,
            'trade_type'      => $full->tradeType     ?? null,
            'trade_usage'     => $full->tradeUsage    ?? null,
            'taxation_type'   => $full->taxationType  ?? null,
            'total_amount'    => isset($full->totalAmount) ? (int) $full->totalAmount : null,
            'supply_cost'     => isset($full->supplyCost)  ? (int) $full->supplyCost  : null,
            'tax'             => isset($full->tax)         ? (int) $full->tax          : null,
            'service_fee'     => isset($full->serviceFee)  ? (int) $full->serviceFee  : null,
            'issue_dt'        => $full->issueDT       ?? null,
            'trade_dt'        => $full->tradeDT       ?? null,
            'trade_date'      => $full->tradeDate     ?? null,
            'reg_dt'          => $full->regDT         ?? null,
            'state_code'      => isset($full->stateCode)   ? (int) $full->stateCode   : null,
            'state_dt'        => $full->stateDT       ?? null,
            'identity_num'    => $full->identityNum   ?? null,
            'customer_name'   => $full->customerName  ?? null,
            'item_name'       => $full->itemName      ?? null,
            'order_number'    => $full->orderNumber   ?? null,
            'email'           => $full->email         ?? null,
            'hp'              => $full->hp            ?? null,
            'confirm_num'     => $full->confirmNum    ?? null,
            'org_confirm_num' => $full->orgConfirmNum ?? null,
            'org_trade_date'  => $full->orgTradeDate  ?? null,
            'nts_result'      => isset($full->ntsresult)   ? (int) $full->ntsresult   : null,
            'nts_result_dt'   => $full->ntsresultDT   ?? null,
            'nts_result_code' => $full->ntsresultCode ?? null,
            'nts_result_message' => $full->ntsresultMessage ?? null,
            'nts_send_dt'     => $full->ntssendDT     ?? null,
            'franchise_corp_num'  => $full->franchiseCorpNum  ?? null,
            'franchise_corp_name' => $full->franchiseCorpName ?? null,
            'franchise_ceo_name'  => $full->franchiseCEOName  ?? null,
            'franchise_addr'      => $full->franchiseAddr     ?? null,
            'franchise_tel'       => $full->franchiseTEL      ?? null,
        ], fn($v) => $v !== null));
    }
}
