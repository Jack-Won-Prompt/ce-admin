<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 웹훅이 주고받는 값의 이름표 (2026-09-10 지시) */
class WebhookParam extends Model
{
    protected $fillable = [
        'webhook_id', 'position', 'name', 'data_type', 'required',
        'sample', 'description', 'sort',
    ];

    protected $casts = [
        'required' => 'boolean',
        'sort'     => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function getPositionLabelAttribute(): string
    {
        return config('webhooks.positions')[$this->position] ?? $this->position;
    }

    public function getTypeLabelAttribute(): string
    {
        return config('webhooks.types')[$this->data_type] ?? $this->data_type;
    }
}
