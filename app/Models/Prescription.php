<?php
// app/Models/Prescription.php

namespace App\Models;

use App\Support\ResidentNo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use LogsActivity;

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
        // 문서마다의 밝기ㆍ명암 — 파일은 그대로 두고 숫자만 적어 둔다(2026-09-09)
        'img_brightness', 'img_contrast',
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
        'status', 'is_blank_draft', 'reviewed_by', 'reviewed_at', 'review_memo', 'review_request_memo', 'admin_note',
        /* 입력 검수 — 파일 검수(위 status·reviewed_*)와 다른 일이다 (2026-09-16 지시).
           파일 검수는 올라온 이미지를 보고, 입력 검수는 적어 넣은 값을 본다. */
        'input_review_status', 'input_review_requested_at', 'input_review_requested_by',
        'input_review_request_memo', 'input_review_approved_at', 'input_review_approved_by',
        'input_review_memo',
        // 참고 사항 — 이 건을 두고 오래 남겨 둘 말. 검수 메모와 다른 칸이다(2026-09-10)
        'reference_note',
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
        // 이 건의 급여 기간 — 건보위임동의 기간과 다른 값이다(2026-09-09)
        'use_start_date', 'benefit_end_date',
        'inmarket_due', 'last_confirmed_qty', 'diverticulums',
        'kakao_sent_at', 'sms_sent_at',
    ];

    protected $casts = [
        'is_reissue'      => 'boolean',
        'is_blank_draft'  => 'boolean',
        'ocr_raw_data'    => 'array',
        'issued_date'      => 'date',
        // 상담일시는 시간까지 담는다 (2026-09-11 확인요청 4쪽)
        'counsel_date'     => 'datetime',
        'kakao_sent_at'   => 'datetime',
        'sms_sent_at'     => 'datetime',
        'repurchase_date'  => 'date',
        'reviewed_at'  => 'datetime',
        'input_review_requested_at' => 'datetime',
        'input_review_approved_at'  => 'datetime',
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
        /* 가려진 값이 되돌아오면 적어 둔 것을 그대로 둔다 (2026-09-17, 거래처와 같다).
           화면이 「120315-3******」로 보여 주므로, 손대지 않고 저장하면 그 글이
           그대로 온다 — 번호로 알고 다시 암호화하면 적어 둔 번호가 별표로 덮인다. */
        if ($value !== null && str_contains($value, '*')) {
            return;
        }

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
        /* 검수자가 「파일 다시 올리기」로 되물은 건 (2026-09-15 지시).

           여태 되돌릴 자리가 review_needed 하나뿐이라, 「아직 아무도 안 본 건」과
           「보고 나서 되물은 건」이 목록에서 같은 말로 섰다. 검수자에게 앞것은
           지금 볼 차례이고 뒷것은 답을 기다리는 것이라 성격이 다르다. */
        'review_hold'      => ['label' => '검수 보류',  'badge' => 'warning'],
        /* 되물은 자료를 다시 올리고 검수를 청한 건 (2026-09-15 지시).
           처음 올라온 건의 검수 요청과 섞이면 어느 것이 되돌아온 것인지 모른다. */
        'review_resent'    => ['label' => '검수 재요청', 'badge' => 'warning'],
        'approved'         => ['label' => '검수 완료',  'badge' => 'success'],
        'rejected'         => ['label' => '반려',        'badge' => 'danger'],
        'ordered'          => ['label' => '주문 완료',   'badge' => 'success'],
    ];

    /**
     * 올린 사람이 아직 자료를 고칠 수 있는 상태.
     *
     * 검수 완료(approved)와 그 뒤(ordered)는 막는다 — 담당자가 이미 보고 확정한
     * 자료가 밑에서 사라지면, 무엇을 보고 승인했는지 알 수 없게 된다.
     * 반려(rejected)는 열어 둔다. 다시 올려 달라는 뜻이므로 고칠 수 있어야 한다.
     */
    public const UPLOADER_EDITABLE_STATUSES = [
        'pending',
        'ocr_processing',
        'ocr_done',
        'review_needed',
        'review_requested',
        // 되물은 건과 되돌아온 건 — 자료를 갈아 끼우라고 부른 자리다 (2026-09-15)
        'review_hold',
        'review_resent',
        'rejected',
    ];

    /** 이 사람이 이 건의 자료를 지우고 다시 올릴 수 있는가. */
    public function editableByUploader(?int $userId): bool
    {
        return $userId !== null
            && $this->created_by === $userId
            && in_array($this->status, self::UPLOADER_EDITABLE_STATUSES, true);
    }

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

    /**
     * 상담 유형 — 고를 수 있는 갈래 (2026-09-11 확인요청 4쪽으로 셋 보탬).
     *
     * 여태 상담 창ㆍ상담내역 목록ㆍ컨트롤러 세 곳에 따로 적혀 있었고, 한쪽만 늘어
     * 서로 달랐다(교환ㆍ환불은 창에만, 개인구매는 컨트롤러에만 있었다). 한 자리에 둔다.
     */
    public const 상담유형 = [
        '1013' => '구매',
        '1020' => '반품',
        '1021' => '교환',
        '1022' => '환불',
        '1030' => '문의',
        '1031' => '컴플레인',
        '1032' => '샘플',
        '1033' => '서류 문의',
        '1050' => '기타',
    ];

    /** 이제는 고르지 않지만 이미 담긴 건이 있는 갈래 — 보여 줄 때만 쓴다 */
    public const 상담유형옛 = ['1016' => '개인구매'];

    /** 코드를 사람이 읽는 말로 */
    public static function 상담유형말(?string $코드): string
    {
        return (self::상담유형 + self::상담유형옛)[(string) $코드] ?? '';
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ── 입력 검수 ─────────────────────────────────────────────
       파일 검수(status·reviewed_*)와 다른 일이다 (2026-09-16 지시).
       파일 검수는 올라온 이미지를 보고, 입력 검수는 적어 넣은 값을 본다.
       한 칸을 함께 쓰던 때에는 파일만 승인해도 입력이 승인된 것으로 보였다. */

    public const INPUT_REVIEW_REQUESTED = 'requested';
    public const INPUT_REVIEW_APPROVED  = 'approved';

    public const INPUT_REVIEW_LABELS = [
        self::INPUT_REVIEW_REQUESTED => '입력 검수 요청',
        self::INPUT_REVIEW_APPROVED  => '입력 검수 승인',
    ];

    public function inputReviewRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_review_requested_by');
    }

    public function inputReviewApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_review_approved_by');
    }

    public function 입력검수요청했나(): bool
    {
        return $this->input_review_status === self::INPUT_REVIEW_REQUESTED;
    }

    public function 입력검수승인했나(): bool
    {
        return $this->input_review_status === self::INPUT_REVIEW_APPROVED;
    }

    /** 화면이 그대로 쓰는 한 덩이 — 언제ㆍ누가를 함께 넘긴다 */
    public function 입력검수상태(): array
    {
        $this->loadMissing('inputReviewRequester', 'inputReviewApprover');

        return [
            'status'        => $this->input_review_status,
            'label'         => self::INPUT_REVIEW_LABELS[$this->input_review_status] ?? '입력 검수 전',
            'requested_at'  => $this->input_review_requested_at?->format('Y-m-d H:i'),
            'requested_by'  => $this->inputReviewRequester?->name,
            'request_memo'  => $this->input_review_request_memo,
            'approved_at'   => $this->input_review_approved_at?->format('Y-m-d H:i'),
            'approved_by'   => $this->inputReviewApprover?->name,
            'memo'          => $this->input_review_memo,
        ];
    }

    /**
     * 자료 다시 올리기 요청 (2026-09-12 지시).
     *
     * 여기에 차례를 매기지 않는다. 목록이 withCount 로 세는 자리라, 관계에 붙은
     * order by 가 세는 질의까지 따라간다. 차례는 꺼내 쓰는 쪽에서 매긴다.
     */
    public function reuploadRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrescriptionReuploadRequest::class);
    }

    /** 마지막으로 고친 사람. 기록이 붙기 전 처방전은 비어 있다. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 이 건의 **원 주문**.
     *
     * 한 처방전에 주문이 둘 이상 설 수 있게 되면서(추가 주문, 2026-09-14 확인요청 4쪽)
     * 「그 처방전의 주문」이 하나로 정해지지 않게 됐다. 가장 먼저 선 것이 원 주문이다.
     *
     * **이 관계를 쓰는 백열네 자리는 모두 원 주문을 보는 것이 맞다** — 청구ㆍ정산ㆍ
     * 세금계산서ㆍ교환반품취소는 처방전 한 장을 하나로 다룬다. 모두를 봐야 하는 자리만
     * orders() 를 쓴다.
     *
     * oldestOfMany() 가 아니라 정렬을 얹은 hasOne 이다 — 저쪽은 부질의로 만들어져
     * order()->create() 같은 길이 막힌다.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class)->oldest('id');
    }

    /** 이 건에 달린 주문 모두 — 원 주문과 추가 주문 (2026-09-14 확인요청 4쪽) */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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

            /* 처방전 본 그림을 갈아 끼웠으면 그것을 물었던 요청을 닫는다
               (2026-09-12 지시). 본 그림은 첨부가 아니어서 첨부 쪽 자리가
               잡아 주지 못한다. */
            if ($p->wasChanged('image_path') && $p->image_path) {
                app(\App\Services\ReuploadRequestService::class)->닫기($p, '처방전');
            }
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

            /* 사람이 적은 것만 「내용이 생겼다」로 본다 (2026-09-11 고침).

               거래처의 ［상담하기］로 들어오면 `?patient=` 가 실려 와 초안에 그 환자가
               붙는다. 사람이 적은 것이 아니라 화면이 대신 붙인 것인데, 여태 이것만으로도
               초안 표시가 풀렸다. 그러면 다음에 같은 길로 들어올 때 다시 쓸 빈 초안이
               없어 새 처방번호를 받는다 — 들어올 때마다 빈 껍데기가 하나씩 쌓였다.
               한 환자에 여섯 사람이 들어오니 3분 만에 일곱 건이 섰고, 그중 자료가
               올라간 것은 하나뿐이었다.

               환자를 붙이는 것은 초안을 준비하는 일이지 채우는 일이 아니다. 이름ㆍ병원ㆍ
               처방 내용처럼 사람이 친 값이 들어올 때 풀린다. */
            $셈안함 = ['is_blank_draft', 'updated_at', 'updated_by', 'patient_id'];
            $changed = array_diff(array_keys($rx->getDirty()), $셈안함);
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

    /**
     * 상담만 적어 둔 건 — 처방전 목록에는 세우지 않는다 (2026-09-14 지시).
     *
     * 상담은 처방전 줄에 붙어 산다(counsel_* 칸). 통화만 하고 끝나는 상담도 한 줄을
     * 차지하므로, 한 사람에게 상담을 두 번 적으면 처방전 목록에 두 줄이 선다 —
     * 담당자에게는 「한 번 올렸는데 처방전이 둘로 갈라진 것」으로 보인다.
     *
     * 그 줄들은 아직 처방전이 아니다. 그림도 서류도 없고, 병원ㆍ발행일도 비어 있다.
     * 나중에 그 사람의 자료를 올리면 업로드가 이 줄을 찾아 이어 쓰고, 그때 비로소
     * 처방전이 된다(store 의 이어쓸초안). 그러니 목록에서는 감추고 상담 이력에서만
     * 보이면 된다 — 지우지 않는다. 상담 기록은 그 자체로 남아야 할 자취다.
     *
     * 잣대를 좁게 잡는다. 하나라도 붙은 것이 있으면 처방전으로 본다 — 넓게 잡으면
     * 손대는 중인 건이 목록에서 조용히 사라진다.
     */
    /**
     * 이 줄이 아직 상담뿐인가 — 주문 줄을 세울지 가릴 때 쓴다 (2026-09-14 지시).
     *
     * scopeCounselOnly 와 잣대가 같되 주문 조건만 뺀다. 이것을 부르는 자리
     * (OrderSync::seed)는 주문이 아직 없을 때만 오기 때문이다.
     */
    public function 상담만인가(): bool
    {
        return (bool) $this->counsel_no
            && $this->status === 'pending'
            && ! $this->image_path
            && ! $this->hospital_name
            && ! $this->issued_date
            && ! $this->attachments()->exists()
            && ! $this->consents()->exists()
            && ! $this->documents()->exists();
    }

    public function scopeCounselOnly($query)
    {
        return $query->whereNotNull('counsel_no')
            ->where('status', 'pending')
            ->whereNull('image_path')
            ->whereNull('hospital_name')
            ->whereNull('issued_date')
            ->whereDoesntHave('attachments')
            ->whereDoesntHave('consents')
            ->whereDoesntHave('documents')
            /* 주문 줄은 상담 건에도 저절로 선다(OrderSync::seed) — 있다고 해서 손댄
               건은 아니다. 제품이 담겼거나 창고로 넘어간 것만 처방전으로 본다. */
            ->where(fn ($q) => $q
                ->whereDoesntHave('order')
                ->orWhereHas('order', fn ($o) => $o
                    ->where('status', 'pending')
                    ->where(fn ($w) => $w->whereNull('withworks_so_no')->orWhere('withworks_so_no', ''))
                    ->where(fn ($w) => $w->whereNull('product_name')->orWhereIn('product_name', ['', '-']))));
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

    /* ── 저장 이력 ──────────────────────────────────────────
       무엇이 무엇에서 무엇으로 바뀌었는지 남긴다(2026-09-09 지시).

       여태 activity()->log('OCR 필드 수정') 처럼 「고쳤다」는 사실만 남고 무엇이
       바뀌었는지는 남지 않았다. 나중에 값이 이상하면 누가 언제 그렇게 만들었는지
       알 길이 없었다.

       **바뀐 칸만** 남긴다(logOnlyDirty). 저장할 때마다 서른 칸이 통째로 쌓이면
       정작 바뀐 하나를 못 찾는다. 주민등록번호처럼 감춰 둔 값은 아예 남기지 않는다
       — 암호로 가려 둔 것이 이력 표에 평문으로 쌓이면 안 된다(App\Support\ChangeLog). */
    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            /* **감출 칸은 properties 에서 아예 뺀다.** dontLogIfAttributesChangedOnly 는
               그 칸들만 바뀌었을 때 로그를 안 남길 뿐, 값은 그대로 실린다 — 그것으로는
               주민등록번호 원문이 이력에 쌓이는 것을 못 막는다. */
            ->logExcept(\App\Support\ChangeLog::감출칸)
            ->dontSubmitEmptyLogs()
            ->useLogName('변경');
    }
}
