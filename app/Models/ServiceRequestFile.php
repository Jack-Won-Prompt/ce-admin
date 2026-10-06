<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SR 에 붙은 파일 한 개 (2026-10-06 · SR #82ㆍ#86).
 *
 * 파일 자체는 공개 디스크에 두지 않는다 — SR 에는 환자 이름이 적힌 화면 갈무리가
 * 올라온다. 주소만 알면 열리는 자리에 두면 로그인 없이 새어 나간다.
 * 내려받기는 라우트를 지나고, 그 자리에서 로그인과 권한을 본다.
 */
class ServiceRequestFile extends Model
{
    protected $fillable = [
        'service_request_id', 'user_id',
        'original_name', 'path', 'mime', 'size', 'inline',
    ];

    protected $casts = ['inline' => 'boolean', 'size' => 'integer'];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 그림인가 — 화면에서 미리 보일지 가린다 */
    public function 그림인가(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** 사람이 읽는 크기 */
    public function 크기글(): string
    {
        $값 = (int) $this->size;

        return match (true) {
            $값 >= 1048576 => round($값 / 1048576, 1) . 'MB',
            $값 >= 1024    => round($값 / 1024) . 'KB',
            default        => $값 . 'B',
        };
    }
}
