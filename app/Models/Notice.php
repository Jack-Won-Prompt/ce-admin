<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    protected $fillable = [
        'title', 'content', 'is_pinned', 'is_active', 'views', 'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }
}
