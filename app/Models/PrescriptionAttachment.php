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
        // 문서마다의 밝기ㆍ명암 — 파일은 그대로 두고 숫자만 적어 둔다(2026-09-09)
        'img_brightness', 'img_contrast',
    ];

    protected static function booted(): void
    {
        /* 서류가 한 장이라도 붙으면 더는 「빈 초안」이 아니다 (2026-09-09 지시).

           빈 초안 표시는 처방전 줄 자체가 저장될 때만 풀린다. 그런데 앱으로 올린 건은
           처방전 줄을 만들어 두고 파일만 붙이므로 그 표시가 켜진 채로 남았다.
           그러면 주문 등록 화면이 「주문 목록」 탭으로 열려, 웹으로 올린 건과 달리
           상세 목록이 보이지 않았다(2026-09-08 확인요청 10쪽).

           blankDraft 잣대도 이미 「딸린 것이 하나라도 있으면 빈 초안이 아니다」로
           보고 있다 — 표시만 그 뜻을 따라가지 못했다. */
        static::created(function (self $a) {
            $rx = $a->prescription;

            if ($rx && $rx->is_blank_draft) {
                $rx->forceFill(['is_blank_draft' => false])->saveQuietly();
            }

            /* 다시 올려 달라고 물었던 서류가 올라왔으면 그 요청을 닫는다
               (2026-09-12 지시).

               사람이 손으로 닫게 하면 닫히지 않는다 — 올린 사람은 다시 올렸으니
               끝났다고 여기고, 검수자는 목록에서 「요청 중」이 그대로인 것만 본다.
               올리는 길이 앱ㆍ웹으로 갈려 있어 붙는 자리 한 곳에서 닫는다. */
            if ($rx) {
                app(\App\Services\ReuploadRequestService::class)
                    ->닫기($rx, $a->doc_type_label, $a->id);
            }
        });
    }

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
