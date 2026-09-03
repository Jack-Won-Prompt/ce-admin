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
    /**
     * 이름표 — 쌓인 것을 읽을 때 쓴다.
     *
     * 가상계좌는 더 고를 수 없지만(SELECTABLE 참고) 예전에 그것으로 보낸 건이 남아
     * 있어 이름은 그대로 둔다 — 빼면 그 줄들이 코드값(virtual)으로 보인다.
     * 「카드결제」는 「링크페이」로 부른다. 설정 화면과 주문 확정 안내가 이미 그렇게
     * 부르고 있어, 한 가지를 두 이름으로 부르고 있었다.
     */
    public const METHODS = [
        self::METHOD_CARD    => '링크페이',
        self::METHOD_VIRTUAL => '가상계좌',
        self::METHOD_BANK    => '무통장입금',
    ];

    /**
     * 지금 고를 수 있는 것 — 결제 전송 팝오버와 주문 등록의 상세 목록 탭이 세운다.
     *
     * 가상계좌를 한때 뺐다. 발급 단추를 화면에서 걷은 뒤로 담당자가 해 줄 수 없는
     * 것인데 고르면 환자에게는 그렇게 약속이 나갔기 때문이다. 이제 연계할 때
     * 스스로 발급하므로 되살린다(2026-09-03 지시).
     *
     * 무통장입금은 목록에 두지 않는다. 토스 키가 없을 때 내려앉는 자리라 코드는
     * 그대로 두지만, 고르는 것은 둘이다.
     */
    public const SELECTABLE = [
        self::METHOD_CARD    => '링크페이',
        self::METHOD_VIRTUAL => '가상계좌',
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
