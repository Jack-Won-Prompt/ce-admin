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
        'id_card'           => '신분증',
        'registration_form' => '등록신청서',
        'test_result'       => '결과지',
        'delegation'        => '위임장',
        'other'             => '기타',
    ];

    /**
     * 코드에 붙은 이름.
     *
     * 서류명은 환경 설정(공통 코드)에서 정한다. 예전에 박아 둔 상수는 이미 쌓인
     * 자료를 읽기 위해 남겨 둔다 — 둘 다 없으면 「기타」다.
     */
    public static function labelFor(?string $code): string
    {
        if (!$code) {
            return '기타';
        }

        return \App\Models\CommonCode::labels('doc_type')[$code]
            ?? (self::DOC_TYPE_LABELS[$code] ?? '기타');
    }

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

    /**
     * 화면에 적을 이름.
     *
     * 적어 둔 이름(doc_label)은 「기타」에 무엇인지 직접 쓰라고 둔 칸이다. 정해진 유형에는
     * 지금 쓰는 이름을 쓴다 — 예전에 「주민등록증」으로 적힌 건이 유형 이름을 신분증으로
     * 바꾼 뒤에도 옛 이름으로 남아, 같은 종류가 두 이름으로 보였다.
     */
    public function getDocTypeLabelAttribute(): string
    {
        if ($this->doc_type && $this->doc_type !== 'other') {
            return self::labelFor($this->doc_type) ?: ($this->doc_label ?: '기타');
        }

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
