<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 통장 거래 한 줄 (요청서 5쪽).
 *
 * 팝빌 계좌조회가 긁어 온 것을 그대로 담는다. 은행이 준 칸은 다시 긁을 때 덮고,
 * 우리가 붙인 칸(order_id·kind·staff_memo)은 지킨다 — 담당자가 맞춰 둔 것이
 * 30분마다 도는 훑기에 지워지면 그 일을 매번 다시 해야 한다.
 */
class BankTransaction extends Model
{
    /** 무엇으로 들어온 돈인가 — 화면의 탭이 이것으로 갈린다 */
    public const KIND_COPAY  = 'copay';    // 환자 본인부담금
    public const KIND_AGENCY = 'agency';   // 기관 환급(공단·지자체)

    public const KINDS = [
        self::KIND_COPAY  => '본인부담금',
        self::KIND_AGENCY => '기관 환급',
    ];

    /** 은행이 준 칸 — 다시 긁으면 이것만 덮는다 */
    public const FROM_BANK = [
        'bank_code', 'account_number', 'trade_date', 'traded_at', 'trade_serial',
        'amount_in', 'amount_out', 'balance',
        'remark1', 'remark2', 'remark3', 'remark4', 'bank_memo',
    ];

    protected $fillable = [
        'tid', 'bank_code', 'account_number', 'trade_date', 'traded_at', 'trade_serial',
        'amount_in', 'amount_out', 'balance',
        'remark1', 'remark2', 'remark3', 'remark4', 'bank_memo',
        'order_id', 'patient_id', 'kind', 'matched_by', 'matched_at', 'staff_memo',
    ];

    protected $casts = [
        'trade_date' => 'date',
        'traded_at'  => 'datetime',
        'matched_at' => 'datetime',
        'amount_in'  => 'integer',
        'amount_out' => 'integer',
        'balance'    => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(BankTransactionSplit::class);
    }

    /**
     * 보낸 사람 이름.
     *
     * 은행마다 어느 remark 에 넣는지가 달라 팝빌은 번호로만 준다. 앞에서부터 값이 있는
     * 것을 쓴다 — 대개 remark1 이 적요, remark2 가 기재내용(보낸 사람)이다.
     */
    public function getSenderAttribute(): string
    {
        foreach ([$this->remark2, $this->remark1, $this->remark3] as $v) {
            if (trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return '';
    }

    /** 나눠 적은 몫의 합계 — 원금과 어긋나면 화면이 그것을 알린다 */
    public function getSplitTotalAttribute(): int
    {
        return (int) $this->splits->sum('amount');
    }

    /** 아직 어느 주문에도 붙지 않은 입금 */
    public function scopeUnmatched($q)
    {
        return $q->whereNull('order_id')->doesntHave('splits');
    }
}
