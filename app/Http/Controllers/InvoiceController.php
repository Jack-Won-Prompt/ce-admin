<?php
// app/Http/Controllers/InvoiceController.php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $taxColExists = Schema::hasColumn('orders', 'tax_invoice_status');

        $invoiceStatuses = ['confirmed', 'shipping', 'delivered'];

        // prescription ㆍ items.lots — 네 화면이 함께 쓰는 칸이 읽는다
        $query = Order::with(['patient', 'prescription.billingOffice', 'items.lots', 'operationUser'])
            ->whereIn('status', $invoiceStatuses)
            ->latest();

        // 탭 필터
        $tab = $request->get('tab', 'all');
        if ($taxColExists) {
            match($tab) {
                'tax_pending'  => $query->where('tax_invoice_status', 'not_issued'),
                'cash_pending' => $query->where('cash_receipt_status', 'not_issued'),
                'tax_issued'   => $query->where('tax_invoice_status', 'issued'),
                'cash_issued'  => $query->where('cash_receipt_status', 'issued'),
                // 요청서 8ㆍ9쪽 — 입금과 출고가 다 된 건. 이제 발행할 차례다.
                'ready'        => self::scopeReady($query),
                default        => null,
            };
        }

        // 검색
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        // 날짜 (delivered_at 없는 주문은 created_at 기준)
        if ($request->filled('date_from')) {
            $query->where(function($sub) use ($request) {
                $sub->whereDate('delivered_at', '>=', $request->date_from)
                    ->orWhere(function($q2) use ($request) {
                        $q2->whereNull('delivered_at')->whereDate('created_at', '>=', $request->date_from);
                    });
            });
        }
        if ($request->filled('date_to')) {
            $query->where(function($sub) use ($request) {
                $sub->whereDate('delivered_at', '<=', $request->date_to)
                    ->orWhere(function($q2) use ($request) {
                        $q2->whereNull('delivered_at')->whereDate('created_at', '<=', $request->date_to);
                    });
            });
        }

        // ── wwGrid용 데이터 ──
        $crTypes = Order::CASH_RECEIPT_TYPE_LABELS;

        /* 네 화면이 함께 쓰던 칸을 여기에도 세운다(요청서 3쪽). 동의 두 가지는 사람에
           붙어, 줄마다 물으면 서른 줄에 예순을 더 묻는다 — 미리 모아 둔다. */
        $rows   = $query->get();
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('patient_id'));

        $gridData = $rows->map(function ($order) use ($taxColExists, $crTypes, $extras) {
            $tiStatus = $taxColExists ? ($order->tax_invoice_status ?? 'not_issued') : null;
            $crStatus = $taxColExists ? ($order->cash_receipt_status ?? 'not_issued') : null;

            $statusLabel = match($order->status) {
                'confirmed' => '주문확정',
                'shipping'  => '배송중',
                'delivered' => '배송완료',
                default     => $order->status,
            };
            $statusColor = match($order->status) {
                'confirmed' => 'var(--primary)',
                'shipping'  => 'var(--warning)',
                'delivered' => 'var(--success)',
                default     => 'var(--text-muted)',
            };

            $tiDisplay = !$taxColExists ? '-'
                : ($tiStatus === 'issued' ? '발행완료'
                : ($tiStatus === 'cancelled' ? '취소' : '미발행'));
            $crDisplay = !$taxColExists ? '-'
                : ($crStatus === 'issued' ? '발행완료'
                : ($crStatus === 'cancelled' ? '취소' : '미발행'));

            return [
                'id'             => $order->id,
                // ── 그리드 표시 컬럼 ──
                'order_number'   => $order->order_number ?? '',
                'patient_name'   => $order->patient?->name ?? '-',
                'product_name'   => $order->product_name ?? '-',
                'total_amount'   => (int) ($order->total_amount ?? 0),
                'status_label'   => $statusLabel,
                'created_at'     => $order->created_at?->format('Y-m-d') ?? '-',
                'delivered_at'   => $order->delivered_at?->format('Y-m-d') ?? '-',
                'ti_display'     => $tiDisplay,
                'cr_display'     => $crDisplay,
                // ── 상세 패널(selectOrder)용 추가 필드 (그리드 컬럼 아님) ──
                'status'         => $order->status,
                'status_color'   => $statusColor,
                'patient_mobile' => $order->patient?->mobile ?? '',
                'tax_col_exists' => $taxColExists,
                'ti_status'      => $tiStatus,
                'ti_no'          => $order->tax_invoice_no ?? '',
                'ti_type'        => $order->tax_invoice_type ?? '',
                'ti_biz_name'    => $order->tax_invoice_biz_name ?? '',
                'ti_biz_no'      => $order->tax_invoice_biz_no ?? '',
                'ti_email'       => $order->tax_invoice_email ?? '',
                'ti_supply'      => (int) ($order->tax_invoice_supply ?? 0),
                'ti_vat'         => (int) ($order->tax_invoice_vat ?? 0),
                'ti_issued_at'   => $order->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '',
                'ti_cancelled_at'=> $order->tax_invoice_cancelled_at?->format('Y-m-d H:i') ?? '',
                'cr_status'      => $crStatus,
                'cr_no'          => $order->cash_receipt_no ?? '',
                'cr_type'        => $order->cash_receipt_type ?? '',
                'cr_type_label'  => isset($order->cash_receipt_type) ? ($crTypes[$order->cash_receipt_type] ?? '') : '',
                'cr_identifier'  => $order->cash_receipt_identifier ?? '',
                'cr_amount'      => (int) ($order->cash_receipt_amount ?? 0),
                'cr_issued_at'   => $order->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '',
                'cr_cancelled_at'=> $order->cash_receipt_cancelled_at?->format('Y-m-d H:i') ?? '',

                /* ── 발행 창을 미리 채울 값 (요청서 8ㆍ9쪽 뒤처리) ─────────
                   지금까지 발행 창은 늘 빈칸으로 열렸다. 담당자가 매번 같은 값을 다시
                   치고, 친 값은 그 주문에만 남아 다음 발행에서 또 빈칸이 된다 —
                   그래서 거래처의 현금영수증번호ㆍ소득공제 구분이 열 명 가운데 아무도
                   채워지지 않았다.

                   거래처가 적어 둔 것이 있으면 그것으로, 없으면 지난번에 쓴 것으로
                   연다. 값이 쌓이는 바퀴가 이 자리에서 돈다. */
                'cr_fill_no'   => self::cashReceiptNo($order),
                'cr_fill_type' => self::cashReceiptType($order),
            ] + self::taxPrefill($order) + [

                // 네 화면이 함께 쓰는 칸 — 차례와 이름이 어디서나 같다
            ] + $extras->rx($order->prescription, $order->patient)
              + $extras->ww($order, $order->prescription, $order->patient)
              + $extras->of($order);
        });

        $total = $gridData->count();

        // 요약 카운트 (컬럼이 있을 때만)
        $counts = collect([
            'total'        => Order::whereIn('status', $invoiceStatuses)->count(),
            'tax_pending'  => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','not_issued')->count() : 0,
            'cash_pending' => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','not_issued')->count() : 0,
            'tax_issued'   => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','issued')->count() : 0,
            'cash_issued'  => $taxColExists ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','issued')->count() : 0,
            /* 요청서 8ㆍ9쪽이 「입금 및 출고 되어야 결제일로 자동 발행」이라 했다. 자동으로
               내보내기 전에, 먼저 그 대상이 무엇인지가 한 자리에 보여야 한다 — 스무 건이
               조용히 밀려 있는지 세 건인지도 모르고 스케줄을 켤 수는 없다. */
            'ready'        => $taxColExists
                ? self::scopeReady(Order::whereIn('status', $invoiceStatuses))->count() : 0,
        ]);

        // 이번 달 발행 금액
        $monthlyTaxAmount  = $taxColExists
            ? Order::whereIn('status',$invoiceStatuses)->where('tax_invoice_status','issued')
                ->whereMonth('tax_invoice_issued_at', now()->month)
                ->whereYear('tax_invoice_issued_at', now()->year)
                ->sum('tax_invoice_supply')
            : 0;

        $monthlyCashAmount = $taxColExists
            ? Order::whereIn('status',$invoiceStatuses)->where('cash_receipt_status','issued')
                ->whereMonth('cash_receipt_issued_at', now()->month)
                ->whereYear('cash_receipt_issued_at', now()->year)
                ->sum('cash_receipt_amount')
            : 0;

        return view('invoice.index', compact(
            'gridData', 'total', 'counts', 'tab', 'taxColExists',
            'monthlyTaxAmount', 'monthlyCashAmount'
        ));
    }

    /**
     * 발행할 차례가 된 건 (요청서 8ㆍ9쪽, 2026-08-31).
     *
     * 「입금 및 출고 되어야」가 조건이다. 둘 중 하나만 되어도 발행하면 안 된다 —
     * 물건이 안 나갔는데 계산서가 먼저 나가거나, 돈을 못 받았는데 영수증이 나간다.
     *
     * 입금은 두 길로 들어온다. 담당자가 통장을 보고 확인한 것과, 토스가 확인해 준 것.
     * 둘 다 「들어왔다」는 하나다 — 한쪽만 보면 나머지 절반이 영영 안 걸린다.
     *
     * 출고는 창고가 알려 준 출고일이 있거나 주문이 배송 단계에 있는 것으로 본다.
     * 출고일은 2026-08-31 부터 받기 시작한 값이라 그 전 건에는 없다.
     *
     * 아직 하나라도 발행이 남아 있는 건만 센다 — 둘 다 끝난 건은 볼 것이 없다.
     */
    public static function scopeReady($query)
    {
        return $query
            ->where(fn ($q) => $q
                ->whereNotNull('deposit_confirmed_at')
                ->orWhereHas('tossPayment', fn ($t) => $t->where('status', 'DONE')))
            ->where(fn ($q) => $q
                ->whereNotNull('shipped_at')
                ->orWhereIn('status', ['shipping', 'delivered']))
            ->where(fn ($q) => $q
                ->where('tax_invoice_status', 'not_issued')
                ->orWhere('cash_receipt_status', 'not_issued'));
    }


    /**
     * 현금영수증 신분확인번호로 쓸 값.
     *
     * 거래처가 적어 둔 번호가 먼저다 — 담당자가 한 번 확인해 둔 값이라 휴대폰보다 믿는다.
     * 자진발급은 번호를 못 받은 건이라 정해진 번호로 낸다.
     */
    private static function cashReceiptNo(Order $order): string
    {
        $p = $order->patient;

        if ($p?->deduction === '자진발급') {
            return \App\Models\Patient::SELF_ISSUE_NO;
        }

        return trim((string) ($p?->cash_receipt_no ?: $p?->mobile ?: ''));
    }

    /** 소득공제인가 지출증빙인가 — 거래처에 적어 둔 것을 따른다 */
    private static function cashReceiptType(Order $order): string
    {
        return $order->patient?->deduction === '지출증빙' ? 'business_expense' : 'income_deduction';
    }

    /**
     * 세금계산서 공급받는자 — 지난번에 쓴 것으로 연다.
     *
     * 상호와 대표는 발행에 반드시 있어야 하는데(팝빌이 거절한다) 주문마다 사람이 적는
     * 값이라 서른일곱 건 가운데 세 건에만 적혀 있다. 거래처에 담을 칸을 새로 만들지 않고
     * 같은 사람의 지난 발행에서 가져온다 — 한 번 적으면 다음부터 따라온다.
     *
     * @return array<string, string>
     */
    private static function taxPrefill(Order $order): array
    {
        $own = trim((string) $order->tax_invoice_biz_name) !== '' ? $order : null;

        $last = $own ?: ($order->patient_id
            ? Order::where('patient_id', $order->patient_id)
                ->whereKeyNot($order->getKey())
                ->whereNotNull('tax_invoice_biz_name')
                ->where('tax_invoice_biz_name', '<>', '')
                ->latest('tax_invoice_issued_at')->latest('id')->first()
            : null);

        /* 번호는 사업자등록번호일 때만 내려보낸다.
           개인 발행 건은 이 칸에 주민등록번호가 들어 있고(가려진 채로 들어간 건도 있다),
           그것을 목록에 실으면 서른 줄짜리 화면의 소스에 주민번호가 흩어진다. 가려진
           값은 발행에도 못 쓴다 — 팝빌이 거절한다. 개인 건은 화면이 「개인」을 고르면
           처방전의 주민번호로 발행하므로(OrderController) 여기서 채울 것이 없다. */
        $no = preg_replace('/\D/', '', (string) ($last->tax_invoice_biz_no ?? ''));

        return [
            'ti_fill_name'  => (string) ($last->tax_invoice_biz_name ?? ''),
            'ti_fill_ceo'   => (string) ($last->tax_invoice_ceo_name ?? ''),
            'ti_fill_no'    => strlen($no) === 10 ? $no : '',
            'ti_fill_email' => (string) ($last->tax_invoice_email ?? $order->patient?->email ?? ''),
        ];
    }

}
