<?php
// app/Models/Prescription.php

namespace App\Models;

use App\Support\ResidentNo;
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
        // Counseling (ERP sync + editable fields stored as JSON)
        'counseling_data',
        'kakao_sent_at', 'sms_sent_at',
    ];

    protected $casts = [
        'is_reissue'      => 'boolean',
        'is_blank_draft'  => 'boolean',
        'ocr_raw_data'    => 'array',
        'counseling_data' => 'array',
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

    // ── 상담 데이터 accessor — blade에서 $prescription->counseling?->xxx 로 접근 ──
    public function getCounselingAttribute(): ?object
    {
        $data = $this->counseling_data;
        return $data ? (object) $data : null;
    }

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
    public const STATUS_LABELS = [
        'pending'        => ['label' => '대기 중',    'badge' => 'secondary'],
        'ocr_processing' => ['label' => 'OCR 처리중', 'badge' => 'warning'],
        'ocr_done'       => ['label' => 'OCR 완료',   'badge' => 'info'],
        'review_needed'  => ['label' => '검수 필요',  'badge' => 'danger'],
        'approved'       => ['label' => '검수 완료',  'badge' => 'success'],
        'rejected'       => ['label' => '반려',        'badge' => 'danger'],
        'ordered'        => ['label' => '주문 완료',   'badge' => 'success'],
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
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
        static::updating(function (self $rx) {
            if (!$rx->is_blank_draft) {
                return;
            }
            $changed = array_diff(array_keys($rx->getDirty()), ['is_blank_draft', 'updated_at']);
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
    public static function generateRxNumber(): string
    {
        $date = now()->format('Ymd');
        $seq  = static::whereDate('created_at', today())->count() + 1;
        return sprintf('RX-%s-%03d', $date, $seq);
    }

    // ── 상담번호 자동 채번 ────────────────────────────────
    public static function generateCounselNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "CS-{$date}-";

        $last = static::whereNotNull('counseling_data')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(counseling_data, '$.counselling_no')) LIKE ?", ["{$prefix}%"])
            ->selectRaw("MAX(CAST(SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(counseling_data, '$.counselling_no')), ?) AS UNSIGNED)) AS max_seq",
                [strlen($prefix) + 1])
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
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'ocr_processing', 'ocr_done', 'review_needed']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
