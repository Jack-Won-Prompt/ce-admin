<?php
// app/Models/Patient.php

namespace App\Models;

use App\Support\ResidentNo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // 예전 counseling_data JSON 에 있던 환자 속성 (2026_08_15_000003)
        'email', 'sb_sci', 'nhis_reg_status', 'nhis_reg_date', 'nhis_renew', 'nhis_renew_due',
        'nhis_agree_start', 'nhis_agree_end', 'basic_reeval', 'basic_reeval_due',
        'cash_receipt_no', 'deduction', 'new_patient_date',
        'guardian_name', 'guardian_relation', 'guardian_birth_date', 'guardian_phone',
        'name', 'resident_no', 'birth_date', 'gender',
        'phone', 'mobile', 'address', 'postcode', 'address_detail',
        'health_insurance_no', 'is_nhis_eligible', 'nhis_coverage_rate', 'note',
        // 주민번호 암호화(P0-1)
        'resident_no_enc', 'resident_no_hash', 'resident_no_masked',
        'rrn_purpose', 'rrn_retention_basis_at', 'rrn_retention_until', 'rrn_destroyed_at',
    ];

    /** 화면·서류에 한 줄로 적는 주소 — (우편번호) 도로명 상세 */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->postcode ? '(' . $this->postcode . ')' : '',
            $this->address,
            $this->address_detail,
        ];

        return trim(implode(' ', array_filter($parts))) ?: '';
    }

    protected $casts = [
        'birth_date'       => 'date',
        'is_nhis_eligible' => 'boolean',
        'nhis_coverage_rate' => 'float',
        'rrn_retention_basis_at' => 'date',
        'rrn_retention_until'    => 'date',
        'rrn_destroyed_at'       => 'datetime',
    ];

    /** 암호문·해시·평문은 어떤 직렬화에도 실리지 않는다 */
    protected $hidden = ['resident_no', 'resident_no_enc', 'resident_no_hash'];

    /**
     * 주민번호는 항상 이 모델을 거쳐 기록한다.
     * 어느 경로에서 채워 넣든 암호문·해시·마스킹이 함께 만들어지도록 한 곳에 모은다.
     */
    public function setResidentNoAttribute(?string $value): void
    {
        // 평문 컬럼은 제거 마이그레이션 이후 존재하지 않는다.
        // 남아 있는 동안(이관 과도기)만 함께 쓴다 — 없는데 쓰면 INSERT 자체가 죽는다.
        if (self::hasPlainResidentNoColumn()) {
            $this->attributes['resident_no'] = $value;
        }

        $this->attributes['resident_no_enc']    = ResidentNo::encrypt($value);
        $this->attributes['resident_no_hash']   = ResidentNo::hash($value);
        $this->attributes['resident_no_masked'] = ResidentNo::mask($value);
        if ($value !== null && $value !== '') {
            $this->attributes['rrn_purpose'] = 'nhis_claim_form';
        }
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /** 화면·목록·엑셀·팩스 본문에 쓰는 표기. 평문을 만들지 않는다. */
    public function getMaskedResidentNoAttribute(): ?string
    {
        return $this->resident_no_masked
            ?? ResidentNo::mask($this->attributes['resident_no'] ?? null);
    }

    /**
     * 법정서식 출력 전용 평문. 사유 코드가 남고 감사로그가 기록된다.
     * 화면·목록에서는 절대 호출하지 않는다.
     */
    public function residentNoFor(string $reason): ?string
    {
        if ($this->resident_no_enc) {
            return ResidentNo::decrypt($this->resident_no_enc, $reason, [
                'type' => self::class, 'id' => $this->id, 'menu' => '환자',
            ]);
        }

        // 이관 전 과도기 — 평문 컬럼이 남아 있는 동안에도 사유는 동일하게 남긴다
        return $this->attributes['resident_no'] ?? null;
    }

    /** 조회는 평문 비교가 아니라 해시로 한다 */
    public function scopeWhereResidentNo($query, ?string $value)
    {
        return $query->where('resident_no_hash', ResidentNo::hash($value));
    }

    /** 평문 컬럼이 아직 남아 있는지 (요청당 1회만 확인) */
    public static function hasPlainResidentNoColumn(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasColumn('patients', 'resident_no');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
