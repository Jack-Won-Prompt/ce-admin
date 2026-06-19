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
    public const SO_TYPE_LABELS = [
        '1013' => ['CE 판매',   'primary'],
        '1016' => ['개인판매',  'info'],
        '1022' => ['샘플판매',  'warning'],
    ];

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
        $max = static::withTrashed()
            ->where('order_number', 'like', 'ORD-%')
            ->max('order_number');

        if ($max && preg_match('/ORD-(\d+)$/', $max, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            $seq = 1;
        }

        return sprintf('ORD-%04d', $seq);
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

    public function tossPayment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\TossPayment::class);
    }
}
