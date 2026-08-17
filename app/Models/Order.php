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
        '1013' => ['CE 판매',                       'primary'],
        '1016' => ['개인판매',                      'info'],
        '1022' => ['샘플판매',                      'warning'],
        '5001' => ['End User Direct',              'success'],
        '5004' => ['End User Direct 반품·교환·취소', 'danger'],
    ];

    /**
     * 지금 고를 수 있는 판매 유형.
     *
     * 위드웍스와는 End User Direct 로만 주고받기로 해서 하나뿐이다. 1013·1016·1022 는
     * 예전에 쓰던 것이라 위 라벨에는 남겨 둔다 — 그 값으로 저장된 주문이 이미 있고,
     * 목록에서 코드만 덩그러니 보이면 무엇인지 알 수 없다.
     *
     * 5004 는 물건을 되돌리는 유형이라 여기 없다. 판매를 만들면서 고를 수 있게 두면
     * 반품 유형으로 판매가 나간다.
     */
    public const SALE_SO_TYPES = ['5001'];

    /** 교환·반품·취소를 위드웍스로 넘길 때 쓰는 유형 */
    public const RETURN_SO_TYPES = ['5004'];

    /** 저장돼 있을 수 있는 값 — 옛 주문을 수정할 때 막히지 않게 넓게 둔다 */
    public const SO_TYPES = ['1013', '1016', '1022', '5001', '5004'];

    protected $fillable = [
        'order_number', 'prescription_id', 'patient_id', 'created_by',
        'product_name', 'product_code', 'quantity',
        'unit_price', 'nhis_amount', 'patient_copay',
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

    public function tossPayment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\TossPayment::class);
    }
}
