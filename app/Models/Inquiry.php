<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 환자 문의 한 건.
 *
 * 문의자는 환자다. user_id 는 어느 앱 계정으로 올라왔는지일 뿐이며, 담당자가 전화를
 * 받아 대신 적은 건은 계정이 없고 환자만 있다.
 *
 * 답변과 조치사항을 갈라 둔다 — 답변은 환자 앱·웹에 그대로 나가고, 조치사항은
 * 안에서만 본다. 한 칸에 적으면 내부 메모가 환자에게 나간다.
 */
class Inquiry extends Model
{
    protected $fillable = [
        'user_id', 'patient_id', 'title', 'content', 'category',
        'reply_channel', 'contact',
        'status', 'answer', 'action_note', 'answered_by', 'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    /** 시안의 분류 네 가지 */
    public const CATEGORIES = [
        'purchase'     => '구매',
        'prescription' => '처방전',
        'app'          => '앱 이용',
        'other'        => '기타',
    ];

    /**
     * 이미 나간 앱이 보내는 옛 분류.
     *
     * 앱은 따로 배포되므로 새 값을 보내기까지 시간이 걸린다. 그동안 올라오는 것을
     * 거절하면 환자가 문의를 못 한다 — 받아서 가까운 자리로 옮긴다.
     */
    public const LEGACY_CATEGORIES = [
        'general'   => 'other',
        'technical' => 'app',
        'billing'   => 'purchase',
    ];

    /**
     * 상태 — 값은 그대로 두고 이름만 시안에 맞춘다.
     *
     * 이미 나간 앱이 status === 'answered' 로 「답변완료」를 그린다. 값을 갈아 끼우면
     * 그 앱에서 모든 문의가 「답변대기」로 보인다.
     */
    public const STATUSES = [
        'pending'    => '접수',
        'processing' => '처리중',
        'answered'   => '완료',
    ];

    /** 회신방식 — 접수하며 담당자가 고른다 */
    public const CHANNELS = [
        'app'   => '앱',
        'sms'   => '문자',
        'phone' => '전화',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InquiryMessage::class)->orderBy('created_at');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAnswered(): bool
    {
        return $this->status === 'answered';
    }

    public function categoryLabel(): string
    {
        $key = self::LEGACY_CATEGORIES[$this->category] ?? $this->category;

        return self::CATEGORIES[$key] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->reply_channel] ?? '—';
    }

    /** 문의자 이름 — 환자가 주인이고, 없으면 올린 계정으로 대신한다 */
    public function askerName(): string
    {
        return $this->patient?->name ?? $this->user?->name ?? '—';
    }

    /** 회신할 곳 — 접수 때 적어 둔 것이 먼저다 */
    public function contactNumber(): string
    {
        return $this->contact
            ?: ($this->patient?->mobile ?: ($this->patient?->phone ?: ($this->user?->phone ?? '')));
    }

    /**
     * 환자가 앱에 적어 올린 본문.
     *
     * 첫 메시지가 본문이다 — 문의는 대화식으로 쌓이므로 뒤엣것은 덧붙인 말이다.
     */
    public function bodyText(): string
    {
        return $this->content ?: (string) ($this->messages->first()?->body ?? '');
    }

    /** 붙은 파일 수 — 목록에는 몇 개인지만 세운다 */
    public function attachmentCount(): int
    {
        return $this->messages->filter(fn (InquiryMessage $m) => (bool) $m->attachment_path)->count();
    }
}
