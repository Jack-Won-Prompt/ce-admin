<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * 환자에게 보낸 결제 요청 한 건.
 *
 * 카드·가상계좌는 우리 결제 페이지를 열어 토스로 내고, 무통장입금은 우리 계좌를 적어
 * 보낸다 — 셋 다 「보냈다」는 사실과 「냈다」는 사실을 같은 자리에 남긴다.
 */
class PaymentLink extends Model
{
    use SoftDeletes;

    public const METHOD_CARD    = 'card';
    public const METHOD_VIRTUAL = 'virtual';
    public const METHOD_BANK    = 'bank';

    /** 무통장입금은 토스를 타지 않는다 — 우리 계좌를 적어 보내고 입금 확인은 사람이 한다 */
    public const METHODS = [
        self::METHOD_CARD    => '카드결제',
        self::METHOD_VIRTUAL => '가상계좌',
        self::METHOD_BANK    => '무통장입금',
    ];

    public const STATUSES = [
        'sent'      => ['보냄',     'info'],
        'paid'      => ['결제완료', 'success'],
        'expired'   => ['기한지남', 'secondary'],
        'cancelled' => ['취소',     'secondary'],
        'failed'    => ['보내지 못함', 'danger'],
    ];

    protected $fillable = [
        'order_id', 'token', 'method', 'amount', 'status',
        'channel', 'receiver', 'sent_at', 'paid_at', 'expires_at',
        'payment_key', 'toss_order_id', 'error', 'created_by',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'sent_at'    => 'datetime',
        'paid_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 남이 찍어 볼 수 없을 만큼 길게 */
    public static function newToken(): string
    {
        return Str::lower(Str::random(48));
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusToneAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    /** 아직 낼 수 있는가 — 기한이 지났으면 결제 페이지를 열어 주지 않는다 */
    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'sent'
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function getUrlAttribute(): string
    {
        return route('pay.show', $this->token);
    }
}
