<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    protected $fillable = [
        'user_id', 'title', 'content', 'category',
        'status', 'answer', 'answered_by', 'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'general'   => '일반',
        'technical' => '기술',

        'other'     => '기타',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
