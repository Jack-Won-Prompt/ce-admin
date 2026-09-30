<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 결제가 지나간 걸음 한 줄 (2026-09-30 지시).
 *
 * `payment_links` 와 `toss_payments` 는 **지금 어떤가**를 담는 표라 걸음마다 덮어쓴다.
 * 이 표는 **어떻게 여기까지 왔나**를 담는다 — 한 번 적으면 고치지 않는다.
 *
 * 적는 일은 PaymentLink 를 지켜보는 자리(PaymentLinkObserver)가 한다. 부르는 곳이
 * 열한 군데라 자리마다 적게 하면 반드시 빠뜨린다 — 상태가 바뀌는 그 순간에 한 번
 * 적는다.
 */
class PaymentEvent extends Model
{
    /** 보냈다 — 돈은 아직 오가지 않았다 */
    public const KIND_SENT = 'sent';
    /** 받았다 */
    public const KIND_PAID = 'paid';
    /** 받은 뒤 돌려주었다 */
    public const KIND_REFUNDED = 'refunded';
    /** 받기 전에 거두었다 */
    public const KIND_CANCELLED = 'cancelled';
    public const KIND_FAILED = 'failed';
    public const KIND_EXPIRED = 'expired';

    public const KINDS = [
        self::KIND_SENT      => '발송',
        self::KIND_PAID      => '승인',
        self::KIND_REFUNDED  => '환불',
        self::KIND_CANCELLED => '취소',
        self::KIND_FAILED    => '실패',
        self::KIND_EXPIRED   => '기한지남',
    ];

    /** 돈이 실제로 오간 걸음 — 합을 셀 때 쓴다 */
    public const 돈이오간것 = [self::KIND_PAID, self::KIND_REFUNDED];

    protected $fillable = [
        'order_id', 'payment_link_id', 'kind', 'method',
        'amount', 'occurred_at', 'payment_key', 'note',
    ];

    protected $casts = [
        'amount'      => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id');
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /**
     * 걸음 하나를 적는다.
     *
     * 금액은 **부호를 담아** 적는다 — 승인은 양수, 환불ㆍ취소는 음수다. 줄을 그대로
     * 더하면 지금 남은 돈이 나온다.
     *
     * 같은 걸음을 두 번 적지 않는다 — 웹훅과 화면 승인이 거의 동시에 같은 상태를
     * 적어 넣는 일이 있다(자동발행에서 겪었다). 같은 링크ㆍ같은 갈래ㆍ같은 시각이면
     * 이미 적힌 것으로 본다.
     */
    public static function 적기(PaymentLink $link, string $kind, ?string $note = null): ?self
    {
        $금액 = match ($kind) {
            self::KIND_PAID                            => (int) $link->amount,
            self::KIND_REFUNDED                        => -(int) $link->amount,
            /* 받기 전에 거둔 링크는 돈이 오가지 않았다 — 0 이다.
               받은 뒤에 거둔 것이면 그것은 환불이지 취소가 아니다. */
            self::KIND_CANCELLED                       => $link->paid_at ? -(int) $link->amount : 0,
            default                                    => 0,
        };

        $때 = match ($kind) {
            self::KIND_PAID => $link->paid_at ?? now(),
            self::KIND_SENT => $link->sent_at ?? now(),
            default         => now(),
        };

        $이미 = static::where('payment_link_id', $link->id)
            ->where('kind', $kind)
            ->where('occurred_at', $때)
            ->exists();

        if ($이미) {
            return null;
        }

        return static::create([
            'order_id'        => $link->order_id,
            'payment_link_id' => $link->id,
            'kind'            => $kind,
            'method'          => $link->method,
            'amount'          => $금액,
            'occurred_at'     => $때,
            'payment_key'     => $link->payment_key,
            'note'            => $note,
        ]);
    }
}
