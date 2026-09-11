<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 서버에서 난 잘못 한 건 (2026-09-11 지시).
 *
 * 같은 자리에서 같은 잘못이 되풀이되면 줄을 새로 세우지 않고 셈만 올린다 —
 * 백 번 난 잘못이 백 줄이면 정작 한 번 난 다른 잘못이 묻힌다.
 */
class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint', 'source', 'level', 'kind', 'exception', 'http_status',
        'message', 'file', 'line', 'col', 'trace',
        'url', 'http_method', 'route_name', 'ip', 'user_agent',
        'user_id', 'user_name', 'input',
        'hit', 'first_at', 'last_at',
        'status', 'checked_by', 'checked_at', 'memo',
    ];

    protected $casts = [
        'first_at'   => 'datetime',
        'last_at'    => 'datetime',
        'checked_at' => 'datetime',
    ];

    /** 처리 상태 — 담당자가 붙이는 표시 */
    public const 상태 = [
        'open'    => '미확인',
        'checked' => '확인',
        'fixed'   => '조치 완료',
        'ignored' => '보류',
    ];

    /** 어디서 난 오류인가 */
    public const 출처 = [
        'server'  => '서버',
        'browser' => '브라우저',
    ];

    public const 상태색 = [
        'open'    => 'danger',
        'checked' => 'warning',
        'fixed'   => 'success',
        'ignored' => 'muted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function getSourceLabelAttribute(): string
    {
        return self::출처[$this->source] ?? $this->source;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::상태[$this->status] ?? $this->status;
    }

    /** 파일 자리는 프로젝트 안쪽만 보여 준다 — 앞의 긴 경로는 읽는 데 방해가 된다 */
    public function getShortFileAttribute(): string
    {
        $f = (string) $this->file;
        $i = strpos($f, 'ce-admin');

        return $i === false ? $f : substr($f, $i + 9);
    }

    /** 목록에 한 줄로 적을 자리 — 파일:줄(:열) */
    public function getWhereAttribute(): string
    {
        if (! $this->file) {
            return '-';
        }

        return $this->short_file . ':' . $this->line . ($this->col ? ':' . $this->col : '');
    }
}
