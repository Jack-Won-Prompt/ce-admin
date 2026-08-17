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
     * 위드웍스 판매유형.
     *
     * 되돌리는 것은 판매와 코드가 다르고, 셋끼리도 다르다 — 창고가 하는 일이 다르다.
     * 값은 위드웍스 code_list 가 정한다.
     */
    public const TYPE_SALE     = '6001';
    public const TYPE_CANCEL   = '6004';
    public const TYPE_RETURN   = '6005';
    public const TYPE_EXCHANGE = '6006';

    public const TYPES = [
        self::TYPE_SALE     => 'CE 샘플주문',
        self::TYPE_CANCEL   => 'CE 샘플 취소',
        self::TYPE_RETURN   => 'CE 샘플 반품',
        self::TYPE_EXCHANGE => 'CE 샘플 교환',
    ];

    /** 목록 칩에 쓰는 짧은 이름 — 긴 이름은 한 줄에 넷이 들어가지 않는다 */
    public const TYPE_SHORT = [
        self::TYPE_SALE     => '판매',
        self::TYPE_CANCEL   => '취소',
        self::TYPE_RETURN   => '반품',
        self::TYPE_EXCHANGE => '교환',
    ];

    public const STATUS_LABELS = [
        'draft'     => ['작성중',   'secondary'],
        'sent'      => ['창고전달', 'primary'],
        'shipped'   => ['출고완료', 'success'],
        'cancelled' => ['취소',     'danger'],
    ];

    protected $fillable = [
        'sample_no', 'type',
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

    public function items(): HasMany
    {
        return $this->hasMany(SampleOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
