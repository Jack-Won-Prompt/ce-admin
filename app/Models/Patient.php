<?php
// app/Models/Patient.php

namespace App\Models;

use App\Support\ResidentNo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        // 화면 확정요청 2026-08-27 — 거래처관리를 환자 정보의 정본으로
        'remitter_name', 'contact_channel', 'contact_status', 'fax',
        // 어떻게 내는 사람인가 · 마지막으로 발급한 가상계좌(2026-09-03)
        'pay_method', 'va_bank', 'va_account', 'va_holder', 'va_due_at', 'va_order_id',
        'created_by', 'updated_by',
    ];

    /**
     * 어디로 거는 것이 나은가.
     *
     * 사람마다 닿는 길이 다르다 — 전화를 안 받고 카톡만 보는 환자가 있고, 그 반대도
     * 있다. 적어 두지 않으면 담당자가 바뀔 때마다 처음부터 다시 알아내야 한다.
     */
    public const CONTACT_CHANNELS = [
        'phone'      => '전화',
        'happytalk'  => '해피톡',
        'sms2586'    => '업무폰2586 문자',
        'kakao2586'  => '업무폰2586 카톡',
        'sms3018'    => '업무폰3018 문자',
        'kakao3018'  => '업무폰3018 카톡',
    ];

    /**
     * 연락이 닿는가.
     *
     * 예전에는 Active/Inactive 둘이었다. 왜 안 닿는지가 다음에 할 일을 정한다 —
     * 사망과 수신거부와 타사이동은 서로 다른 일이고, 재구매 안내를 보낼지도 갈린다.
     */
    public const CONTACT_STATUSES = [
        'normal'      => '정상',
        'deceased'    => '사망',
        'recovered'   => '회복',
        'optout'      => '수신거부',
        'unreachable' => '연결실패',
        'moved'       => '타사이동',
        'device_buy'  => '의료기상 구매(콜로)',
        'cic_stopped' => 'CIC중단',
        'etc'         => '기타',
    ];

    /** 현금영수증 발행 방식 — 주문 등록의 같은 칸과 값이 같아야 한다 */
    public const DEDUCTION_TYPES = ['소득공제', '지출증빙', '자진발급'];

    /** 자진발급이면 번호가 정해져 있다 — 국세청이 쓰는 자리다 */
    public const SELF_ISSUE_NO = '010-000-1234';

    /**
     * 공단 등록 상태 (화면 확정요청 2026-08-27, 11쪽).
     *
     * 「진행중ㆍ완료」 둘로는 신규인지 재등록인지 알 수 없었다. 공단에 내는 서류도
     * 다음에 할 일도 그 둘이 다르다.
     */
    public const NHIS_REG_STATUSES = [
        '신규 등록 진행중',
        '신규 등록 완료',
        '재등록 진행중',
        '재등록 완료',
        '필요없음',
    ];

    /** 예ㆍ아니오만 받는 칸 — 「대상 여부 또는 비고」로 두었더니 사람마다 다르게 적혔다 */
    public const YN = ['Y', 'N'];

    /** IC = 카테터 · OC = 장루. 개인정보 동의서의 catheter/stoma 와 같은 축이다. */
    public const CARE_TYPES = ['IC' => 'IC (카테터)', 'OC' => 'OC (장루)'];

    /**
     * 환자구분 (요청서 2쪽, 2026-08-31).
     *
     * 예전에는 SB·SCI 둘뿐이었다. 그것으로는 같은 SB 안에서 갈리는 것을 적을 데가 없어
     * 다섯으로 늘렸다.
     *
     * 이미 적힌 SB·SCI 는 그대로 둔다 — 요청이 「기존 위드웍스 data 는 유지, 앞으로
     * 신규 등록 때만」이다. 그래서 목록에는 없지만 저장된 값이 옛것이면 화면이 그 값을
     * 선택지로 한 줄 더 세운다(optionsFor). 목록에 없다고 지워 버리면 고르지도 않은
     * 사이에 값이 날아간다.
     */
    public const SB_SCI = ['SB-O', 'SB-N', 'SCI-O', 'SCI-N', 'NB'];

    /**
     * 화면에 세울 선택지 — 저장된 값이 목록 밖이면 그것을 앞에 붙인다.
     *
     * @return list<string>
     */
    public static function sbSciOptions(?string $current = null): array
    {
        $current = trim((string) $current);

        return $current !== '' && !in_array($current, self::SB_SCI, true)
            ? array_merge([$current], self::SB_SCI)
            : self::SB_SCI;
    }

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

        /* 누가 만들고 누가 마지막으로 고쳤는가.
           화면마다 따로 적으면 어느 길로 저장했느냐에 따라 비는 곳이 생긴다.
           사람이 아닌 길(웹훅ㆍ배치)로 들어온 것은 비워 둔다 — 없는 사람을 적을 수 없다. */
        static::saving(function (self $p) {
            $id = \Illuminate\Support\Facades\Auth::id();
            if (!$id || !\Illuminate\Support\Facades\Schema::hasColumn('patients', 'updated_by')) {
                return;
            }

            if (!$p->exists && !$p->created_by) {
                $p->created_by = $id;
            }

            // 값이 실제로 바뀔 때만 수정자를 갈아 낀다 — 열어만 봐도 바뀌면 뜻이 없다
            if ($p->exists && $p->isDirty()) {
                $p->updated_by = $id;
            }
        });

        /* 주소가 바뀌면 이력에 한 줄 쌓는다.
           환자 칸에는 늘 가장 최근 것이 남으므로, 그 칸을 읽는 화면(주문 등록ㆍ팩스ㆍ
           서류)은 손대지 않아도 된다. 여기 쌓이는 것은 「언제 어디였는지」다. */
        static::saved(function (self $p) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('patient_addresses')) {
                return;
            }

            if (!$p->address) {
                return;
            }

            $last = $p->addresses()->first();

            if ($last && $last->sameAs($p->postcode, $p->address, $p->address_detail)) {
                return;
            }

            $p->addresses()->create([
                'postcode'       => $p->postcode,
                'address'        => $p->address,
                'address_detail' => $p->address_detail,
                'created_by'     => \Illuminate\Support\Facades\Auth::id(),
            ]);
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
        'va_due_at'              => 'datetime',
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

    /** 주소 이력 — 가장 최근 것이 맨 위다 */
    public function addresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class)->orderByDesc('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contactChannelLabel(): string
    {
        return self::CONTACT_CHANNELS[$this->contact_channel] ?? '';
    }

    public function contactStatusLabel(): string
    {
        return self::CONTACT_STATUSES[$this->contact_status] ?? '';
    }

    /**
     * 생년월일 세 가지 표기 (화면 확정요청 2026-08-27, 2쪽).
     *
     * 위드웍스에서 받아 온 표는 「1982. 11. 11.」이고, 우리 표는 「1982-11-11」이며,
     * 해만 맞춰 보는 자리도 있다. 셋을 나란히 두어야 눈으로 맞춰 볼 수 있다.
     */
    public function getBirthDottedAttribute(): string
    {
        return $this->birth_date?->format('Y. m. d.') ?? '';
    }

    public function getBirthIsoAttribute(): string
    {
        return $this->birth_date?->format('Y-m-d') ?? '';
    }

    public function getBirthYearAttribute(): string
    {
        return $this->birth_date?->format('Y') ?? '';
    }

    /** 개인정보 동의서. 밖에서 들어오는 폼이라 이어지지 않은 것도 있다(patient_id 가 빈다). */
    public function privacyConsents(): HasMany
    {
        return $this->hasMany(PrivacyConsent::class);
    }
}
