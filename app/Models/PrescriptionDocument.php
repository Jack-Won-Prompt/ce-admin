<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDocument extends Model
{
    protected $fillable = [
        'prescription_id',
        'patient_id',
        'created_by',
        'type',
        'file_path',
        'original_filename',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'consent'      => '위임동의서',
            'delegation'   => '요양비위임장',
            'fax'          => '팩스통합본',
            'cash_receipt' => '현금영수증',
            'tax_invoice'  => '세금계산서',
            default        => $this->type,
        };
    }

    /** 서류 관리 화면에서 직접 업로드한 건인가 (저장 경로로 판별 — 자동 생성본과 구분) */
    public function isManuallyRegistered(): bool
    {
        return str_starts_with((string) $this->file_path, 'documents/manual/');
    }

    public function sourceLabel(): string
    {
        if ($this->isManuallyRegistered()) {
            return '직접 등록';
        }

        return match ($this->type) {
            'consent'      => '서명 완료',
            'delegation'   => '서명 완료(자동)',
            'fax'          => '팩스 전송',
            'cash_receipt' => '현금영수증 발행',
            'tax_invoice'  => '세금계산서 발행',
            default        => '-',
        };
    }
}
