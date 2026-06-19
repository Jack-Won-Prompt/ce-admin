<?php
// app/Models/Prescription.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'status', 'reviewed_by', 'reviewed_at', 'review_memo', 'admin_note',
        'postcode', 'address_detail', 'repurchase_date',
        // Counseling (ERP sync + editable fields stored as JSON)
        'counseling_data',
        'kakao_sent_at', 'sms_sent_at',
    ];

    protected $casts = [
        'is_reissue'      => 'boolean',
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

    // ── OCR 주민번호 마스킹 ───────────────────────────────
    public function getMaskedResidentNoOcrAttribute(): ?string
    {
        if (!$this->resident_no_ocr) return null;
        $v = preg_replace('/\s+/', '', $this->resident_no_ocr);
        // XXXXXX-XXXXXXX 형식
        if (preg_match('/^(\d{6})-?(\d)/', $v, $m)) {
            return $m[1] . '-' . $m[2] . '******';
        }
        return $v;
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
        return $this->image_path
            ? asset('storage/' . $this->image_path)
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
