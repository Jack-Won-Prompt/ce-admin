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
        // NICE 본인확인 결과
        'nice_verified_at',
        'nice_name',
        'nice_birthdate',
        'nice_gender',
        'nice_nation',
        'nice_mobileco',
        'nice_mobile',
        'nice_authtype',
        'nice_response_no',
        'nice_ci',
        'nice_di',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'responded_at'     => 'datetime',
        'nice_verified_at' => 'datetime',
        // CI/DI 는 민감식별정보 → 애플리케이션 레벨 암호화 저장
        'nice_ci'          => 'encrypted',
        'nice_di'          => 'encrypted',
    ];

    // 직렬화(toArray/toJson) 시 민감식별정보 노출 방지
    protected $hidden = ['nice_ci', 'nice_di'];

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

    /** NICE 본인확인이 완료되었는가 */
    public function isIdentityVerified(): bool
    {
        return $this->nice_verified_at !== null;
    }

    public function remainingMinutes(): int
    {
        if ($this->expires_at->isPast()) {
            return 0;
        }
        return (int) now()->diffInMinutes($this->expires_at, false);
    }
}
