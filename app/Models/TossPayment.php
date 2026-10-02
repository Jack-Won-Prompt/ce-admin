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
        // 카드 매입 상태 (2026-10-02 지시) — 토스에 다시 물어 받은 값
        'acquire_status', 'acquire_checked_at',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'deposited_at' => 'datetime',
        'canceled_at'  => 'datetime',
        'cancel_amount'=> 'integer',
        'raw_response' => 'array',
        'amount'       => 'integer',
        'acquire_checked_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 카드 매입 상태 — 토스가 주는 코드와 우리 말ㆍ빛깔 (2026-10-02 지시).
     *
     * 취소에 걸리는 시간이 여기서 갈린다(토스 고객센터 안내).
     *
     *   매입 전 취소(전체) : 결제 **당일에만** 가능 · 즉시
     *   매입 전 취소(부분) : 영업일 3~4일
     *   매입 후 취소       : 영업일 3~4일
     */
    public const 매입 = [
        'READY'            => ['매입 전',    'warning'],
        'REQUESTED'        => ['매입 요청',  'info'],
        'COMPLETED'        => ['매입 완료',  'success'],
        'CANCEL_REQUESTED' => ['매입 취소 요청', 'info'],
        'CANCELED'         => ['매입 취소',  'secondary'],

        /* 토스가 카드 정보를 주지 않는 건 (2026-10-02 확인).

           `method` 는 CARD 인데 응답에 `card` 가 없는 줄이 운영에 둘 있다. 물어봐도
           값이 없으므로 「알 수 없음」이라 적어 두면 담당자가 고장으로 읽고, 30분마다
           영영 다시 묻게 된다. 물어본 사실을 이 값으로 적어 두고 더 묻지 않는다. */
        'NONE'             => ['매입 정보 없음', 'muted'],
    ];

    /** 카드로 받은 건인가 — 가상계좌ㆍ간편결제에는 매입이라는 걸음이 없다 */
    public function 카드인가(): bool
    {
        return in_array(strtoupper((string) $this->method), ['CARD', '카드'], true)
            || ! empty(($this->raw_response['card'] ?? null));
    }

    /**
     * 화면에 적을 매입 상태.
     *
     * 아직 물어본 적이 없으면 「확인 전」이라 적는다. 승인할 때 받아 둔 사본은
     * **바뀌지 않으므로**(운영 카드 23건이 모두 READY 였다) 그 값을 「지금 그렇다」고
     * 읽으면 한 달 전 결제도 영영 「매입 전」이 된다.
     *
     * @return array{label: string, tone: string, at: ?string, stale: bool}
     */
    public function 매입상태(): array
    {
        if (! $this->카드인가()) {
            return ['label' => '해당 없음', 'tone' => 'muted', 'at' => null, 'stale' => false];
        }

        if (! $this->acquire_checked_at) {
            return ['label' => '확인 전', 'tone' => 'muted', 'at' => null, 'stale' => true];
        }

        [$말, $빛] = self::매입[strtoupper((string) $this->acquire_status)] ?? ['알 수 없음', 'muted'];

        return [
            'label' => $말,
            'tone'  => $빛,
            'at'    => $this->acquire_checked_at->format('Y-m-d H:i'),
            'stale' => false,
        ];
    }

    /**
     * 더 물어볼 것이 남았나 — 매입이 끝나거나 취소되면 더 바뀌지 않는다.
     *
     * 끝난 값까지 되물으면 결제가 쌓일수록 토스를 부르는 횟수만 늘어난다.
     */
    public function 매입더볼까(): bool
    {
        if (! $this->카드인가()) {
            return false;
        }

        return ! in_array(strtoupper((string) $this->acquire_status),
            ['COMPLETED', 'CANCELED', 'NONE'], true);
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
     * 승인이 났고 **아직 그 돈을 쥐고 있는가** (2026-09-28 시험에서 드러남).
     *
     * is_done 은 status==='DONE' 하나만 본다. 부분 취소가 일어나면 토스가 상태를
     * PARTIAL_CANCELED 로 바꾸므로 is_done 이 거짓이 되고, 받은금액()ㆍ결제기준금액()
     * 이 「한 번도 받은 적 없음」으로 떨어졌다 — 19,500원을 무르고 3,000원이 남아
     * 있는데 받은 돈이 0원으로 읽혔다.
     *
     * 전액 취소(CANCELED)는 들지 않는다. 그때는 쥐고 있는 돈이 정말로 없다.
     */
    public function 쥐고있나(): bool
    {
        return in_array($this->status, ['DONE', 'PARTIAL_CANCELED'], true);
    }

    /** 무른 것을 뺀, 지금 우리가 쥐고 있는 돈 */
    public function 남은금액(): int
    {
        return $this->쥐고있나()
            ? max(0, (int) $this->amount - (int) ($this->cancel_amount ?? 0))
            : 0;
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
