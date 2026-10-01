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
        'part_birth' => '품는 이름ㆍ생년월일',
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

    /**
     * 원본(운영 데이터 › 위임장 서명)을 이 표의 **꼴로만** 만든다 — **담지 않는다**
     * (2026-10-01 지시).
     *
     * 이관 뒤에 서명한 사람은 이 표에 없다. 그때 원본을 이 꼴로 감싸 건네면,
     * 이 표를 읽는 자리(배지ㆍ위임장ㆍ서명확인 창ㆍ개인정보 동의)를 하나도 고치지
     * 않아도 된다.
     *
     * `exists` 를 false 로 두어 실수로 save() 해도 새 줄이 생기지 않게 한다 —
     * 담는 일은 이관 명령 한 곳에서만 한다. 담아 버리면 이관이 다시 돌 때 같은
     * 서명이 두 줄이 되고, 어느 것이 원본에서 온 것인지 알 수 없게 된다.
     */
    public static function 원본꼴(DelegationSign $원본, int $patientId): self
    {
        $것 = new self();

        $것->forceFill([
            'patient_id'         => $patientId,
            'delegation_sign_id' => $원본->id,
            /* 이관 명령이 적는 값과 섞이지 않게 따로 적는다 — 화면이 「무엇으로
               이었는가」를 그대로 보여 주므로, 아직 옮겨 담기 전이라는 것이 보인다. */
            'matched_by'         => 'live',

            'customer_name'      => $원본->customer_name,
            'dealer_name'        => $원본->dealer_name,
            'phone'              => $원본->phone,
            'guardian_phone'     => $원본->guardian_phone,
            'main_contact'       => $원본->main_contact,
            'resident_no_masked' => $원본->resident_no_masked,
            'birth_date'         => $원본->birth_date,

            'guardian_name'       => $원본->guardian_name,
            'guardian_relation'   => $원본->guardian_relation,
            'guardian_birth_date' => $원본->guardian_birth_date,

            'agree_delegation'   => (bool) $원본->agree_delegation,
            'agree_privacy'      => (bool) $원본->agree_privacy,
            'agree_marketing'    => (bool) $원본->agree_marketing,

            'signed_at'          => $원본->signed_at,
            'sign_base64'        => $원본->sign_base64,
            'sign_path'          => $원본->sign_path,
        ]);

        $것->exists = false;

        return $것;
    }

    /**
     * 이 거래처의 서명 — 옮겨 담은 것이 없으면 원본에서 찾아 그 꼴로 돌려준다.
     *
     * 이 표를 읽는 모든 자리가 이 한 곳을 지나야 두 길의 답이 갈리지 않는다.
     *
     * @param string $동의칸 agree_delegation | agree_privacy
     */
    public static function 거래처것(?Patient $patient, string $동의칸 = 'agree_delegation'): ?self
    {
        if (! $patient?->id) {
            return null;
        }

        $옮긴것 = static::where('patient_id', $patient->id)
            ->where($동의칸, true)
            ->whereNotNull('signed_at')
            ->orderByDesc('signed_at')->orderByDesc('id')
            ->first();

        if ($옮긴것) {
            return $옮긴것;
        }

        $원본 = DelegationSign::거래처것($patient, $동의칸);

        return $원본 ? static::원본꼴($원본, $patient->id) : null;
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
