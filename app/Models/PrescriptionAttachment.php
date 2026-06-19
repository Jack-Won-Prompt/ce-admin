<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PrescriptionAttachment extends Model
{
    protected $fillable = [
        'prescription_id', 'file_path', 'file_original_name', 'file_mime_type',
        'file_size', 'doc_type', 'doc_label', 'ocr_raw_text', 'ocr_confidence',
        'display_order', 'uploaded_by',
    ];

    public const DOC_TYPE_LABELS = [
        'prescription' => '처방전',
        'id_card'      => '주민등록증',
        'delegation'   => '위임장',
        'other'        => '기타',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getDocTypeLabelAttribute(): string
    {
        return $this->doc_label ?: (self::DOC_TYPE_LABELS[$this->doc_type] ?? '기타');
    }

    public function getIsImageAttribute(): bool
    {
        $mime = $this->file_mime_type ?? '';
        return str_starts_with($mime, 'image/');
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->file_mime_type === 'application/pdf';
    }
}
