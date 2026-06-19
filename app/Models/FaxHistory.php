<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaxHistory extends Model
{
    protected $fillable = [
        'prescription_id',
        'corp_num', 'receipt_num', 'sender', 'sender_name',
        'title', 'receivers', 'file_names', 'reserve_dt',
        'request_num', 'sent_by',
        'fax_no', 'recipient_type', 'documents', 'attachment_ids', 'pdf_path',
        'popbill_state', 'popbill_result', 'synced_at',
    ];

    protected $casts = [
        'receivers'      => 'array',
        'file_names'     => 'array',
        'documents'      => 'array',
        'attachment_ids' => 'array',
        'popbill_state'  => 'integer',
        'popbill_result' => 'integer',
        'synced_at'      => 'datetime',
    ];

    public const STATE_WAIT    = 0;
    public const STATE_SENDING = 1;
    public const STATE_OK      = 2;
    public const STATE_FAIL    = 3;
    public const STATE_CANCEL  = 4;

    /** 아직 완료되지 않은 건 (동기화 대상) */
    public function scopePending($query)
    {
        return $query->whereNotIn('popbill_state', [self::STATE_OK, self::STATE_CANCEL]);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Prescription::class);
    }

    public function pdfUrl(): ?string
    {
        if (!$this->pdf_path) return null;
        return rtrim(request()->root(), '/') . '/storage/' . $this->pdf_path;
    }
}
