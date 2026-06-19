<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopbillTaxinvoice extends Model
{
    protected $fillable = [
        'corp_num', 'mgt_key_type', 'mgt_key',
        'item_key', 'state_code', 'state_dt',
        'tax_type', 'purpose_type', 'issue_type', 'write_date', 'issue_dt',
        'invoicer_corp_num', 'invoicer_corp_name', 'invoicer_ceo_name',
        'invoicee_corp_num', 'invoicee_corp_name', 'invoicee_ceo_name',
        'supply_cost_total', 'tax_total', 'total_amount',
        'nts_confirm_num', 'is_final', 'synced_at',
    ];

    protected $casts = [
        'state_code'        => 'integer',
        'supply_cost_total' => 'integer',
        'tax_total'         => 'integer',
        'total_amount'      => 'integer',
        'is_final'          => 'boolean',
        'synced_at'         => 'datetime',
    ];

    /** 최종 상태 코드 (더 이상 sync 불필요) */
    public const FINAL_STATES = [400, 500, 600];

    /** 상태 코드 → 텍스트 */
    public const STATE_LABELS = [
        100 => '임시저장',
        200 => '발행완료',
        220 => '발행완료',
        300 => '국세청대기',
        400 => '국세청완료',
        500 => '취소',
        600 => '국세청취소',
    ];

    public function isFinalState(): bool
    {
        return in_array($this->state_code, self::FINAL_STATES, true);
    }

    /** Popbill TaxinvoiceInfo(Search 결과) 객체 → 배열로 변환 */
    public static function fromPopbillInfo(object $info, string $corpNum, string $mgtKeyType): array
    {
        $mgtKey = match ($mgtKeyType) {
            'BUY'     => $info->invoiceeMgtKey ?? '',
            'TRUSTEE' => $info->trusteeMgtKey  ?? '',
            default   => $info->invoicerMgtKey ?? '',
        };

        $stateCode = (int) ($info->stateCode ?? 0);

        return [
            'corp_num'          => $corpNum,
            'mgt_key_type'      => $mgtKeyType,
            'mgt_key'           => $mgtKey,
            'item_key'          => $info->itemKey       ?? null,
            'state_code'        => $stateCode,
            'state_dt'          => $info->stateDT       ?? null,
            'tax_type'          => $info->taxType        ?? null,
            'purpose_type'      => $info->purposeType   ?? null,
            'issue_type'        => $info->issueType      ?? null,
            'write_date'        => $info->writeDate      ?? null,
            'issue_dt'          => $info->issueDT        ?? null,
            'invoicer_corp_num' => $info->invoicerCorpNum  ?? null,
            'invoicer_corp_name'=> $info->invoicerCorpName ?? null,
            'invoicer_ceo_name' => $info->invoicerCEOName  ?? $info->invoicerCeoName ?? null,
            'invoicee_corp_num' => $info->invoiceeCorpNum  ?? null,
            'invoicee_corp_name'=> $info->invoiceeCorpName ?? null,
            'invoicee_ceo_name' => $info->invoiceeCEOName  ?? $info->invoiceeCeoName ?? null,
            'supply_cost_total' => (int) ($info->supplyCostTotal ?? 0),
            'tax_total'         => (int) ($info->taxTotal        ?? 0),
            'total_amount'      => (int) ($info->totalAmount     ?? 0),
            'nts_confirm_num'   => $info->ntsconfirmNum ?? null,
            'is_final'          => in_array($stateCode, self::FINAL_STATES, true),
            'synced_at'         => now(),
        ];
    }
}
