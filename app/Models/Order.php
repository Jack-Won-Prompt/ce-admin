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
        // 2026-08-31 위드웍스가 되돌리는 세 가지를 다 열었다 — 전에는 반품만 있었다
        '1504' => ['End User Direct 취소',     'danger'],
        '1505' => ['End User Direct 반품',     'danger'],
        '1506' => ['End User Direct 교환',     'warning'],
        '1601' => ['CE 샘플주문',              'warning'],
        '1605' => ['CE 샘플 반품',             'danger'],
        // 일반 환불(자격 변경 등) — 물건은 움직이지 않고 금액만 마이너스로 맞춘다
        '1092' => ['전산판매 (금액조정)',      'secondary'],
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
    public const SO_TYPES = ['1013', '1016', '1022', '1092',
                             '1501', '1504', '1505', '1506', '1601', '1605',
                             '5001', '5004', '5005', '5006', '6001'];

    /**
     * 결제 방식 — 가상계좌ㆍ카드결제ㆍ무통장입금.
     *
     * 정해 둔 것이 있으면 그것을 따른다. 없으면 보낸 결제 링크에서 되짚는다 —
     * 낸 것이 있으면 그 방식, 아직이면 마지막으로 보낸 방식, 그것도 없으면 가상계좌다.
     */
    public function payMethod(): string
    {
        if ($this->pay_method && isset(\App\Models\PaymentLink::METHODS[$this->pay_method])) {
            return $this->pay_method;
        }

        $link = $this->paymentLinks->firstWhere('status', 'paid')
             ?? $this->paymentLinks->sortByDesc('id')->first();

        return $link?->method ?? \App\Models\PaymentLink::METHOD_VIRTUAL;
    }

    public function payMethodLabel(): string
    {
        return \App\Models\PaymentLink::METHODS[$this->payMethod()] ?? '가상계좌';
    }

    /** 이 주문으로 보낸 결제 링크들 — 결제 방식을 정해 두지 않았을 때 여기서 되짚는다. */
    public function paymentLinks()
    {
        return $this->hasMany(\App\Models\PaymentLink::class);
    }

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

    /**
     * 물건이 나갔는가.
     *
     * 창고가 알려 주는 출고일이 먼저다. 그 값은 2026-08-31 부터 받기 시작했으므로 그
     * 전 건에는 없어, 주문이 배송 단계에 들었는지로 갈음한다.
     *
     * 한 곳에 두는 까닭 — 발행이 이 판정에 걸린다(요청서 8ㆍ9쪽). 두 곳에 적어 두면
     * 언젠가 갈리고, 갈린 쪽이 「출고 전인데 이미 신고된 건」을 만든다.
     */
    public function isShipped(): bool
    {
        return $this->shipped_at !== null
            || in_array($this->status, self::AFTER_SHIP, true);
    }

    /** 담당자가 손으로 확인한 건인가 — 토스가 아니라 사람이 본 것 */
    public function isDepositConfirmedByHand(): bool
    {
        return $this->deposit_confirmed_at !== null;
    }

    /**
     * 들어와야 하는 금액 — 본인부담금.
     *
     * 배송비는 없다(2026-09-03 확정). 그 전 스물여섯 건에 3,000원이 적혀 있으나
     * 받을 돈으로 세지 않는다 — 세면 이미 끝난 건이 영영 「3,000원 모자람」으로
     * 남는다. 적힌 값 자체는 지우지 않는다. 그때 실제로 받은 돈이고, 그 가운데
     * 열한 건은 그 금액으로 증빙까지 나갔다.
     */
    public function expectedDeposit(): int
    {
        return (int) ($this->patient_copay ?? 0);
    }

    protected $fillable = [
        'order_number', 'prescription_id', 'patient_id', 'created_by',
        'product_name', 'product_code', 'quantity',
        'unit_price', 'nhis_amount', 'patient_copay',
        // 담당자가 눈으로 확인한 입금 — 토스가 알려 주지 못하는 건을 위한 자리
        'deposit_confirmed_at', 'deposit_confirmed_by', 'deposit_amount', 'deposit_note',
        'pay_method',
        'total_amount',
        'status', 'so_type', 'shipping_address', 'tracking_number',
        'estimated_delivery', 'delivered_at',
        'nhis_claim_status', 'nhis_submitted_at', 'nhis_approved_at', 'nhis_reject_stage',
        // 정산이 어디까지 갔는가(요청서 12쪽)
        'settle_status', 'settle_status_at', 'settle_status_by', 'settle_reason',
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
        // 창고가 알려 주는 진짜 출고일 — withworks_ship_at 은 우리가 적어 둔 시각이다
        'shipped_at',
        // 발행ㆍ청구ㆍ정산을 맡은 사람과 그 자취(요청서 6ㆍ10ㆍ11ㆍ12쪽)
        'operation_user_id', 'closing_checked_at', 'closing_checked_by', 'reference_note',
        // 배송 — 우편번호와 상세주소는 칸이 없어 사라지던 값이다(2026-08-28 마이그레이션)
        'shipping_recipient', 'shipping_postcode', 'shipping_address_detail',
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
        'shipped_at'                => 'date',
        'closing_checked_at'        => 'datetime',
        'settle_status_at'          => 'datetime',
        'unit_price'          => 'float',
        'nhis_amount'         => 'float',
        'patient_copay'       => 'float',
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

    /**
     * 주문이 지나는 자리.
     *
     * 넷이던 것을 창고가 알려 오는 단계대로 늘렸다(2026-09-03 · 테스트 시나리오 4).
     * 여태 할당ㆍ피킹ㆍ송장은 사건 기록에만 남고 상태는 「주문 확정」에 멈춰 있었다 —
     * 담당자는 물건이 어디쯤 왔는지 목록에서 알 수 없어 위드웍스 화면을 따로 열었다.
     *
     * 위드웍스가 보내는 사건과 하나씩 짝이 된다(WithworksWebhookController).
     * 「배송 완료」만 짝이 없다 — 그쪽은 택배사 조회가 없어 배송이 끝난 때를 알지
     * 못한다(2026-08-15 합의). 사람이 손으로 옮기거나, 저쪽에 그 사건이 생기면 잇는다.
     */
    public const STATUS_LABELS = [
        'pending'   => ['label' => '주문 대기',  'badge' => 'secondary'],
        'confirmed' => ['label' => '주문 확정',  'badge' => 'primary'],
        'allocated' => ['label' => '재고 할당',  'badge' => 'primary'],
        'picked'    => ['label' => '피킹 완료',  'badge' => 'primary'],
        'invoiced'  => ['label' => '송장 출력',  'badge' => 'info'],
        'shipping'  => ['label' => '출고 완료',  'badge' => 'info'],
        'delivered' => ['label' => '배송 완료',  'badge' => 'success'],
        'cancelled' => ['label' => '취소',        'badge' => 'danger'],
    ];

    /**
     * 창고로 넘어간 뒤의 자리 — 「확정 이상」을 묻는 곳이 쓴다.
     *
     * 청구ㆍ발행ㆍ정산이 모두 이 목록으로 대상을 고른다. 손으로 적어 두었더니
     * 상태를 늘릴 때마다 여덟 곳을 고쳐야 했고, 한 곳이라도 빠뜨리면 그 건이
     * 청구 목록에서 조용히 사라진다.
     */
    public const OPEN_AFTER_CONFIRM = [
        'confirmed', 'allocated', 'picked', 'invoiced', 'shipping', 'delivered',
    ];

    /** 창고에서 물건이 나간 뒤의 자리 */
    public const AFTER_SHIP = ['shipping', 'delivered'];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? $this->status;
    }

    /**
     * 되돌릴 수 있는 원 주문만.
     *
     * 교환ㆍ반품ㆍ취소는 모두 이미 나간 것을 되돌리는 일이다. 두 가지를 함께 본다.
     *
     * 하나, 창고에 판매주문이 서 있어야 한다. 아직 보내지 않은 주문은 되돌릴 물건도,
     * 취소할 판매주문도 그쪽에 없다 — 그런 건은 주문 등록에서 지우면 된다.
     *
     * 둘, 주문이 확정된 뒤여야 한다. 「주문 대기」는 우리 쪽에서 아직 손대는 중이라
     * 되돌릴 것이 아니고, 「취소」는 이미 되돌린 것이라 두 번 되돌릴 수 없다.
     * 판매주문 번호만 보고 골랐더니 주문 대기 열일곱 건이 고를 거리로 섰다.
     *
     * 고르는 자리가 둘이라(찾기 창ㆍ옛 접수 화면) 규칙을 여기 한 곳에 둔다.
     */
    public function scopeSentToWarehouse($q)
    {
        return $q->whereNotNull('withworks_so_no')
                 ->where('withworks_so_no', '!=', '')
                 ->whereIn('status', self::RETURNABLE_STATUSES);
    }

    /** 되돌릴 수 있는 주문 상태 — 대기(아직 우리 손 안)와 취소(이미 되돌림)는 뺀다 */
    public const RETURNABLE_STATUSES = self::OPEN_AFTER_CONFIRM;

    /**
     * 주문번호 옆에 붙여 적는 판매번호(2026-09-03 지시).
     *
     * 창고와 이야기할 때 쓰는 번호는 우리 주문번호가 아니라 저쪽 판매번호다.
     * 화면마다 주문번호만 적어 두었더니, 담당자가 창고에 전화할 때마다 주문을
     * 열어 판매번호를 찾아야 했다.
     *
     * 아직 창고에 넘기지 않은 건은 빈 문자열을 돌려준다 — 「· —」 같은 빈 자리를
     * 세워 두면 무엇이 빠진 것처럼 읽힌다.
     */
    public function saleNoSuffix(string $sep = ' · '): string
    {
        $so = trim((string) $this->withworks_so_no);

        return $so === '' ? '' : $sep . $so;
    }

    /** 주문번호 옆에 붙여 적는 판매번호 — 없으면 null */
    public function saleNo(): ?string
    {
        $so = trim((string) $this->withworks_so_no);

        return $so === '' ? null : $so;
    }

    /** 주문번호 앞머리 — End User Direct */
    public const NUMBER_PREFIX = 'EUD';

    /**
     * 주문번호 — EUD + 년월일 + 시분초 + 그 초의 차례 한 자리(2026-09-03 확정).
     *
     *   EUD 20260903 161830 1
     *   └┬┘ └───┬──┘ └──┬─┘ │
     *    │      │      │    같은 초에 몇 번째인가
     *    │      │      만든 시각
     *    │      만든 날
     *    End User Direct
     *
     * 예전에는 ORD-0001 처럼 통째로 이어지는 번호였다. 번호만 보고는 언제 것인지 알 수
     * 없어, 목록에서 날짜 칸을 함께 봐야 했다.
     *
     * **끝 한 자리를 두는 까닭** — 시각만으로는 겹친다. 주문이 서는 자리가 둘인데,
     * 그 가운데 하나가 「동의 서명이 끝나면 자동으로」다. 환자가 각자 휴대폰에서
     * 서명하는 일이라 우리가 시각을 조절할 수 없어, 둘이 같은 초에 마치면 번호가
     * 부딪힌다. order_number 에는 unique 가 걸려 있어 그때 저장이 실패하고 —
     * 하필 환자 쪽 화면에서 — 서명은 끝났는데 「오류」가 뜬다.
     *
     * 지운 건도 함께 센다(withTrashed). 되살렸을 때 부딪히지 않는다.
     *
     * 한 초에 아홉 건을 넘으면 다음 초로 넘어가 다시 센다. 그런 일은 없겠지만,
     * 없다고 여겨 두면 있는 날 주문이 서지 않는다.
     *
     * 옛 ORD- 번호는 그대로 둔다. 이미 창고ㆍ증빙ㆍ공단 서류에 적혀 나간 번호다.
     */
    public static function generateOrderNumber(?\Carbon\CarbonInterface $when = null): string
    {
        $at = $when ? $when->copy() : now();

        /* 한 초가 다 차면 다음 초로 민다. 60초까지만 밀어 본다 — 그보다 오래 도는
           것은 무언가 잘못된 것이고, 그때는 끝없이 도는 것보다 멈추는 편이 낫다. */
        for ($i = 0; $i < 60; $i++) {
            $prefix = self::NUMBER_PREFIX . $at->format('YmdHis');

            $max = (int) static::withTrashed()
                ->where('order_number', 'like', $prefix . '%')
                ->whereRaw('CHAR_LENGTH(order_number) = ?', [strlen($prefix) + 1])
                ->selectRaw('MAX(CAST(SUBSTRING(order_number, ?) AS UNSIGNED)) AS seq', [strlen($prefix) + 1])
                ->value('seq');

            if ($max < 9) {
                return $prefix . ($max + 1);
            }

            $at = $at->addSecond();
        }

        throw new \RuntimeException('주문번호를 만들지 못했습니다 — 같은 시각에 너무 많은 주문이 섰습니다.');
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

    /**
     * 정산이 어디까지 갔는가 (요청서 12쪽, 2026-08-31 회신 A).
     *
     * 「마감」은 셈을 닫은 것이고 「확정」은 그 뒤 되돌릴 수 없게 잠근 것이다.
     * 둘을 가르는 까닭 — 닫고 나서도 며칠은 고칠 일이 생긴다. 잠그는 것을 따로 두지
     * 않으면 담당자가 닫기를 미루게 되고, 그러면 어디까지 봤는지가 영영 안 남는다.
     */
    public const SETTLE_STATUS_LABELS = [
        'open'      => '진행중',
        'closed'    => '마감',
        'confirmed' => '확정',
        'rejected'  => '반려',
        'on_hold'   => '보류',
        'cancelled' => '취소',
    ];

    /** 확정은 끝이다 — 여기서 다른 데로 가지 않는다 */
    public const SETTLE_LOCKED = 'confirmed';

    public function settleStatusLabel(): string
    {
        return self::SETTLE_STATUS_LABELS[$this->settle_status] ?? (string) $this->settle_status;
    }

    /** 잠긴 건인가 — 잠긴 것은 금액도 상태도 손대지 않는다 */
    public function isSettleLocked(): bool
    {
        return $this->settle_status === self::SETTLE_LOCKED;
    }

    /**
     * 청구가 어디까지 갔는가 (요청서 13쪽, 2026-08-31 회신 A).
     *
     * 낸 것(청구완료)과 공단이 인정한 것(승인)은 다른 일이라 한 칸으로 묶지 않는다.
     * 보류는 공단이 판단을 미룬 것이다(2026-08-31 회신) — 미청구와 다르다. 미청구는
     * 「우리가 아직 손대지 않았다」이고 보류는 「내고 나서 멈췄다」라, 섞으면 무엇을
     * 살펴봐야 하는지가 묻힌다.
     */
    public const CLAIM_STATUS_LABELS = [
        'pending'    => '청구 전',
        'submitting' => '청구중',
        'submitted'  => '청구완료',
        'approved'   => '승인',
        'rejected'   => '반려',
        'on_hold'    => '보류',
        'cancelled'  => '취소',
    ];

    /**
     * 반려 뒤의 걸음 (요청서 13쪽).
     *
     * 상태로 두지 않는 까닭 — 재신청 중에도 그 건은 여전히 반려된 건이다. 상태를
     * 옮겨 버리면 「반려 몇 건」이 갑자기 줄어, 다시 내야 할 일이 눈에서 사라진다.
     */
    public const CLAIM_REJECT_STAGES = [
        'reviewed'    => '관할 지사의 검토결과 반려',
        'resubmitted' => '재신청(승인대기)',
        'redone'      => '재신청완료',
    ];

    public function claimStatusLabel(): string
    {
        return self::CLAIM_STATUS_LABELS[$this->nhis_claim_status] ?? (string) $this->nhis_claim_status;
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

    /**
     * 발행ㆍ청구ㆍ정산을 맡은 사람 (Consumer Operation).
     *
     * 상담을 맡은 사람(prescriptions.assigned_user_id)과 다르다. 상담한 사람과 청구한
     * 사람이 다른 것이 예사라, 한 칸에 담으면 「누구에게 물어야 하나」가 갈리지 않는다.
     */
    public function operationUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'operation_user_id');
    }

    /** 마감을 확인한 사람 — 「확인했다」만 남기면 되물을 사람을 못 찾는다 */
    public function closingChecker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_checked_by');
    }


    /** 정산 상태를 마지막으로 옮긴 사람 */
    public function settleBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'settle_status_by');
    }

}
