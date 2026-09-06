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
 *   고객 변심 교환   환자가 보내고 · 입금 확인 뒤 다시 나간다
 *   불량 교환        3PL 이 회수하고 · 샘플 교환 오더로 나간다
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
        'reason_code', 'reason_text',
        'collect_tracking_no', 'arrived_at',
        'inspect_confirmed_by', 'inspect_confirmed_at',
        'approved_by', 'approved_at',
        'payment_checked_at', 'order_confirmed_at',
        'refund_method', 'refund_bank', 'refund_account', 'refund_holder',
        'refund_amount', 'refunded_at',
        'credit_issued_at', 'credit_note', 'is_partial',
        'adjust_amount', 'adjust_direction',
        'assigned_user_id', 'created_by',
        // 위드웍스 반품 주문 — 창고가 무엇을 하고 있는지
        'withworks_so_no', 'withworks_so_id', 'withworks_so_type',
        'withworks_status', 'withworks_status_label', 'withworks_sent_at', 'withworks_error',
        'adjust_so_no', 'adjusted_at',
        // 창고가 지금 무엇을 하고 있는가 — 우리 단계(status)와 다른 것을 잰다
        'pl3_status', 'pl3_status_label', 'pl3_status_at', 'pl3_note', 'pl3_note_at',
        // 환불을 실제로 처리한 자취(요청서 4쪽)
        'card_issuer', 'card_expiry', 'refund_approval_no', 'card_cancelled_at',
        'bank_cancelled_at', 'handling_branch', 'refund_agency',
        'refund_cash_receipt_no', 'refund_cash_receipt_type', 'memo', 'staff_memo',
    ];

    protected $casts = [
        'card_cancelled_at'    => 'datetime',
        'bank_cancelled_at'    => 'datetime',
        'pl3_status_at'        => 'datetime',
        'pl3_note_at'          => 'datetime',
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

    /**
     * 절차서의 갈래.
     *
     * 2026-09-02 자 유형표로 불량 반품을 갈랐다. 여태 반품은 한 갈래라 불량으로
     * 되돌아온 것도 변심 반품과 같은 길을 갔는데, 불량은 최초 일자로 청구하므로
     * 입금을 다시 확인하지 않는다 — 교환에서는 이미 갈라 두었던 것이다.
     */
    public const SC_EXCHANGE_MIND   = 'exchange_mind';
    public const SC_EXCHANGE_DEFECT = 'exchange_defect';
    public const SC_RETURN_REFUND   = 'return_refund';
    public const SC_RETURN_DEFECT   = 'return_defect';
    public const SC_CANCEL_PRESHIP  = 'cancel_before_ship';
    public const SC_REFUND_ONLY     = 'refund_only';

    public const SCENARIO_LABELS = [
        self::SC_EXCHANGE_MIND   => '고객 변심 교환',
        self::SC_EXCHANGE_DEFECT => '불량 교환',
        self::SC_RETURN_REFUND   => '반품 및 환불',
        self::SC_RETURN_DEFECT   => '불량 반품',
        self::SC_CANCEL_PRESHIP  => '출고 전 취소',
        self::SC_REFUND_ONLY     => '자격 변경 (환불ㆍ추가 입금)',
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
        self::SC_RETURN_DEFECT => [
            // 불량은 최초 일자 기준으로 청구한다 — 교환 쪽과 같은 까닭이다
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
        'approved'        => '반품 승인',
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
        self::SC_RETURN_DEFECT   => 'Consumer Operation manager',
        self::SC_CANCEL_PRESHIP  => 'Consumer Care manager',
        self::SC_REFUND_ONLY     => 'Consumer Care manager',
    ];

    /** 권한(approve)으로 잠그는 단계 — 검수 확정과 전자 승인 */
    public const APPROVAL_STATUSES = ['inspected', 'approved'];

    /**
     * 승인을 기다리는 건이 서 있을 수 있는 걸음들 — 흐름에서 끌어낸다.
     *
     * 「승인 대기」는 지금 상태가 승인 단계인 것이 아니라, **다음 걸음이 승인**인
     * 것이다. 갈래마다 앞걸음이 달라(변심 교환은 검수중, 출고 전 취소는 확인요청,
     * 자격 변경은 접수) 손으로 적어 두면 갈래가 늘 때 어긋난다.
     *
     * 이 목록은 조회를 좁히는 데만 쓴다 — 줄마다 맞는지는 awaitsApproval() 이 가른다.
     * 「접수」가 여기 드는 것은 자격 변경 때문이라, 상태만으로는 갈라지지 않는다.
     */
    public static function awaitingCandidates(): array
    {
        $out = [];

        foreach (self::FLOWS as $flow) {
            foreach ($flow as $i => $status) {
                $next = $flow[$i + 1] ?? null;
                if ($next && self::needsApproval($next)) {
                    $out[] = $status;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** 이 건이 지금 승인을 기다리는가 — 다음 걸음이 승인이면 그렇다 */
    public function awaitsApproval(): bool
    {
        foreach ($this->nextStatuses() as $s) {
            if (self::needsApproval($s)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 신청 사유 — 표가 비었을 때의 대비다.
     *
     * 배송비 부담 주체를 함께 두었으나 걷었다. 배송비는 없다(2026-09-03 확정).
     *
     * 자격 변경은 물건을 되돌려 받지 않는다 — 금액조정 한 줄로 끝난다.
     */
    public const REASONS = [
        'change_mind'   => ['label' => '단순 변심'],
        'size_exchange' => ['label' => '사이즈 교환'],
        'defect'        => ['label' => '상품 불량'],
        'wrong_item'    => ['label' => '오배송'],
        'delay'         => ['label' => '배송 지연'],
        'eligibility'   => ['label' => '자격 변경'],
        'other'         => ['label' => '기타'],
    ];

    /** 3PL 이 무는 사유 — 불량 교환으로 갈린다 */
    public const DEFECT_REASONS = ['defect', 'wrong_item'];

    /**
     * 사유표 — 이제 표가 원본이다 (요청서 6쪽, 2026-08-31).
     *
     * 위의 REASONS 는 표가 비었을 때의 대비로 남긴다. 화면과 검증은 이 메서드를 본다 —
     * 사유를 늘리거나 규칙을 고치는 일이 배포를 기다리지 않아야 한다.
     *
     * @return array<string, array{label: string}>
     */
    public static function reasons(): array
    {
        $rows = \App\Models\ReturnReason::table()->where('is_active', true);

        if ($rows->isEmpty()) {
            return self::REASONS;
        }

        return $rows->mapWithKeys(fn ($r) => [
            $r->code => ['label' => $r->label],
        ])->all();
    }

    /** 그 사유의 이름 — 표에 없어도 옛 건은 읽혀야 한다 */
    public static function reasonLabel(?string $code): string
    {
        return \App\Models\ReturnReason::table()[$code]?->label
            ?? (self::REASONS[$code]['label'] ?? (string) $code);
    }

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
            return $this->isDefect() ? self::SC_RETURN_DEFECT : self::SC_RETURN_REFUND;
        }

        return $this->subtype === self::SUB_REFUND_ONLY
            ? self::SC_REFUND_ONLY
            : self::SC_CANCEL_PRESHIP;
    }

    public function scenarioLabel(): string
    {
        return self::SCENARIO_LABELS[$this->scenario()] ?? $this->typeLabel();
    }

    /** 3PL 잘못인가 — 승인자와 청구 방식이 여기서 갈린다 */
    public function isDefect(): bool
    {
        return in_array($this->reason_code, self::DEFECT_REASONS, true);
    }

    public function approverRole(): string
    {
        return self::APPROVERS[$this->scenario()] ?? 'Consumer Care manager';
    }

    /** 조정 방향 — 자격 변경은 돌려주기도, 더 받기도 한다 */
    public const ADJ_REFUND = 'refund';
    public const ADJ_CHARGE = 'charge';

    public const ADJ_DIRECTIONS = [
        self::ADJ_REFUND => '환불 (돌려준다)',
        self::ADJ_CHARGE => '추가 입금 (더 받는다)',
    ];

    /**
     * 금액을 조정해야 하는 건인가(2026-09-02 자 유형표).
     *
     * 표가 「조정 필요」라 적은 것은 셋이다 — 부분 교환ㆍ부분 반품ㆍ자격 변경.
     * 전부를 되돌리는 건은 조정이 아니라 취소다(발행을 통째로 무른다). 물건만
     * 바꿔 주는 온전한 교환은 돈이 그대로라 조정할 것이 없다.
     */
    public function needsAdjust(): bool
    {
        return $this->scenario() === self::SC_REFUND_ONLY || $this->is_partial;
    }

    /**
     * 움직일 금액 — 적어 두지 않았으면 줄에서 셈해 본다.
     *
     * 이 칸은 방향(환불·추가 입금)과 짝을 이룬다 — 그러므로 돈이 얼마나
     * 움직이는가여야 한다. 예전에는 「조정 뒤 남는 금액」(본인부담 − 되돌린 몫)을
     * 셈해 넣었는데 방향은 「환불」로 서 있어, 540개 가운데 200개를 되돌리면
     * 「환불 76,500원」이 미리 채워졌다 — 돌려줄 돈은 45,000원인데도.
     * 그대로 저장하면 자취에 틀린 금액이 남는다 (2026-09-06 고침).
     *
     * 사람이 적어 둔 값이 있으면 그것이 정본이다 — 위약금이 섞이는 건이 있어
     * 셈이 늘 맞지는 않는다.
     */
    public function adjustedAmount(): ?int
    {
        if ($this->adjust_amount !== null) {
            return (int) $this->adjust_amount;
        }

        if (!$this->order) {
            return null;
        }

        /* copay 는 줄 전체 금액이다 — 수량을 다시 곱하면 안 된다. 부분만 되돌린
           줄은 그 몫만큼만 센다(OrderReturnItem::refundAmount 가 그 셈이다). */
        $back = (int) $this->items->sum(fn ($i) => $i->refundAmount());

        return max(0, $back);
    }

    public function flow(): array
    {
        $flow = self::FLOWS[$this->scenario()] ?? [];

        /* 부분 건에도 금액조정 단계를 끼운다. 표가 「조정 필요」라 적은 것을 단계로
           두지 않으면, 되돌리고 끝내 버려 남는 금액이 어디에도 정해지지 않는다.
           자격 변경 흐름에는 처음부터 들어 있다 — 두 번 넣지 않는다. */
        if ($this->is_partial && !in_array('adjusted', $flow, true)) {
            /* 돈이 오간 뒤에 조정한다. 반품ㆍ취소는 환불 다음, 교환은 창고에 넘긴
               다음이다 — 얼마가 남는지는 무엇을 되돌려 받았는지가 정해진 뒤에야
               안다. 어느 것도 없는 옛 흐름이면 승인 다음에 둔다. */
            foreach (['refunded', 'order_confirmed', 'approved'] as $anchor) {
                $at = array_search($anchor, $flow, true);
                if ($at !== false) {
                    array_splice($flow, $at + 1, 0, 'adjusted');
                    break;
                }
            }
        }

        return $flow;
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
            self::SC_EXCHANGE_MIND, self::SC_EXCHANGE_DEFECT,
            self::SC_RETURN_REFUND, self::SC_RETURN_DEFECT,
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
    /**
     * 접수번호 — RN + 날짜 + 네 자리.
     *
     * 접두사는 RN 이다. 창고의 반품주문을 RT2609060001 로 바꾸자 둘 다 RT 로
     * 시작해 눈으로 갈리지 않았다 — 날짜 자릿수만 달랐다(네 자리·두 자리).
     * 만드는 곳이 다른 번호는 보기에도 달라야 한다.
     * CR 은 현금영수증이, TI 는 세금계산서가, RX 는 처방전이 이미 쓴다.
     *
     * 가운데 하이픈을 두지 않는다 (2026-09-06). 주문번호(EUD202609061254261)도,
     * 창고 번호(S2609060013·RT2609060001)도 붙여 쓴다 — 이 번호만 뜸어 있어
     * 베껴 옮길 때 끝이 잘리거나 찾을 때 모양이 어긋난다.
     *
     * 이미 발급한 RT-…-… 은 그대로 산다 — 번호는 뒤에 바꾸지 않는다.
     * 그래서 다음 순번을 셀 때 두 꼴을 함께 본다.
     */
    public static function generateReceiptNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'RN' . $date;

        /* 오늘 발급한 것을 세 꼴 모두 본다 — RN(지금)·RT(잠시 쓴 꼴)·RT-…-…(예전).
           번호는 뒤에 바꾸지 않으므로 예전 것도 그대로 산다. */
        $last = static::withTrashed()
            ->where(fn ($q) => $q->where('receipt_no', 'like', $prefix . '%')
                                 ->orWhere('receipt_no', 'like', 'RT' . $date . '%')
                                 ->orWhere('receipt_no', 'like', 'RT-' . $date . '-%'))
            ->get(['receipt_no'])
            ->map(fn ($r) => (int) substr($r->receipt_no, -4))
            ->max();

        return $prefix . str_pad((string) (($last ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }
}
