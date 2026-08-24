<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;

    /** 판매 유형 코드 → 레이블/배지 */
    /**
     * 위드웍스 판매유형 코드.
     *
     * 위드웍스 code_list 에 있는 값을 그대로 쓴다. 우리가 만드는 값이 아니라 그쪽이
     * 정하는 것이라, 새 유형이 생기면 여기와 검증 목록을 함께 늘려야 한다.
     */
    public const SO_TYPE_LABELS = [
        '1013' => ['CE 판매',                  'primary'],
        '1016' => ['개인판매',                 'info'],
        '1022' => ['샘플판매',                 'warning'],
        // 2026-08-18 코드 개편 — 5000·6000번대가 지워지고 1500·1600번대가 그 자리를 받았다
        '1501' => ['End User Direct',          'success'],
        '1505' => ['End User Direct 반품',     'danger'],
        '1601' => ['CE 샘플주문',              'warning'],
        '1605' => ['CE 샘플 반품',             'danger'],
        // 개편 전 코드 — 그 값으로 저장된 주문이 아직 진행 중이라 이름은 남긴다
        '5001' => ['End User Direct',          'success'],
        '5004' => ['End User Direct 취소',     'danger'],
        '5005' => ['End User Direct 반품',     'danger'],
        '5006' => ['End User Direct 교환',     'warning'],
        '6001' => ['CE 샘플주문',              'warning'],
    ];

    /**
     * 지금 고를 수 있는 판매 유형 — 설정 화면에 적어 둔 값 하나다.
     *
     * 코드를 여기 박아 두었더니 위드웍스가 개편할 때마다 배포를 해야 했고, 그 사이에는
     * 지워진 코드로 주문이 나갔다. 이제 설정에서 읽는다 — 창고에서 코드가 바뀌면
     * 화면에서 고쳐 넣으면 그만이다.
     *
     * 되돌리는 유형(반품 등)은 여기 없다. 판매를 만들면서 고를 수 있게 두면 반품
     * 유형으로 판매가 나간다.
     */
    public static function saleSoTypes(): array
    {
        $code = trim((string) \App\Models\WithworksSetting::current()->so_type);

        return [$code !== '' ? $code : \App\Models\WithworksSetting::SO_TYPE_EUD];
    }

    /**
     * 되돌리는 주문의 유형 — 종류마다 따로 둔다. 적어 둔 것만 쓴다.
     *
     * 창고가 하는 일이 셋 다 다르다. 코드는 위드웍스 code_list 가 정하므로 설정 화면에서
     * 적어 넣는다 — 아직 코드가 없는 종류는 비어 있고, 그 종류는 창고로 넘기지 않는다.
     */
    public static function returnSoTypes(): array
    {
        $s = \App\Models\WithworksSetting::current();

        return array_values(array_filter([
            trim((string) $s->cancel_so_type),
            trim((string) $s->return_so_type),
            trim((string) $s->exchange_so_type),
        ], fn ($c) => $c !== ''));
    }

    /** 저장돼 있을 수 있는 값 — 옛 주문을 수정할 때 막히지 않게 넓게 둔다 */
    public const SO_TYPES = ['1013', '1016', '1022', '1501', '1505', '1601', '1605',
                             '5001', '5004', '5005', '5006', '6001'];

    /**
     * 입금이 확인되었는가 — 토스가 확인했거나, 담당자가 통장을 보고 확인했거나.
     *
     * 화면은 이 둘을 가르지 않는다. 「돈이 들어왔는가」 하나만 묻기 때문이다.
     * 다만 누가 확인했는지는 기록에 남는다(deposit_confirmed_by).
     */
    public function isDepositConfirmed(): bool
    {
        return $this->deposit_confirmed_at !== null || (bool) $this->tossPayment?->is_done;
    }

    /** 담당자가 손으로 확인한 건인가 — 토스가 아니라 사람이 본 것 */
    public function isDepositConfirmedByHand(): bool
    {
        return $this->deposit_confirmed_at !== null;
    }

    /** 들어와야 하는 금액 — 본인부담금 + 배송비 */
    public function expectedDeposit(): int
    {
        return (int) ($this->patient_copay ?? 0) + (int) ($this->shipping_fee ?? 0);
    }

    protected $fillable = [
        'order_number', 'prescription_id', 'patient_id', 'created_by',
        'product_name', 'product_code', 'quantity',
        'unit_price', 'nhis_amount', 'patient_copay',
        // 담당자가 눈으로 확인한 입금 — 토스가 알려 주지 못하는 건을 위한 자리
        'deposit_confirmed_at', 'deposit_confirmed_by', 'deposit_amount', 'deposit_note',
        'shipping_fee', 'total_amount',
        'status', 'so_type', 'shipping_address', 'tracking_number',
        'estimated_delivery', 'delivered_at',
        'nhis_claim_status', 'nhis_submitted_at', 'nhis_approved_at',
        'nhis_reimbursement', 'latest_fax_log_id', 'nhis_rejection_reason',
        // 세금계산서
        'tax_invoice_status', 'tax_invoice_no', 'tax_invoice_type',
        'tax_invoice_biz_name', 'tax_invoice_ceo_name', 'tax_invoice_biz_no', 'tax_invoice_email',
        'tax_invoice_supply', 'tax_invoice_vat',
        'tax_invoice_issued_at', 'tax_invoice_cancelled_at',
        // 현금영수증
        'cash_receipt_status', 'cash_receipt_no', 'cash_receipt_type',
        'cash_receipt_identifier', 'cash_receipt_amount',
        'cash_receipt_issued_at', 'cash_receipt_cancelled_at',
        'note',
        // Withworks 연동
        'so_type', 'withworks_so_no', 'withworks_so_id',
        'withworks_status', 'withworks_status_label', 'withworks_status_at',
        'withworks_ship_no', 'withworks_ship_status', 'withworks_ship_status_label',
        'withworks_tracking_no', 'withworks_ship_at',
        // 배송
        'shipping_recipient',
    ];

    protected $casts = [
        'deposit_confirmed_at' => 'datetime',
        'estimated_delivery'        => 'date',
        'delivered_at'              => 'datetime',
        'nhis_submitted_at'         => 'datetime',
        'nhis_approved_at'          => 'datetime',
        'tax_invoice_issued_at'     => 'datetime',
        'tax_invoice_cancelled_at'  => 'datetime',
        'cash_receipt_issued_at'    => 'datetime',
        'cash_receipt_cancelled_at' => 'datetime',
        'withworks_status_at'       => 'datetime',
        'withworks_ship_at'         => 'datetime',
        'unit_price'          => 'float',
        'nhis_amount'         => 'float',
        'patient_copay'       => 'float',
        'shipping_fee'        => 'float',
        'total_amount'        => 'float',
        'nhis_reimbursement'  => 'float',
        'tax_invoice_supply'  => 'float',
        'tax_invoice_vat'     => 'float',
        'cash_receipt_amount' => 'float',
    ];

    public const TAX_INVOICE_STATUS_LABELS = [
        'not_issued' => ['미발행', 'secondary'],
        'issued'     => ['발행완료', 'success'],
        'cancelled'  => ['취소됨',  'danger'],
    ];

    public const CASH_RECEIPT_STATUS_LABELS = [
        'not_issued' => ['미발행', 'secondary'],
        'issued'     => ['발행완료', 'success'],
        'cancelled'  => ['취소됨',  'danger'],
    ];

    public const CASH_RECEIPT_TYPE_LABELS = [
        'income_deduction' => '소득공제',
        'business_expense' => '지출증빙',
    ];

    public const STATUS_LABELS = [
        'pending'   => ['label' => '주문 대기',  'badge' => 'secondary'],
        'confirmed' => ['label' => '주문 확정',  'badge' => 'primary'],
        'shipping'  => ['label' => '배송 중',    'badge' => 'info'],
        'delivered' => ['label' => '배송 완료',  'badge' => 'success'],
        'cancelled' => ['label' => '취소',        'badge' => 'danger'],
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? $this->status;
    }

    public static function generateOrderNumber(): string
    {
        /* 번호 부분만 숫자로 보고 최대를 찾는다.
           예전에는 order_number 의 문자열 최대값을 잡아 뒤 숫자를 뽑았는데, ORD- 뒤에 숫자가
           아닌 것이 하나라도 섞이면(ORD-T0815… 같은 임시 번호) 그것이 문자열 최대가 되어
           숫자 추출이 실패하고 번호가 1 로 되돌아간다. 그러면 이미 있는 번호와 부딪혀 주문이
           아예 만들어지지 않는다. 자릿수가 넷을 넘어갈 때도 문자열 비교는 틀린 답을 준다. */
        $max = (int) static::withTrashed()
            ->whereRaw("order_number REGEXP '^ORD-[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(order_number, 5) AS UNSIGNED)) AS seq')
            ->value('seq');

        return sprintf('ORD-%04d', $max + 1);
    }

    /**
     * 주문 품목. 공단 제출용 '제품 구매내역' 서류가 이 관계를 근거로 만들어진다.
     * 예전에는 이 관계가 없어 그 서류가 늘 비어 나갔다.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** 신환 · 구환 */
    public const PATIENT_NEW      = 'new';
    public const PATIENT_EXISTING = 'existing';

    public const PATIENT_TYPE_LABELS = [
        self::PATIENT_NEW      => '신환',
        self::PATIENT_EXISTING => '구환',
    ];

    /**
     * 신환인가 구환인가 — 주민등록번호를 갖고 있는지로 가른다.
     *
     * 주민번호가 있어야 공단에 등록·청구가 되므로, 아직 받지 못한 환자는 앞선 절차가
     * 남아 있다는 뜻이다. 별도 표시 항목을 두지 않는 이유는 그것이 사람 손을 타면
     * 실제 자료와 어긋나기 때문이다 — 있는 것을 보고 판단한다.
     *
     * 환자에 없으면 처방전에서 읽은 값도 본다. 신규 접수 건은 환자 기록이 아직 얇다.
     * 여기서는 평문을 열지 않는다. 있는지 없는지만 알면 되므로 감사로그를 남길 일도 없다.
     */
    public function patientType(): string
    {
        $has = $this->patient?->resident_no_hash
            || $this->patient?->resident_no_enc
            || $this->prescription?->resident_no_ocr_enc;

        return $has ? self::PATIENT_EXISTING : self::PATIENT_NEW;
    }

    public function patientTypeLabel(): string
    {
        return self::PATIENT_TYPE_LABELS[$this->patientType()];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function faxLogs(): HasMany
    {
        return $this->hasMany(NhisFaxLog::class);
    }

    public function latestFaxLog(): BelongsTo
    {
        return $this->belongsTo(NhisFaxLog::class, 'latest_fax_log_id');
    }

    /** 지자체 청구 등기 발송 — 반송·누락으로 다시 보내는 일이 있어 여러 건이 쌓인다 */
    public function localDispatches(): HasMany
    {
        return $this->hasMany(LocalClaimDispatch::class);
    }

    /**
     * 교환 · 반품 · 취소 신청.
     *
     * 한 주문에 여러 건이 붙는다 — 교환한 물건을 다시 반품하는 일이 실제로 있다.
     * 최근 것을 앞에 둔다. 목록에서 한 건만 보일 때 지금 상태를 보여야 하기 때문이다.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->latest('id');
    }

    public function tossPayment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\TossPayment::class);
    }
}
