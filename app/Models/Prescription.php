<?php
// app/Models/Prescription.php

namespace App\Models;

use App\Support\ResidentNo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use SoftDeletes;

    /**
     * 처방 유형 — 상담에서 고른 값(counsel_acc_add_type).
     *
     * 원내·원외·처방외는 정산 방식과 필요한 서류가 달라 나눠 봐야 하는 값이다.
     * 코드는 상담 시스템이 정한 것이라 우리가 고를 수 없다.
     */
    public const ACC_TYPES = [
        '30' => '처방전-원내',
        '10' => '처방전-원외',
        '20' => '처방외',
    ];

    public function accTypeLabel(): string
    {
        return self::ACC_TYPES[(string) $this->counsel_acc_add_type] ?? '-';
    }

    protected $fillable = [
        'rx_number', 'patient_id', 'assigned_user_id', 'created_by',
        'image_path', 'image_original_name', 'image_mime_type',
        'image_size', 'upload_source',
        // OCR fields
        'registration_no', 'serial_no', 'is_reissue',
        'patient_name_ocr', 'resident_no_ocr', 'mobile_ocr', 'address_ocr',
        'resident_no_ocr_enc', 'resident_no_ocr_masked',
        'hospital_name', 'hospital_code', 'doctor_name',
        'specialty', 'license_no', 'specialist_no',
        'department', 'disease_name', 'disease_code',
        'daily_count', 'total_days', 'total_count',
        'usage_period', 'issued_date',
        'ocr_raw_data', 'ocr_confidence',
        // Product
        'product_name', 'product_code', 'quantity',
        'nhis_status', 'product_price', 'insurance_price', 'nhis_amount', 'patient_copay',
        // Review
        'status', 'is_blank_draft', 'reviewed_by', 'reviewed_at', 'review_memo', 'admin_note',
        'postcode', 'address_detail', 'repurchase_date',
        // 상담·처방 부가 항목 — 예전에는 counseling_data JSON 이었다. 모두 컬럼으로 옮겼다.
        'counsel_no', 'counsel_date', 'counsel_type', 'counsel_acc_add_type',
        'counsel_status', 'counsel_call_no', 'counsel_re_date', 'counsel_contents',
        'counsel_order_id',
        'dealer_type', 'caregiver_name',
        'benefit_class', 'billing_strategy', 'claim_agency', 'billing_office_id', 'local_gov', 'disease_class', 'uro_date', 'diagnosis_date',
        // 화면 확정요청 2026-08-27 — 상병 구분(1ㆍ2-1ㆍ2-2ㆍ3)과 요류역학검사 확인사항
        'disease_grade', 'uro_findings',
        'rx_use_period', 'rx_end_date', 'purchase_type',
        'five_program', 'five_110days', 'daily_use_qty', 'order_manager',
        'special_case', 'reason', 'pay_date', 'buy_date', 'next_repurchase',
        'inmarket_due', 'last_confirmed_qty', 'diverticulums',
        'kakao_sent_at', 'sms_sent_at',
    ];

    protected $casts = [
        'is_reissue'      => 'boolean',
        'is_blank_draft'  => 'boolean',
        'ocr_raw_data'    => 'array',
        'issued_date'      => 'date',
        'kakao_sent_at'   => 'datetime',
        'sms_sent_at'     => 'datetime',
        'repurchase_date'  => 'date',
        'reviewed_at'  => 'datetime',
        'ocr_confidence' => 'float',
        'product_price'   => 'float',
        'insurance_price' => 'float',
        'nhis_amount'     => 'float',
        'patient_copay'   => 'float',
    ];

    /**
     * OCR 주민번호는 오인식 여부를 담당자가 판단해야 하므로 원문을 그대로 암호화한다
     * (정규화하면 '잘못 읽힌 형태' 자체가 사라져 검수가 불가능해진다).
     * 마스킹만 정규화해서 만든다.
     */
    public function setResidentNoOcrAttribute(?string $value): void
    {
        // 평문 컬럼은 제거 마이그레이션 이후 존재하지 않는다. 없는데 쓰면 INSERT 가 죽는다.
        if (self::hasPlainResidentNoOcrColumn()) {
            $this->attributes['resident_no_ocr'] = $value;
        }

        $this->attributes['resident_no_ocr_enc']    = ResidentNo::encrypt($value);
        $this->attributes['resident_no_ocr_masked'] = ResidentNo::mask($value);
    }

    // ── OCR 주민번호 마스킹 ───────────────────────────────
    public function getMaskedResidentNoOcrAttribute(): ?string
    {
        return $this->resident_no_ocr_masked
            ?? ResidentNo::mask($this->attributes['resident_no_ocr'] ?? null);
    }

    /** 검수 화면에서 담당자가 원문을 확인할 때만. 호출 즉시 감사로그가 남는다. */
    public function residentNoOcrFor(string $reason): ?string
    {
        if ($this->resident_no_ocr_enc) {
            return ResidentNo::decrypt($this->resident_no_ocr_enc, $reason, [
                'type' => self::class, 'id' => $this->id, 'menu' => '처방전 검수',
            ]);
        }

        return $this->attributes['resident_no_ocr'] ?? null;
    }

    // ── 상태 라벨 매핑 ───────────────────────────────────
    /*
     * 흐름은 셋이다 — 올리면 검수 필요, 담당자가 다 적으면 검수 요청, 검수자가 보면 검수 완료.
     *   업로드 → review_needed → (담당자) review_requested → (검수자) approved → ordered
     * OCR 은 쓰지 않는다. pending·ocr_processing·ocr_done 은 새로 만들지 않지만 라벨은
     * 남긴다 — 예전에 그 상태로 저장된 처방전이 있어, 지우면 목록에서 상태가 빈칸이 된다.
     */
    public const STATUS_LABELS = [
        'pending'          => ['label' => '대기 중',    'badge' => 'secondary'],
        'ocr_processing'   => ['label' => 'OCR 처리중', 'badge' => 'warning'],
        'ocr_done'         => ['label' => 'OCR 완료',   'badge' => 'info'],
        'review_needed'    => ['label' => '검수 필요',  'badge' => 'danger'],
        'review_requested' => ['label' => '검수 요청',  'badge' => 'warning'],
        'approved'         => ['label' => '검수 완료',  'badge' => 'success'],
        'rejected'         => ['label' => '반려',        'badge' => 'danger'],
        'ordered'          => ['label' => '주문 완료',   'badge' => 'success'],
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['badge'] ?? 'secondary';
    }

    // ── 이미지 URL ────────────────────────────────────────
    public function getImageUrlAttribute(): ?string
    {
        // 예전에는 storage 로 바로 열려 주소만 알면 로그인 없이 보였다.
        // 로그인·권한을 확인하는 경로로 내보낸다(SecureFileController).
        return $this->image_path && $this->exists
            ? route('files.prescription-image', $this)
            : null;
    }

    // ── 라우트 키 ─────────────────────────────────────────
    public function getRouteKeyName(): string
    {
        return 'rx_number';
    }

    // ── 관계 ─────────────────────────────────────────────
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** 이 상담이 어느 주문 이야기였나 — 처방으로 산 것일 수도, 처방 없이 산 것일 수도 있다 */
    public function counselOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'counsel_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** 마지막으로 고친 사람. 기록이 붙기 전 처방전은 비어 있다. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /** 이 건을 보내는 청구처 — 공단 지사 또는 지자체 부서의 담당자 한 줄. */
    public function billingOffice()
    {
        return $this->belongsTo(\App\Models\BillingOffice::class, 'billing_office_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order');
    }

    public function memos(): HasMany
    {
        return $this->hasMany(PrescriptionMemo::class)->latest();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PrescriptionConsent::class)->latest();
    }

    public function faxHistories(): HasMany
    {
        return $this->hasMany(\App\Models\FaxHistory::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PrescriptionAttachment::class)->orderBy('display_order');
    }

    /** 생성 서류 (위임동의서·요양비위임장 등 시스템 생성 PDF) */
    public function documents(): HasMany
    {
        return $this->hasMany(PrescriptionDocument::class)->latest();
    }

    /**
     * 처음 저장되는 순간 빈 초안 표식을 스스로 뗀다.
     *
     * 이 표식 하나로 '메뉴를 열어만 둔 껍데기' 와 '입력이 들어간 처방전' 을 가른다.
     * 저장 경로가 여러 곳(검수·제품·주문·메모…)이라 각 컨트롤러에서 지우게 하면
     * 반드시 빠뜨리는 곳이 생기므로 모델에서 일괄 처리한다.
     */
    protected static function booted(): void
    {
        /* 빈 초안이 아닌 처방전은 주문 관리에도 선다.
           어느 길로 만들어지든(업로드ㆍ상담하기ㆍ주문 등록의 저장ㆍ위임동의 서명) 빠지지
           않게 여기 한 곳에서 세운다 — 길마다 한 줄씩 붙여 두었더니 나중에 난 길(상담하기)이
           빠져, 이름과 상담이 적힌 건이 어느 목록에도 없이 떠 있었다.

           이미 줄이 있으면 손대지 않는다(OrderSync::seed). 값을 다시 맞추는 것은 그 일을
           하려고 부른 자리의 몫이다. */
        static::saved(function (self $p) {
            \App\Support\OrderSync::seed($p);
        });

        static::updating(function (self $rx) {
            /* 마지막으로 고친 사람을 남긴다.
               로그인한 사람이 실제 값을 바꿀 때만 기록한다 — 배치·웹훅처럼
               사람이 없는 변경과, 값이 그대로인 저장은 기록하지 않는다.
               updated_by 자체만 바뀐 경우도 제외해야 무한히 되짚지 않는다. */
            $touched = array_diff(array_keys($rx->getDirty()), ['updated_at', 'updated_by', 'is_blank_draft']);
            if ($touched && Auth::check() && Schema::hasColumn('prescriptions', 'updated_by')) {
                $rx->updated_by = Auth::id();
            }

            if (!$rx->is_blank_draft) {
                return;
            }
            $changed = array_diff(array_keys($rx->getDirty()), ['is_blank_draft', 'updated_at', 'updated_by']);
            if ($changed) {
                $rx->is_blank_draft = false;
            }
        });
    }

    /**
     * 아직 아무것도 입력하지 않은 '신규 등록' 초안.
     *
     * 메뉴 '처방전 관리' 와 검수 화면의 '신규 등록' 이 만드는 껍데기 레코드다.
     * 목록에서는 감추고, 다시 눌렀을 때는 새로 만들지 않고 이것을 재사용한다.
     */
    public function scopeBlankDraft($query)
    {
        // 초안에 딸린 것이 하나라도 있으면 더는 '빈' 초안이 아니다.
        // is_blank_draft 는 처방전 자체가 저장될 때만 풀리는데, 위임동의·서류·첨부는
        // 처방전을 건드리지 않고 따로 생긴다. 그것들을 안 보면 동의가 붙은 초안을
        // 재사용해서 신규 등록 화면에 이전 동의 상태가 그대로 딸려온다.
        return $query->where('is_blank_draft', true)
                     ->whereDoesntHave('order')
                     ->whereDoesntHave('consents')
                     ->whereDoesntHave('documents')
                     ->whereDoesntHave('attachments')
                     ->whereDoesntHave('memos');
    }

    /** 평문 컬럼이 아직 남아 있는지 (요청당 1회만 확인) */
    public static function hasPlainResidentNoOcrColumn(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasColumn('prescriptions', 'resident_no_ocr');
    }

    /** 특정 사용자가 만든 빈 초안 */
    public function scopeBlankDraftsOf($query, ?int $userId)
    {
        return $query->blankDraft()->where('created_by', $userId);
    }

    // ── 처방번호 자동 생성 ────────────────────────────────
    /**
     * 오늘 날짜 + 일련번호.
     *
     * '오늘 만들어진 행의 수 + 1' 로 세면 안 된다. 한 건을 지우면 수가 줄어 다음 건이 이미
     * 나간 번호를 다시 받는다. 번호는 서류·팩스·공단 제출에 찍혀 나가므로 다시 내주면 안 된다.
     * 이미 쓰인 번호 중 가장 큰 것에서 하나를 올린다 — 지운 건도 함께 센다.
     */
    public static function generateRxNumber(): string
    {
        $prefix = 'RX-' . now()->format('Ymd') . '-';

        $max = static::withTrashed()
            ->where('rx_number', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(rx_number, ?) AS UNSIGNED)) AS max_seq', [strlen($prefix) + 1])
            ->value('max_seq');

        return sprintf('%s%03d', $prefix, ((int) $max) + 1);
    }

    // ── 상담번호 자동 채번 ────────────────────────────────
    public static function generateCounselNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "CS-{$date}-";

        $last = static::withTrashed()
            ->whereNotNull('counsel_no')
            ->where('counsel_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(counsel_no, ?) AS UNSIGNED)) AS max_seq', [strlen($prefix) + 1])
            ->value('max_seq');

        $seq = str_pad(($last ?? 0) + 1, 3, '0', STR_PAD_LEFT);
        return "{$prefix}{$seq}";
    }

    // ── OCR 신뢰도 상태 ───────────────────────────────────
    public function getOcrStatusAttribute(): string
    {
        if (is_null($this->ocr_confidence)) return 'pending';
        if ($this->ocr_confidence >= 90)   return 'high';
        if ($this->ocr_confidence >= 70)   return 'medium';
        return 'low';
    }

    // ── 표시용 신뢰도 (95% 미만으로 캡, OCR 실제값 비례 반영) ────
    // 95% 미만이면 실제값 그대로, 95% 이상이면 94 + (실제값 - 95) × 0.1 로 압축
    public function getDisplayConfidenceAttribute(): ?float
    {
        if (is_null($this->ocr_confidence)) return null;
        $v = (float) $this->ocr_confidence;
        if ($v < 95.0) {
            return round($v, 1);
        }
        // 95~100 → 94.0~94.5 범위로 압축 (차이 유지)
        return round(94.0 + ($v - 95.0) * 0.1, 1);
    }

    // ── Scopes ───────────────────────────────────────────
    /** 아직 검수가 끝나지 않은 것. 앞의 셋은 OCR 시절에 저장된 옛 상태다. */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'ocr_processing', 'ocr_done', 'review_needed', 'review_requested']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
