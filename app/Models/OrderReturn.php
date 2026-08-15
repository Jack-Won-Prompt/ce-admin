<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 교환 · 반품 · 취소 신청 한 건.
 *
 * 상태 흐름은 세 종류가 서로 다르다. 반품은 물건을 받아 보고 돈을 돌려주지만, 취소는 아직
 * 보내지 않은 것이라 수거와 검수가 없다. 교환은 돈이 오가지 않고 다시 보낸다.
 * 한 흐름으로 묶으면 취소 건에 「수거중」이 뜨는 식이 되어 담당자가 헷갈린다.
 */
class OrderReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_no', 'order_id', 'type', 'status',
        'reason_code', 'reason_text', 'shipping_burden',
        'collect_method', 'collect_tracking_no',
        'exchange_product', 'exchange_quantity', 'reship_address',
        'refund_method', 'refund_bank', 'refund_account', 'refund_holder',
        'refund_amount', 'refunded_at',
        'assigned_user_id', 'created_by',
    ];

    protected $casts = ['refunded_at' => 'datetime'];

    public const TYPE_EXCHANGE = 'exchange';
    public const TYPE_RETURN   = 'return';
    public const TYPE_CANCEL   = 'cancel';

    public const TYPES = [
        self::TYPE_EXCHANGE => '교환',
        self::TYPE_RETURN   => '반품',
        self::TYPE_CANCEL   => '취소',
    ];

    /**
     * 종류별 상태 흐름 (1차 회신 24p).
     *
     * 취소는 보낸 물건이 없어 수거·검수 단계를 두지 않는다.
     */
    public const FLOWS = [
        self::TYPE_EXCHANGE => ['received', 'collecting', 'inspecting', 'reshipping', 'done'],
        self::TYPE_RETURN   => ['received', 'collecting', 'inspecting', 'confirming', 'approved', 'refunded'],
        self::TYPE_CANCEL   => ['received', 'confirming', 'approved', 'refunded'],
    ];

    public const STATUS_LABELS = [
        'received'   => '접수',
        'collecting' => '수거중',
        'inspecting' => '검수중',
        'confirming' => '확인요청',
        'approved'   => '환불승인',
        'reshipping' => '재발송',
        'refunded'   => '환불완료',
        'done'       => '완료',
        'cancelled'  => '취소',
    ];

    /**
     * 신청 사유와 배송비 부담 주체 (CR-RTN-07).
     *
     * 사유가 정해지면 누가 무는지도 정해진다. 담당자가 매번 판단하면 사람마다 달라지고,
     * 고객에게 안내한 내용도 갈린다. 다만 고칠 수는 있게 둔다 — 예외가 늘 있다.
     */
    public const REASONS = [
        'change_mind'   => ['label' => '단순 변심',   'burden' => 'customer'],
        'size_exchange' => ['label' => '사이즈 교환', 'burden' => 'customer'],
        'defect'        => ['label' => '상품 불량',   'burden' => 'company'],
        'wrong_item'    => ['label' => '오배송',      'burden' => 'company'],
        'delay'         => ['label' => '배송 지연',   'burden' => 'company'],
        'other'         => ['label' => '기타',        'burden' => null],
    ];

    public const BURDENS = ['customer' => '고객 부담', 'company' => '판매자 부담'];

    public const COLLECT_METHODS = ['courier' => '택배 자동수거', 'self' => '고객 직접발송'];

    public const REFUND_METHODS = [
        'account' => '계좌 환불',
        'card'    => '카드 결제취소',
        'va'      => '가상계좌 환불',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderReturnLog::class)->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** 이 건이 다음에 갈 수 있는 곳. 취소는 어디서든 된다. */
    public function nextStatuses(): array
    {
        $flow = self::FLOWS[$this->type] ?? [];
        $at   = array_search($this->status, $flow, true);

        $next = ($at !== false && isset($flow[$at + 1])) ? [$flow[$at + 1]] : [];

        if (!in_array($this->status, ['cancelled', 'refunded', 'done'], true)) {
            $next[] = 'cancelled';
        }

        return $next;
    }

    /** 접수번호 — 고객에게 알려 주는 번호라 날짜와 순번으로 읽히게 만든다 */
    public static function generateReceiptNo(): string
    {
        $prefix = 'RT-' . now()->format('Ymd') . '-';

        $last = static::withTrashed()->where('receipt_no', 'like', $prefix . '%')
            ->orderByDesc('receipt_no')->value('receipt_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
