<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 중복으로 받은 돈을 돌려주기까지의 걸음.
 *
 * 요청 → 승인 → 실제 환불 → 고객 안내. 자세한 뜻은 만든 자리의 글을 본다
 * (2026_10_02_090000_create_duplicate_payment_refunds_table).
 */
class DuplicatePaymentRefund extends Model
{
    public const 요청 = 'requested';
    public const 승인 = 'approved';
    public const 완료 = 'done';
    public const 실패 = 'failed';
    public const 반려 = 'rejected';

    public const 상태이름 = [
        self::요청 => '승인 대기',
        self::승인 => '승인됨',
        self::완료 => '환불 완료',
        self::실패 => '환불 실패',
        self::반려 => '반려',
    ];

    protected $fillable = [
        'order_id', 'payment_link_id', 'payment_key', 'toss_order_id', 'method',
        'amount', 'approved_at', 'status',
        'requested_by', 'requested_at', 'request_note',
        'approved_by', 'approved_at_by', 'reject_reason',
        'refunded_at', 'toss_response', 'notified_at', 'notify_result',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'approved_at'    => 'datetime',
        'requested_at'   => 'datetime',
        'approved_at_by' => 'datetime',
        'refunded_at'    => 'datetime',
        'notified_at'    => 'datetime',
        'toss_response'  => 'array',
    ];

    public function order(): BelongsTo        { return $this->belongsTo(Order::class); }
    public function paymentLink(): BelongsTo  { return $this->belongsTo(PaymentLink::class); }
    public function requestedBy(): BelongsTo  { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy(): BelongsTo   { return $this->belongsTo(User::class, 'approved_by'); }

    public function 상태말(): string
    {
        return self::상태이름[$this->status] ?? $this->status;
    }

    /** 아직 돈이 나가지 않았고 되돌릴 수 있는 걸음인가 */
    public function 기다리는중인가(): bool
    {
        return in_array($this->status, [self::요청, self::승인], true);
    }

    /** 이 결제를 두고 이미 올라와 있는 건이 있는가 — 같은 돈을 두 번 무르지 않는다 */
    public static function 살아있는것(string $paymentKey): ?self
    {
        return self::where('payment_key', $paymentKey)
            ->whereIn('status', [self::요청, self::승인, self::완료])
            ->first();
    }
}
