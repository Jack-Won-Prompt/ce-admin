<?php

namespace App\Models;

use App\Support\BusinessDays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 교환 · 반품 · 취소 신청 한 건.
 *
 * 「Unicorn 교환·반품 절차」는 다섯 갈래를 서로 다르게 다룬다.
 *
 *   고객 변심 교환   환자가 보내고 · 환자가 배송비를 물고 · 입금 확인 뒤 다시 나간다
 *   불량 교환        3PL 이 회수하고 · 3PL 이 배송비를 물고 · 샘플 교환 오더로 나간다
 *   반품 및 환불     물건을 받아 보고 결제를 취소한 뒤 마이너스로 발행한다
 *   출고 전 취소     아직 나가지 않았다 — 수거도 검수도 없다
 *   일반 환불        자격 변경 등. 반품 자체가 없고 금액조정 주문 한 줄로 끝난다
 *
 * 한 흐름으로 묶으면 취소 건에 「수거중」이 뜨고, 불량 교환에 「입금 확인」이 뜬다.
 * 담당자가 무엇을 눌러야 할지 화면이 말해 주지 못한다.
 */
class OrderReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_no', 'order_id', 'type', 'subtype', 'status',
        'reason_code', 'reason_text', 'shipping_burden',
        'collect_tracking_no', 'arrived_at',
        'inspect_confirmed_by', 'inspect_confirmed_at',
        'approved_by', 'approved_at',
        'payment_checked_at', 'order_confirmed_at',
        'refund_method', 'refund_bank', 'refund_account', 'refund_holder',
        'refund_amount', 'refunded_at',
        'credit_issued_at', 'credit_note', 'is_partial',
        'assigned_user_id', 'created_by',
        // 위드웍스 반품 주문 — 창고가 무엇을 하고 있는지
        'withworks_so_no', 'withworks_so_id', 'withworks_so_type',
        'withworks_status', 'withworks_status_label', 'withworks_sent_at', 'withworks_error',
        'adjust_so_no', 'adjusted_at',
        // 창고가 지금 무엇을 하고 있는가 — 우리 단계(status)와 다른 것을 잰다
        'pl3_status', 'pl3_status_label', 'pl3_status_at',
        // 환불을 실제로 처리한 자취(요청서 4쪽)
        'card_issuer', 'card_expiry', 'refund_approval_no', 'card_cancelled_at',
        'bank_cancelled_at', 'handling_branch', 'refund_agency',
        'refund_cash_receipt_no', 'refund_cash_receipt_type', 'memo', 'staff_memo',
    ];

    protected $casts = [
        'card_cancelled_at'    => 'datetime',
        'bank_cancelled_at'    => 'datetime',
        'pl3_status_at'        => 'datetime',
        'arrived_at'           => 'datetime',
        'inspect_confirmed_at' => 'datetime',
        'approved_at'          => 'datetime',
        'payment_checked_at'   => 'datetime',
        'order_confirmed_at'   => 'datetime',
        'refunded_at'          => 'datetime',
        'credit_issued_at'     => 'datetime',
        'withworks_sent_at'    => 'datetime',
        'adjusted_at'          => 'datetime',
        'is_partial'           => 'boolean',
    ];

    public const TYPE_EXCHANGE = 'exchange';
    public const TYPE_RETURN   = 'return';
    public const TYPE_CANCEL   = 'cancel';

    public const TYPES = [
        self::TYPE_EXCHANGE => '교환',
        self::TYPE_RETURN   => '반품',
        self::TYPE_CANCEL   => '취소',
    ];

    /**
     * 취소의 하위 갈래.
     *
     * 「일반 환불」은 새 종류가 아니라 취소의 한 갈래로 둔다. 고객이 보기에는 둘 다
     * 「주문을 무르는 것」이고, 목록에서도 취소로 세어야 한다. 다만 하는 일이 달라
     * 흐름과 창고에 보내는 것이 갈린다.
     */
    public const SUB_BEFORE_SHIP = 'before_ship';
    public const SUB_REFUND_ONLY = 'refund_only';

    public const SUBTYPES = [
        self::SUB_BEFORE_SHIP => '출고 전 취소',
        self::SUB_REFUND_ONLY => '일반 환불 (자격 변경 등)',
    ];

    /** 절차서의 다섯 갈래 */
    public const SC_EXCHANGE_MIND   = 'exchange_mind';
    public const SC_EXCHANGE_DEFECT = 'exchange_defect';
    public const SC_RETURN_REFUND   = 'return_refund';
    public const SC_CANCEL_PRESHIP  = 'cancel_before_ship';
    public const SC_REFUND_ONLY     = 'refund_only';

    public const SCENARIO_LABELS = [
        self::SC_EXCHANGE_MIND   => '고객 변심 교환',
        self::SC_EXCHANGE_DEFECT => '불량 교환',
        self::SC_RETURN_REFUND   => '반품 및 환불',
        self::SC_CANCEL_PRESHIP  => '출고 전 취소',
        self::SC_REFUND_ONLY     => '일반 환불',
    ];

    /**
     * 갈래별 단계 (Unicorn 교환·반품 절차).
     *
     * 절차서의 칸 하나가 단계 하나다. 「전자 승인」과 「검수 확정」을 단계로 두지 않으면
     * 누가 언제 승인했는지가 남지 않고, 승인 없이 출고되는 것을 막을 수도 없다.
     */
    public const FLOWS = [
        self::SC_EXCHANGE_MIND => [
            'received', 'collecting', 'inspecting', 'inspected',
            'approved', 'payment_checked', 'order_confirmed', 'reshipping', 'done',
        ],
        self::SC_EXCHANGE_DEFECT => [
            // 불량은 최초 일자 기준으로 청구하므로 입금을 다시 확인하지 않는다
            'received', 'collecting', 'inspecting', 'inspected',
            'approved', 'order_confirmed', 'reshipping', 'credited', 'done',
        ],
        self::SC_RETURN_REFUND => [
            'received', 'collecting', 'inspecting', 'inspected',
            'approved', 'refunded', 'credited', 'done',
        ],
        self::SC_CANCEL_PRESHIP => [
            // 보낸 물건이 없어 수거·검수가 없다
            'received', 'confirming', 'approved', 'refunded', 'done',
        ],
        self::SC_REFUND_ONLY => [
            // 반품이 없다 — 승인하고, 결제를 취소하고, 금액조정 주문을 세우고, 발행한다
            'received', 'approved', 'refunded', 'adjusted', 'credited', 'done',
        ],
    ];

    public const STATUS_LABELS = [
        'received'        => '접수',
        'collecting'      => '수거중',
        'inspecting'      => '검수중',
        'inspected'       => '검수 확정',
        'confirming'      => '확인요청',
        'approved'        => '전자 승인',
        'payment_checked' => '입금 확인',
        'order_confirmed' => '오더 확정',
        'reshipping'      => '재발송',
        'refunded'        => '환불완료',
        'adjusted'        => '금액조정',
        'credited'        => '발행 완료',
        'done'            => '완료',
        'cancelled'       => '취소',
    ];

    /**
     * 그 단계를 누가 하는가 — 절차서의 첫 줄이다.
     *
     * 화면에 적어 두면 담당자가 「내가 누를 것인지」를 매번 묻지 않는다.
     */
    public const STATUS_ACTORS = [
        'received'        => 'Care team(상담)',
        'collecting'      => 'Patient · 3PL',
        'inspecting'      => '3PL',
        'inspected'       => 'Care team manager',
        'confirming'      => 'Care team(상담)',
        'approved'        => 'Consumer manager',
        'payment_checked' => 'Consumer Operation',
        'order_confirmed' => 'Consumer Operation',
        'reshipping'      => '3PL',
        'refunded'        => 'Consumer Care manager',
        'adjusted'        => 'Consumer Operation',
        'credited'        => 'Consumer Operation',
        'done'            => '—',
    ];

    /** 전자 승인을 누구가 하는가 — 갈래마다 다르다 */
    public const APPROVERS = [
        self::SC_EXCHANGE_MIND   => 'Consumer Care manager',
        self::SC_EXCHANGE_DEFECT => 'Consumer Operation manager',
        self::SC_RETURN_REFUND   => 'Consumer Care manager',
        self::SC_CANCEL_PRESHIP  => 'Consumer Care manager',
        self::SC_REFUND_ONLY     => 'Consumer Care manager',
    ];

    /** 권한(approve)으로 잠그는 단계 — 검수 확정과 전자 승인 */
    public const APPROVAL_STATUSES = ['inspected', 'approved'];

    /**
     * 신청 사유와 배송비 부담 주체 (CR-RTN-07 · Unicorn 절차서).
     *
     * 사유가 정해지면 누가 무는지도 정해진다. 담당자가 매번 판단하면 사람마다 달라지고,
     * 고객에게 안내한 내용도 갈린다. 다만 고칠 수는 있게 둔다 — 예외가 늘 있다.
     *
     * 자격 변경은 물건을 되돌려 받지 않는다 — 일반 환불로 간다.
     */
    public const REASONS = [
        'change_mind'   => ['label' => '단순 변심',   'burden' => 'customer'],
        'size_exchange' => ['label' => '사이즈 교환', 'burden' => 'customer'],
        'defect'        => ['label' => '상품 불량',   'burden' => 'company'],
        'wrong_item'    => ['label' => '오배송',      'burden' => 'company'],
        'delay'         => ['label' => '배송 지연',   'burden' => 'company'],
        'eligibility'   => ['label' => '자격 변경',   'burden' => null],
        'other'         => ['label' => '기타',        'burden' => null],
    ];

    /** 3PL 이 무는 사유 — 불량 교환으로 갈린다 */
    public const DEFECT_REASONS = ['defect', 'wrong_item'];

    /**
     * 사유표 — 이제 표가 원본이다 (요청서 6쪽, 2026-08-31).
     *
     * 위의 REASONS 는 표가 비었을 때의 대비로 남긴다. 화면과 검증은 이 메서드를 본다 —
     * 사유를 늘리거나 규칙을 고치는 일이 배포를 기다리지 않아야 한다.
     *
     * @return array<string, array{label: string, burden: ?string}>
     */
    public static function reasons(): array
    {
        $rows = \App\Models\ReturnReason::table()->where('is_active', true);

        if ($rows->isEmpty()) {
            return self::REASONS;
        }

        return $rows->mapWithKeys(fn ($r) => [
            $r->code => ['label' => $r->label, 'burden' => $r->burden],
        ])->all();
    }

    /** 그 사유의 이름 — 표에 없어도 옛 건은 읽혀야 한다 */
    public static function reasonLabel(?string $code): string
    {
        return \App\Models\ReturnReason::table()[$code]?->label
            ?? (self::REASONS[$code]['label'] ?? (string) $code);
    }

    public const BURDENS = ['customer' => '고객 부담', 'company' => '판매자 부담'];

    public const COLLECT_METHODS = ['courier' => '택배 자동수거', 'self' => '고객 직접발송'];

    public const REFUND_METHODS = [
        'account' => '계좌 환불',
        'card'    => '카드 결제취소',
        'va'      => '가상계좌 환불',
    ];

    /**
     * 환불분 현금영수증을 어느 몫으로 끊는가 (요청서 4쪽).
     *
     * 주문 발행 때 쓰는 두 가지(소득공제ㆍ지출증빙)에 둘을 더 둔다. 번호를 못 받은
     * 건은 자진발급으로 끊고, 카드로 돌려준 건은 현금영수증이 아니라 카드 취소로
     * 처리했다는 표시다 — 그것도 적어 두어야 나중에 「왜 안 끊었나」를 되묻지 않는다.
     */
    public const REFUND_RECEIPT_TYPES = [
        'income_deduction'  => '소득공제',
        'business_expense'  => '지출증빙',
        'self_issue'        => '자진발급',
        'card'              => '카드결제',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class)->orderBy('id');
    }

    /**
     * 창고에 알렸는가.
     *
     * 출고 전 취소는 반품 주문을 세우지 않고 원 판매주문을 취소하므로, 번호는 원
     * 판매주문의 것이고 유형은 비어 있다. 그래도 알린 것은 알린 것이다.
     */
    public function sentToWithworks(): bool
    {
        return (bool) $this->withworks_so_no;
    }

    /** 반품 주문을 따로 세웠는가 — 출고 전 취소는 세우지 않는다 */
    public function hasReturnSo(): bool
    {
        return (bool) ($this->withworks_so_no && $this->withworks_so_type);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderReturnLog::class)->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function inspectConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspect_confirmed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * 이 건이 절차서의 어느 갈래인가.
     *
     * 종류만으로는 갈리지 않는다 — 교환은 사유가, 취소는 하위 갈래가 갈래를 정한다.
     */
    public function scenario(): string
    {
        if ($this->type === self::TYPE_EXCHANGE) {
            return $this->isDefect() ? self::SC_EXCHANGE_DEFECT : self::SC_EXCHANGE_MIND;
        }

        if ($this->type === self::TYPE_RETURN) {
            return self::SC_RETURN_REFUND;
        }

        return $this->subtype === self::SUB_REFUND_ONLY
            ? self::SC_REFUND_ONLY
            : self::SC_CANCEL_PRESHIP;
    }

    public function scenarioLabel(): string
    {
        return self::SCENARIO_LABELS[$this->scenario()] ?? $this->typeLabel();
    }

    /** 3PL 잘못인가 — 배송비와 승인자와 청구 방식이 여기서 갈린다 */
    public function isDefect(): bool
    {
        return in_array($this->reason_code, self::DEFECT_REASONS, true);
    }

    public function approverRole(): string
    {
        return self::APPROVERS[$this->scenario()] ?? 'Consumer Care manager';
    }

    public function flow(): array
    {
        return self::FLOWS[$this->scenario()] ?? [];
    }

    /** 이 건이 다음에 갈 수 있는 곳. 취소는 어디서든 된다. */
    public function nextStatuses(): array
    {
        $flow = $this->flow();
        $at   = array_search($this->status, $flow, true);

        $next = ($at !== false && isset($flow[$at + 1])) ? [$flow[$at + 1]] : [];

        if (!in_array($this->status, ['cancelled', 'done'], true)) {
            $next[] = 'cancelled';
        }

        return $next;
    }

    /** 그 단계를 누르려면 승인 권한이 있어야 하는가 */
    public static function needsApproval(string $status): bool
    {
        return in_array($status, self::APPROVAL_STATUSES, true);
    }

    // ── 기한 ────────────────────────────────────────────────
    //
    // 절차서는 「입고일로부터 2영업일 이내 검수 · 3영업일 이내 출고」다. 재지 않으면
    // 지켜지는지 알 수 없고, 늦은 건이 조용히 묻힌다.

    /** 기한을 재는 갈래인가 — 물건이 창고에 들어오는 것만 잰다 */
    public function hasDeadlines(): bool
    {
        return in_array($this->scenario(), [
            self::SC_EXCHANGE_MIND, self::SC_EXCHANGE_DEFECT, self::SC_RETURN_REFUND,
        ], true);
    }

    public function inspectDueAt(): ?\Carbon\CarbonImmutable
    {
        if (!$this->arrived_at || !$this->hasDeadlines()) {
            return null;
        }

        return BusinessDays::add($this->arrived_at, (int) config('returns.inspect_days', 2));
    }

    public function finalDueAt(): ?\Carbon\CarbonImmutable
    {
        if (!$this->arrived_at || !$this->hasDeadlines()) {
            return null;
        }

        return BusinessDays::add($this->arrived_at, (int) config('returns.ship_days', 3));
    }

    /** 마지막 기한이 무엇을 재는가 — 교환은 출고, 반품은 발행이다 */
    public function finalStatus(): string
    {
        return $this->type === self::TYPE_EXCHANGE ? 'reshipping' : 'credited';
    }

    /** 그 단계를 이미 지났는가 */
    public function reached(string $status): bool
    {
        $flow = $this->flow();
        $now  = array_search($this->status, $flow, true);
        $at   = array_search($status, $flow, true);

        return $now !== false && $at !== false && $now >= $at;
    }

    /**
     * 지금 늦었는가 — [무엇이, 며칠] 또는 null.
     *
     * 아직 안 지난 단계의 기한만 본다. 검수를 마쳤으면 검수 기한은 더 이상 재지 않는다.
     */
    public function overdue(): ?array
    {
        if (!$this->arrived_at || in_array($this->status, ['cancelled', 'done'], true)) {
            return null;
        }

        if (!$this->reached('inspected') && ($due = $this->inspectDueAt())) {
            $left = BusinessDays::until($due);
            if ($left < 0) {
                return ['검수', -$left];
            }
        }

        if (!$this->reached($this->finalStatus()) && ($due = $this->finalDueAt())) {
            $left = BusinessDays::until($due);
            if ($left < 0) {
                return [$this->type === self::TYPE_EXCHANGE ? '출고' : '발행', -$left];
            }
        }

        return null;
    }

    /** 접수번호 — 고객에게 알려 주는 번호라 날짜와 순번으로 읽히게 만든다 */
    public static function generateReceiptNo(): string
    {
        $prefix = 'RT-' . now()->format('Ymd') . '-';

        $last = static::withTrashed()->where('receipt_no', 'like', $prefix . '%')
            ->orderByDesc('receipt_no')->value('receipt_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
