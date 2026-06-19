<?php
// app/Models/NhisFaxLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhisFaxLog extends Model
{
    protected $fillable = [
        'order_id', 'sent_by',
        'fax_number', 'sender_number', 'document_title',
        'claim_amount', 'nhis_amount', 'patient_copay',
        'status', 'reference_no', 'error_message', 'retry_count',
        'sent_at', 'confirmed_at',
        'nhis_result', 'approved_amount', 'nhis_message', 'nhis_result_at',
        'raw_payload',
    ];

    protected $casts = [
        'claim_amount'    => 'float',
        'nhis_amount'     => 'float',
        'patient_copay'   => 'float',
        'approved_amount' => 'float',
        'sent_at'         => 'datetime',
        'confirmed_at'    => 'datetime',
        'nhis_result_at'  => 'datetime',
    ];

    public const STATUS_LABELS = [
        'queued'  => ['label' => '대기중',    'badge' => 'secondary'],
        'sending' => ['label' => '전송중',    'badge' => 'warning'],
        'sent'    => ['label' => '전송완료',  'badge' => 'success'],
        'failed'  => ['label' => '전송실패',  'badge' => 'danger'],
    ];

    public const NHIS_RESULT_LABELS = [
        'pending'  => ['label' => '결과 대기',  'badge' => 'secondary'],
        'approved' => ['label' => '승인',        'badge' => 'success'],
        'rejected' => ['label' => '거부',        'badge' => 'danger'],
        'partial'  => ['label' => '부분 승인',   'badge' => 'warning'],
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? $this->status;
    }

    public function getNhisResultLabelAttribute(): string
    {
        return self::NHIS_RESULT_LABELS[$this->nhis_result]['label'] ?? $this->nhis_result;
    }
}
