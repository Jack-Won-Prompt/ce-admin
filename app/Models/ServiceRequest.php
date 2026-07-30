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

    public const STATUSES = [
        'open'        => '접수',
        'in_progress' => '처리중',
        'answered'    => '답변완료',
        'closed'      => '종결',
    ];

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
            'created'     => $this->created_at?->format('Y-m-d H:i') ?? '',
        ];
    }
}
