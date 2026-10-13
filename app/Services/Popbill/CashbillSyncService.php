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
            /* **문서번호가 없는 줄은 여기서 묻지 못한다** (2026-10-13 확인).

               팝빌 상점관리자에서 사람이 직접 발행ㆍ취소한 건은 연동 문서번호가 없다
               (`interOPYN = false`). GetInfo 는 문서번호로만 묻으므로 그 줄에서는 늘
               터져 실패만 쌓였고, 그 줄의 상태는 **영영 갱신되지 않았다** —
               2026-10-06 (E)김지수B 의 취소 문서가 그랬다. 국세청 전송까지 끝났는데
               우리 표에는 전송 칸이 비어 있어 화면이 「전송전」이라 적었다.

               그런 줄은 Search 로 도는 `syncFromPopbill` 이 `item_key` 로 갱신한다. */
            ->whereNotNull('mgt_key')->where('mgt_key', '!=', '')
            /* **끝난 줄은 다시 묻지 않는다** (2026-10-13 확인).

               잣대가 `nts_result` 였는데 **팝빌은 현금영수증에 그 칸을 주지 않는다** —
               조회 응답을 통째로 떠 보니 늘 null 이다. 그래서 137줄이 모두 「아직 안
               끝났다」로 남아, 돌 때마다 팝빌에 137번씩 물었다. 줄이 쌓일수록 그만큼
               늘어난다.

               끝났는지는 국세청 결과 코드가 말한다 — `0000` 이면 정상승인이다. */
            ->where(fn ($q) => $q->whereNull('nts_result_code')
                                 ->orWhere('nts_result_code', '!=', '0000'))
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
        /* **문서번호가 없는 줄도 담는다** (2026-10-06 · (E)김지수B 건).

           팝빌 웹에서 직접 낸 것은 문서번호(mgtKey)가 없고 `itemKey` 만 있다. 여태
           문서번호로만 가려서 그런 줄은 한 건도 담기지 않았고, 매시간 도는 동기화도
           지나갔다 — 담당자가 팝빌에서 직접 취소하면 우리 표에는 「승인」 줄만 남아
           같은 금액이 두 번 발행된 것처럼 보였다.

           문서번호가 없으면 `item_key` 로 가린다. 빈 문자열로 가리면 그런 줄이 모두
           한 줄로 뭉개진다. */
        $문서 = trim((string) ($info->mgtKey ?? ''));
        $짐   = trim((string) ($info->itemKey ?? ''));

        $rec = $문서 !== ''
            ? CashbillRecord::firstOrNew(['corp_num' => $corpNum, 'mgt_key' => $문서])
            : ($짐 !== ''
                ? CashbillRecord::firstOrNew(['corp_num' => $corpNum, 'item_key' => $짐])
                : CashbillRecord::firstOrNew(['corp_num' => $corpNum, 'mgt_key' => '']));

        /* `mgt_key` 는 기본값이 없는 칸이라 비운 채로 담으면 거절당한다.
           문서번호가 없는 줄은 빈 글자로 채워 둔다 — 가리는 열쇠는 위에서 item_key 다. */
        if (($rec->mgt_key ?? null) === null) {
            $rec->mgt_key = '';
        }

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
            // 우리 주문에 잇는다(요청서 6쪽) — 못 이으면 빈칸이다
            'order_id'      => \App\Support\PopbillLink::orderIdFor(
                                   $info->mgtKey ?? null, $info->orderNumber ?? null),
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
            'order_id'        => \App\Support\PopbillLink::orderIdFor(
                                     $full->mgtKey ?? null, $full->orderNumber ?? null),
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
