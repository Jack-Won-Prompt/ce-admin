<?php
// app/Models/PrescriptionConsent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionConsent extends Model
{
    protected $fillable = [
        'prescription_id',
        'token',
        'patient_name',
        'patient_mobile',
        'signature_data',
        'status',
        'expires_at',
        'responded_at',
        'pdf_path',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'responded_at'  => 'datetime',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === 'expired';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => '대기 중',
            'agreed'   => '동의 완료',
            'declined' => '거절',
            'expired'  => '만료',
            default    => $this->status,
        };
    }

    public function remainingMinutes(): int
    {
        if ($this->expires_at->isPast()) {
            return 0;
        }
        return (int) now()->diffInMinutes($this->expires_at, false);
    }
}
