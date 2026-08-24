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
        'care_type', 'name', 'resident_no', 'birth_date', 'gender',
        'phone', 'mobile', 'address', 'postcode', 'address_detail',
        'health_insurance_no', 'is_nhis_eligible', 'nhis_coverage_rate', 'note',
        // 주민번호 암호화(P0-1)
        'resident_no_enc', 'resident_no_hash', 'resident_no_masked',
        'rrn_purpose', 'rrn_retention_basis_at', 'rrn_retention_until', 'rrn_destroyed_at',
    ];

    /** IC = 카테터 · OC = 장루. 개인정보 동의서의 catheter/stoma 와 같은 축이다. */
    public const CARE_TYPES = ['IC' => 'IC (카테터)', 'OC' => 'OC (장루)'];

    /*
     * IC 거래처는 이름 앞에 (E) 를 달아 둔다. 위드웍스의 개인 거래처 표기가 그렇고
     * ((E)김현열), 그 이름 그대로 오가야 두 시스템에서 같은 거래처로 읽힌다.
     * 표시만 바꾸지 않고 저장되는 이름에 넣는다(요청).
     *
     * 어느 길로 저장하든 규칙이 한 번만 적용되도록 모델에서 건다 — 화면이 여럿이라
     * 각자 붙이면 「(E)(E)김콜로」 처럼 겹치거나, 어느 화면에서만 빠진다.
     */
    protected static function booted(): void
    {
        static::saving(function (self $p) {
            /* 사업부를 모르면 이름에 손대지 않는다. 예전에 사람 손으로 (E) 를 달아 둔
               이름이 있는데, 사업부가 비었다는 이유로 그것을 떼면 안 된다. */
            if ($p->care_type !== null && $p->care_type !== '') {
                $p->name = self::nameWithCareTag($p->name, $p->care_type);
            }
        });

        /* 신환 Master 등록일 — 이 사람이 마스터에 처음 오른 날이다.
           주문 등록에서 기존에 없던 사람으로 저장하면 그날이 곧 이 날짜다. 담당자가
           손으로 적어 넣었으면(위드웍스에서 옮겨 온 날짜 같은 것) 그것을 지키고,
           비어 있을 때만 오늘로 채운다. 어느 화면에서 만들든 같게 두려고 여기 건다. */
        static::creating(function (self $p) {
            if (empty($p->new_patient_date)) {
                $p->new_patient_date = now()->toDateString();
            }
        });
    }

    /** 이름에 (E) 를 맞춰 단다 — IC 면 붙이고, 아니면 뗀다. 이미 붙어 있어도 겹치지 않는다. */
    public static function nameWithCareTag(?string $name, ?string $careType): ?string
    {
        $bare = trim(preg_replace('/^\s*\(E\)\s*/u', '', (string) $name));
        if ($bare === '') {
            return $name;   // 이름이 비어 있으면 손대지 않는다 — 검증이 걸러 낼 몫이다
        }
        return $careType === 'IC' ? '(E)' . $bare : $bare;
    }

    /* 사업부 칸은 서버마다 있을 수도, 없을 수도 있다(마이그레이션 대기).
       없는 곳에 쓰려 들면 질의가 깨지므로 한 번만 물어 기억해 둔다. */
    private static ?bool $hasCareType = null;

    public static function hasCareTypeColumn(): bool
    {
        return self::$hasCareType ??= \Illuminate\Support\Facades\Schema::hasColumn('patients', 'care_type');
    }

    /** (E) 를 뗀 이름. 찾을 때ㆍ서류에 적을 때 쓴다. */
    public function getBareNameAttribute(): string
    {
        return trim(preg_replace('/^\s*\(E\)\s*/u', '', (string) $this->name));
    }

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

    /** 개인정보 동의서. 밖에서 들어오는 폼이라 이어지지 않은 것도 있다(patient_id 가 빈다). */
    public function privacyConsents(): HasMany
    {
        return $this->hasMany(PrivacyConsent::class);
    }
}
