<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 거래처 › 위임장 서명 — 운영 데이터에서 옮겨 담은 서명 (2026-09-29 지시).
 *
 * 운영 데이터 › 위임장 서명(DelegationSign)에서 **서명까지 받은** 줄을 옮겨 담는다.
 * 원본 표는 읽기만 하는 자리라 거래처 번호를 그쪽에 적지 않는다 — 이쪽으로 옮긴다.
 *
 * 옮겨 온 줄이지 서명을 새로 받는 자리가 아니다. 그래서 화면에서 고칠 수 있는 칸을
 * 두지 않는다($fillable 을 두지 않고 옮기는 명령이 forceFill 로 채운다).
 */
class PatientDelegationSign extends Model
{
    /** 서명 그림을 두는 곳 — 원본과 같은 폴더를 본다. 파일을 옮겨 적지 않는다. */
    public const 디스크 = DelegationSign::디스크;

    /**
     * 무엇으로 이었는가 (2026-09-29 지시).
     *
     * 이름만으로 이은 줄은 사람이 한 번 더 보아야 한다. 동명이인이 나타나면 그 줄이
     * 먼저 의심할 자리이기 때문이다 — 화면에 그대로 적는다.
     */
    public const 짝지은법 = [
        'name_birth' => '이름ㆍ생년월일',
        'name'       => '이름만',
    ];

    protected $casts = [
        'birth_date'          => 'date:Y-m-d',
        'guardian_birth_date' => 'date:Y-m-d',
        'signed_at'           => 'datetime',
        'sent_at'             => 'datetime',
        'nice_verified_at'    => 'datetime',
        'agree_delegation'    => 'boolean',
        'agree_privacy'       => 'boolean',
        'agree_marketing'     => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** 원본 줄 — 운영 데이터 › 위임장 서명. 읽기만 한다. */
    public function 원본(): BelongsTo
    {
        return $this->belongsTo(DelegationSign::class, 'delegation_sign_id');
    }

    /** 화면에 적는 이름 — (E) 를 뗀다 */
    public function 이름(): string
    {
        return Patient::bare($this->customer_name);
    }

    /** 무엇으로 이었는지 사람이 읽을 말 */
    public function 짝지은말(): string
    {
        return self::짝지은법[$this->matched_by] ?? '-';
    }

    /**
     * 서명 그림 — 파일이 있으면 파일, 없으면 표에 담아 둔 그림.
     *
     * 옮길 때 이미 status 가 signed 인 줄만 골랐으므로 여기서 다시 상태를 보지 않는다.
     * 원본(DelegationSign::서명그림)은 재발송으로 「서명 대기」로 돌아간 줄의 지난
     * 서명을 감추는데, 이 표에는 그런 줄이 애초에 들어오지 않는다.
     */
    public function 서명그림(): ?string
    {
        if ($this->sign_path && Storage::disk(self::디스크)->exists($this->sign_path)) {
            return Storage::disk(self::디스크)->get($this->sign_path);
        }

        if (! $this->sign_base64) {
            return null;
        }

        $조각 = preg_replace('~^data:image/\w+;base64,~', '', $this->sign_base64);

        return base64_decode($조각, true) ?: null;
    }

    /** 보호자 서명 그림 — 미성년의 위임은 법정대리인이 한다 */
    public function 보호자서명그림(): ?string
    {
        if ($this->guardian_sign_path && Storage::disk(self::디스크)->exists($this->guardian_sign_path)) {
            return Storage::disk(self::디스크)->get($this->guardian_sign_path);
        }

        if (! $this->guardian_signature_data) {
            return null;
        }

        $조각 = preg_replace('~^data:image/\w+;base64,~', '', $this->guardian_signature_data);

        return base64_decode($조각, true) ?: null;
    }

    /** 목록에 적는 동의 — 「동의함 / 동의 안 함」으로 읽히게 */
    public function 동의말(string $칸): string
    {
        return $this->{$칸} ? '동의함' : '동의 안 함';
    }
}
