<?php
// app/Models/TossPayment.php

namespace App\Models;

use App\Services\TossPayments\TossClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TossPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'payment_key', 'toss_order_id', 'method',
        'status', 'amount', 'bank', 'account_number', 'customer_name',
        'due_date', 'deposited_at', 'raw_response',
        'canceled_at', 'cancel_amount', 'cancel_reason',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'deposited_at' => 'datetime',
        'canceled_at'  => 'datetime',
        'cancel_amount'=> 'integer',
        'raw_response' => 'array',
        'amount'       => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** 상태 한글 레이블 */
    public function getStatusLabelAttribute(): string
    {
        return TossClient::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    /** 상태 배지 클래스 */
    public function getStatusBadgeAttribute(): string
    {
        return TossClient::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    /** 결제 수단 — 지금은 가상계좌 하나뿐이지만 값이 그대로 보이면 읽히지 않는다 */
    public const METHOD_LABELS = [
        'VIRTUAL_ACCOUNT' => '가상계좌',
        'CARD'            => '신용카드',
        'TRANSFER'        => '계좌이체',
    ];

    public function getMethodLabelAttribute(): string
    {
        return self::METHOD_LABELS[$this->method] ?? ($this->method ?: '-');
    }

    /** 은행명 */
    public function getBankNameAttribute(): string
    {
        return TossClient::BANK_NAMES[$this->bank] ?? ($this->bank ?? '-');
    }

    /** 가상계좌 만료 여부 */
    public function getIsExpiredAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'DONE';
    }

    /** 입금 완료 여부 */
    public function getIsDoneAttribute(): bool
    {
        return $this->status === 'DONE';
    }

    /**
     * 토스가 알려 준 **실제** 결제 유형 (2026-09-09 지시).
     *
     * 「링크페이」는 우리가 무엇으로 안내했는가일 뿐, 환자가 그 창에서 무엇을 골랐는지는
     * 아니다. 카드로 냈는지 간편결제로 냈는지는 토스가 답에 실어 보낸다 —
     * method='간편결제' · easyPay.provider='토스페이' 처럼. 여태 그것을 받아 두고도
     * 쓰지 않아, 정산 화면에는 무엇으로 받았든 늘 「링크페이」라고만 섰다.
     *
     * 「간편결제 · 토스페이」ㆍ「카드 · 신한 신용」처럼 한 줄로 돌려준다.
     * 알려 준 것이 없으면 null 이다 — 그때는 부르는 쪽이 예전 이름을 쓴다.
     */
    public function getPaidTypeAttribute(): ?string
    {
        $r  = $this->raw_response ?? [];
        $무엇 = trim((string) ($r['method'] ?? ''));

        if ($무엇 === '') {
            return null;
        }

        /* 어디로 냈는지까지 알면 함께 적는다 — 「간편결제」만으로는 되짚을 때 모자란다 */
        $덧 = match ($무엇) {
            '간편결제' => $r['easyPay']['provider'] ?? null,
            '카드'     => trim(implode(' ', array_filter([
                              $r['card']['company']  ?? null,   // 신한ㆍ국민 …
                              $r['card']['cardType'] ?? null,   // 신용ㆍ체크
                          ]))) ?: null,
            default    => null,
        };

        return $덧 ? "{$무엇} · {$덧}" : $무엇;
    }
}
