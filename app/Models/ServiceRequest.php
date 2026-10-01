<?php
// app/Models/ServiceRequest.php
// SR(Service Request) — 화면·기능 요청과 답변.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    protected $fillable = [
        'user_id', 'title', 'content', 'category', 'priority',
        'status', 'page_label', 'page_url',
        'answer', 'answered_by', 'answered_at',
    ];

    protected $casts = ['answered_at' => 'datetime'];

    public const CATEGORIES = [
        'improve'  => '개선 요청',
        'bug'      => '오류 신고',
        'question' => '문의',
        'etc'      => '기타',
    ];

    public const PRIORITIES = [
        'low'    => '낮음',
        'normal' => '보통',
        'high'   => '높음',
        'urgent' => '긴급',
    ];

    /**
     * 진행 상태 (2026-10-01 지시).
     *
     * 전에는 접수ㆍ처리중ㆍ답변완료ㆍ종결이었다. 「답변완료」는 답변을 적었는가를
     * 말하고 「완료」는 일이 끝났는가를 말한다 — 둘은 다르고, 담당자가 보는 것은
     * 뒤쪽이다. 「종결」 자리에는 「대기」를 둔다. 대기는 끝난 것이 아니라 멈춰 둔
     * 것이고, 끝난 건은 완료 하나로 족하다.
     *
     * 바꿀 때 service_requests 는 0줄이었다 — 옮길 자료가 없었다.
     */
    public const STATUSES = [
        'new'         => '신규',
        'in_progress' => '진행중',
        'done'        => '완료',
        'hold'        => '대기',
    ];

    /** 새 SR 이 서는 자리 */
    public const STATUS_DEFAULT = 'new';

    /** 답변을 적으면 저절로 옮겨 가는 자리 — 담당자가 달리 고르면 그것을 따른다 */
    public const STATUS_ANSWERED = 'done';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function priorityLabel(): string { return self::PRIORITIES[$this->priority] ?? $this->priority; }
    public function statusLabel(): string   { return self::STATUSES[$this->status]   ?? $this->status; }

    /** 답변이 달렸는가 */
    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /** 목록·패널 공용 직렬화 (wwGrid 행 + 상세 표시) */
    public function toRow(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'content'     => $this->content,
            'category'    => $this->category,
            'categoryLabel' => $this->categoryLabel(),
            'priority'    => $this->priority,
            'priorityLabel' => $this->priorityLabel(),
            'status'      => $this->status,
            'statusLabel' => $this->statusLabel(),
            'page'        => $this->page_label ?? '',
            'page_url'    => $this->page_url ?? '',
            'writer'      => $this->user?->name ?? '-',
            'answer'      => $this->answer ?? '',
            'answerer'    => $this->answeredBy?->name ?? '',
            'answered_at' => $this->answered_at?->format('Y-m-d H:i') ?? '',
            'created'     => $this->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
