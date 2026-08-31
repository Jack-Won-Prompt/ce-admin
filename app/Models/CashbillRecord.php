<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashbillRecord extends Model
{
    protected $fillable = [
        // 우리 주문에 잇는 열쇠 — 팝빌이 돌려준 주문번호나 관리번호에서 읽는다
        'order_id',
        'corp_num', 'mgt_key', 'item_key',
        'trade_type', 'trade_usage', 'taxation_type',
        'total_amount', 'supply_cost', 'tax', 'service_fee',
        'issue_dt', 'trade_dt', 'trade_date', 'reg_dt',
        'state_code', 'state_dt', 'state_memo',
        'identity_num', 'customer_name', 'item_name', 'order_number', 'email', 'hp',
        'confirm_num', 'org_confirm_num', 'org_trade_date',
        'nts_result', 'nts_result_dt', 'nts_result_code', 'nts_result_message', 'nts_send_dt',
        'franchise_corp_num', 'franchise_corp_name', 'franchise_ceo_name', 'franchise_addr', 'franchise_tel',
        'synced_at',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'supply_cost'  => 'integer',
        'tax'          => 'integer',
        'service_fee'  => 'integer',
        'state_code'   => 'integer',
        'nts_result'   => 'integer',
        'synced_at'    => 'datetime',
    ];

    /** 국세청 전송 결과가 최종 상태인지 (더 이상 갱신 불필요) */
    public function isFinal(): bool
    {
        return $this->nts_result === 2 && $this->state_code >= 300;
    }

    /** 상태 배지용 텍스트 */
    public function ntsResultLabel(): string
    {
        return match($this->nts_result) {
            0 => '전송전',
            1 => '전송중',
            2 => '성공',
            3 => '실패',
            default => '—',
        };
    }

    /** 어느 주문의 발행 건인가 — 못 이은 것은 null 이다 */
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class);
    }
}
