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

    /**
     * 첨부 서류 종류.
     *
     * 등록신청서·결과지는 공단 환자 등록·재등록(Step1)을 e-Fax 로 보낼 때 쓴다.
     * 병원에서 받아 오는 종이라 시스템이 만들 수 없고 첨부로 받는다.
     */
    public const DOC_TYPE_LABELS = [
        'prescription'      => '처방전',
        'id_card'           => '주민등록증',
        'registration_form' => '등록신청서',
        'test_result'       => '결과지',
        'delegation'        => '위임장',
        'other'             => '기타',
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
        // 신분증·위임장이 담긴다. storage 직결 대신 로그인·권한을 거치게 한다.
        return $this->file_path && $this->exists
            ? route('files.prescription-attachment', $this)
            : null;
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
