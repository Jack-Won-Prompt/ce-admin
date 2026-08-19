<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CE 샘플주문.
 *
 * 처방 없이 나가는 물건이다. 판매(5001)와 섞지 않는 이유는 청구·정산이 샘플까지
 * 셈에 넣으면 안 되기 때문이다.
 */
class SampleOrder extends Model
{
    use SoftDeletes;

    /**
     * 위드웍스 판매유형 — 6001(CE 샘플주문) 하나뿐이다.
     *
     * 반품(반품·교환·취소)은 아직 정해지지 않았다. 코드가 정해지면 여기를 늘린다 —
     * 값은 위드웍스 code_list 가 정하므로 우리가 미리 지어 두면 안 된다.
     */
    public const TYPE_SALE = '6001';

    public const TYPES = [
        self::TYPE_SALE => 'CE 샘플주문',
    ];

    public const STATUS_LABELS = [
        'draft'     => ['작성중',   'secondary'],
        'sent'      => ['창고전달', 'primary'],
        'shipped'   => ['출고완료', 'success'],
        'cancelled' => ['취소',     'danger'],
    ];

    protected $fillable = [
        'sample_no', 'type', 'patient_id', 'requester_id', 'requester_name',
        'account_name', 'recipient_name', 'mobile', 'postcode', 'address', 'address_detail',
        'order_date', 'delivery_date', 'purpose', 'note',
        'status', 'total_qty', 'total_amount',
        'withworks_so_no', 'withworks_so_id', 'withworks_status', 'withworks_status_label',
        'withworks_sent_at', 'withworks_error',
        'created_by',
    ];

    protected $casts = [
        'order_date'        => 'date',
        'delivery_date'     => 'date',
        'withworks_sent_at' => 'datetime',
        'total_qty'         => 'integer',
        'total_amount'      => 'integer',
    ];

    /** 샘플을 받는 고객. 환자로 등록되지 않은 사람에게 보내는 일이 있어 비어 있을 수 있다 */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SampleOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 샘플을 달라고 한 영업 담당자 — 등록한 사람과 다를 수 있다 */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    /**
     * 접수번호를 짓는다.
     *
     * 날짜 안에서만 이어 붙인다. 주문번호처럼 전체 최대를 찾으면 옛 번호 하나가 규칙을
     * 벗어났을 때 번호가 1 로 되돌아가 부딪힌다 — 실제로 그런 일이 있었다.
     */
    public static function generateNo(): string
    {
        $prefix = 'SMP-' . now()->format('Ymd') . '-';

        $max = (int) static::withTrashed()
            ->where('sample_no', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(sample_no, ?) AS UNSIGNED)) AS seq', [strlen($prefix) + 1])
            ->value('seq');

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
