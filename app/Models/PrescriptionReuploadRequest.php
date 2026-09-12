<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 자료 다시 올리기 요청 (2026-09-12 지시).
 *
 * 검수 창에서 파일 한 장을 짚어 남긴다. 남긴 것은 지우지 않는다 — 같은 자료를
 * 몇 번 되물었는지가 그대로 이력이 된다.
 */
class PrescriptionReuploadRequest extends Model
{
    protected $fillable = [
        'prescription_id', 'attachment_id', 'doc_label',
        'reason', 'memo',
        'requested_by', 'requested_by_name', 'requested_at',
        'target_user_id', 'target_user_name',
        'fcm_sent', 'fcm_error',
        'resolved_at', 'resolved_attachment_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'fcm_sent'     => 'boolean',
    ];

    /** 고를 수 있는 까닭. 앞의 둘이 실제로 겪는 일이고, etc 는 그 밖을 적는 자리다. */
    public const 사유 = [
        'unreadable' => '이미지가 잘 안 보임',
        'wrong_type' => '서류 유형이 다름',
        'etc'        => '그 밖의 사유',
    ];

    /** 문자에 실어 보내는 한 줄 — 고른 까닭에 적은 말을 잇는다. */
    public function 사유말(): string
    {
        $앞 = self::사유[$this->reason] ?? $this->reason;

        return $this->memo ? $앞 . ' — ' . $this->memo : $앞;
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(PrescriptionAttachment::class, 'attachment_id');
    }
}
