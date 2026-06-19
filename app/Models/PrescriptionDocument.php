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
            'fax'          => '팩스통합본',
            'cash_receipt' => '현금영수증',
            'tax_invoice'  => '세금계산서',
            default        => $this->type,
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->type) {
            'consent'      => '서명 완료',
            'fax'          => '팩스 전송',
            'cash_receipt' => '현금영수증 발행',
            'tax_invoice'  => '세금계산서 발행',
            default        => '-',
        };
    }
}
