<?php
// app/Http/Controllers/NhisController.php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NhisController extends Controller
{
    // ── 목록 ─────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        // items.lots — 네 화면이 함께 쓰는 칸에 Lotㆍ유효기간이 선다
        $query = Order::with(['patient', 'prescription.billingOffice', 'items.lots', 'operationUser'])
            ->whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->latest();

        // NHIS 청구 상태 필터
        if ($request->filled('nhis_status')) {
            $query->where('nhis_claim_status', $request->nhis_status);
        }

        /* 신구매·재구매는 처방전에 사람이 적는 값이다(요청서 11쪽). 예전에는 주민번호가
           있느냐로 신환·구환을 스스로 갈랐는데, 그 칸과 공통 칸의 신구매/재구매가 목록에
           나란히 서면서 비슷해 보이는 칸이 둘이 됐다. 하나로 모은다.
           주민번호 유무는 그대로 쓰인다 — 공단 등록 서류를 보낼지 가르는 자리
           (NhisAssistController) 는 Order::patientType() 을 그대로 본다. */
        if ($request->filled('purchase_type')) {
            $type = $request->purchase_type;
            $query->whereHas('prescription', fn ($p) => $p->where('purchase_type', $type));
        }

        // 공단이냐 지자체냐 — 청구처가 다르면 서류도 보내는 법도 다르다
        if ($request->filled('agency')) {
            $agency = $request->agency;
            $query->whereHas('prescription', fn ($p) => $agency === \App\Support\ClaimAgency::NHIS
                ? $p->where(fn ($x) => $x->where('claim_agency', $agency)->orWhereNull('claim_agency'))
                : $p->where('claim_agency', $agency));
        }

        /* 자료가 갖춰진 건만 추린다. 청구할 수 있는 것과 아직 못 하는 것이 목록에서 똑같아
           보이면 담당자가 하나씩 열어 보고 닫기를 반복한다. */
        if ($request->filled('ready')) {
            $query->where('claim_ready', $request->ready === 'y');
        }

        // 검색 (이름, 주문번호)
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        /* 2차 요청(R2-09) — 주민번호로 찾기.
           평문은 어디에도 내려보내지 않으므로 마스킹된 값에서 부분검색만 한다. 앞 6자리는
           마스킹에 그대로 남아 있어 생년월일로 찾는 실제 쓰임은 이것으로 된다. */
        if ($request->filled('rrn')) {
            $rrn = preg_replace('/\D/', '', $request->rrn);
            if ($rrn !== '') {
                $query->where(fn ($sub) => $sub
                    ->whereHas('patient', fn ($p) => $p->where('resident_no_masked', 'like', "%{$rrn}%"))
                    ->orWhereHas('prescription', fn ($p) => $p->where('resident_no_ocr_masked', 'like', "%{$rrn}%")));
            }
        }

        // 현금영수증 — 발행 여부와 번호 둘 다로 찾는다
        if ($request->filled('cash_receipt')) {
            match ($request->cash_receipt) {
                'issued'     => $query->whereNotNull('cash_receipt_no')->where('cash_receipt_status', '!=', 'cancelled'),
                'not_issued' => $query->where(fn ($s) => $s->whereNull('cash_receipt_no')->orWhere('cash_receipt_status', 'cancelled')),
                default      => $query->where('cash_receipt_no', 'like', '%' . $request->cash_receipt . '%'),
            };
        }

        /* 날짜 필터 — 출고일 기준.
           예전에는 배송완료일(delivered_at)로 걸렀는데, 위드웍스가 배송 상태를 관리하지 않아
           그 값이 채워지지 않는다. 기간을 넣으면 아무것도 안 나오는 필터였다.
           출고 시각이 없으면 접수일로 떨어뜨려, 손으로 만든 주문도 걸리게 한다. */
        $shipDate = 'COALESCE(delivered_at, withworks_ship_at, created_at)';

        if ($request->filled('date_from')) {
            $query->whereRaw("{$shipDate} >= ?", [$request->date_from . ' 00:00:00']);
        }
        if ($request->filled('date_to')) {
            $query->whereRaw("{$shipDate} <= ?", [$request->date_to . ' 23:59:59']);
        }

        /* 청구일 기간 — 위의 기간과 다른 것을 센다(요청서 11쪽).
           위는 「언제 나갔는가」이고 이것은 「언제 청구했는가」다. 한 달치 청구를
           묶어 볼 때 쓰는 것은 이쪽이라, 한 칸으로 합치면 둘 중 하나를 못 본다. */
        if ($request->filled('claim_from')) {
            $query->whereDate('nhis_submitted_at', '>=', $request->claim_from);
        }
        if ($request->filled('claim_to')) {
            $query->whereDate('nhis_submitted_at', '<=', $request->claim_to);
        }

        // 전자세금계산서 — 현금영수증과 나란히 거른다(요청서 11쪽)
        if ($request->filled('tax_invoice')) {
            match ($request->tax_invoice) {
                'issued'     => $query->where('tax_invoice_status', 'issued'),
                'not_issued' => $query->where('tax_invoice_status', '!=', 'issued'),
                default      => null,
            };
        }

        // 청구 상태 이름은 모델이 한 벌만 갖는다(Order::CLAIM_STATUS_LABELS)
        $nhisStatusLabels = Order::CLAIM_STATUS_LABELS + [
            'pending'   => '청구 전',
            'submitted' => '청구완료',
            'approved'  => '승인',
            'rejected'  => '반려',
            'cancelled' => '주문취소',
        ];

        /* 네 화면이 함께 쓰던 칸을 여기에도 세운다(요청서 3쪽). 동의 두 가지는 사람에
           붙어 줄마다 물으면 서른 줄에 예순을 더 묻는다 — 미리 한 번에 모아 둔다. */
        $rows   = $query->get();
        $extras = \App\Support\OrderGridExtras::forPatients($rows->pluck('patient_id'));

        // wwGrid: 필터된 전체를 그리드용 배열로 (클라이언트사이드)
        $gridData = $rows->map(function ($o) use ($nhisStatusLabels, $extras) {
            // 승인/거부 결과 텍스트
            if ($o->nhis_claim_status === 'approved') {
                $result = number_format((int) $o->nhis_reimbursement) . '원';
            } elseif ($o->nhis_claim_status === 'rejected') {
                $result = '거부';
            } else {
                $result = '-';
            }

            return [
                'id'           => $o->id,
                'order_no'     => $o->order_number ?? '',
                'patient'      => $o->patient?->name ?? '',
                'product'      => $o->product_name ?? '',
                'nhis_amount'  => (int) $o->nhis_amount,
                'patient_copay'=> (int) $o->patient_copay,
                'status'       => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                'nhis_status'  => $nhisStatusLabels[$o->nhis_claim_status] ?? $o->nhis_claim_status,
                'submitted_at' => $o->nhis_submitted_at?->format('Y-m-d H:i') ?? '',
                /* 왜 반려됐는가. 칸은 진작 있었는데 목록에 세우지 않아, 반려된 건을
                   다시 내려면 한 건씩 열어 봐야 했다(요청서 10쪽). */
                'reject_reason' => $o->nhis_rejection_reason ?? '',
                // 반려 뒤 어디까지 갔는가 — 다시 내는 일이 눈에서 사라지지 않게 한다
                'reject_stage'  => Order::CLAIM_REJECT_STAGES[$o->nhis_reject_stage] ?? '',
                'result'       => $result,
                // 주민번호를 갖고 있는지로 가른다 — 없으면 앞선 등록 절차가 남아 있다
                // 무엇이 빠졌는지까지 보여 준다. 「안 됨」만 알면 다시 열어 봐야 한다.
                'claim_ready_flag' => (bool) $o->claim_ready,
                'claim_missing' => $o->claim_missing ?? '',
                // 공단에 낼 건이 아니면 자료를 따질 것도 없다 — 색을 달리 쓴다
                'claim_na'     => ($o->prescription?->claim_agency ?? \App\Support\ClaimAgency::NHIS)
                                    !== \App\Support\ClaimAgency::NHIS,
                /* 청구 기한 — 출고일에서 두 주다(요청서 10쪽 「출고일자+2주」).
                   아직 청구하지 않은 건만 센다. 이미 낸 건에 남은 날을 적어 두면
                   무엇이 급한지가 묻힌다(요청서 11쪽 「미청구 경우 D-14 보이게」). */
                'claim_due'  => ($due = self::claimDue($o))?->format('Y-m-d') ?? '',
                'claim_dday' => self::dday($o, $due),
                'agency'       => \App\Support\ClaimAgency::LABELS[$o->prescription?->claim_agency] ?? '',
                /* 청구 칸이 무엇을 세울지 가른다 — 공단은 사이트에 옮겨 적고, 지자체는
                   등기로 부친다. 두 가지가 같은 단추를 쓰면 지자체 건에서 공단 서식이
                   열려 엉뚱한 곳에 옮겨 적는다(요청서 10쪽). */
                'agency_code'  => $o->prescription?->claim_agency ?? '',
                /* 등기로 부친 자취가 있는가 — 있으면 영수증을 받아 볼 수 있다 */
                'local_sent'   => $o->localDispatches()->exists(),
                /* 어느 지사ㆍ어느 부서로 보내는가. 「건강보험공단」만 적혀 있으면 결국
                   건마다 다시 찾아야 한다 — 골라 둔 것이 있으면 그것을 보여 준다. */
                'office'       => $o->prescription?->billingOffice?->displayName() ?? '',
                'office_tel'   => $o->prescription?->billingOffice?->tel ?? '',
                'office_fax'   => $o->prescription?->billingOffice?->fax ?? '',
                'office_who'   => trim(($o->prescription?->billingOffice?->manager_name ?? '')
                                    . ' ' . ($o->prescription?->billingOffice?->title ?? '')),

                // 네 화면이 함께 쓰는 칸 — 차례와 이름이 어디서나 같다
            ] + $extras->rx($o->prescription, $o->patient)
              + $extras->ww($o, $o->prescription, $o->patient)
              + $extras->of($o);
        })->values();

        $total = $gridData->count();

        // 지금 바로 청구할 수 있는 건수 — 상단 카드에 쓴다
        $readyCount = Order::whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->where('nhis_claim_status', 'pending')
            ->where('claim_ready', true)
            ->count();

        // 요약 카운트
        $counts = Order::whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->selectRaw('nhis_claim_status, count(*) as cnt')
            ->groupBy('nhis_claim_status')
            ->pluck('cnt', 'nhis_claim_status');

        // 이번 달 청구 합계
        $monthlyTotal = Order::where('nhis_claim_status', 'submitted')
            ->whereMonth('nhis_submitted_at', now()->month)
            ->whereYear('nhis_submitted_at', now()->year)
            ->sum('nhis_amount');

        $monthlyApproved = Order::where('nhis_claim_status', 'approved')
            ->whereMonth('nhis_approved_at', now()->month)
            ->whereYear('nhis_approved_at', now()->year)
            ->sum('nhis_reimbursement');

        return view('nhis.index', compact('gridData', 'total', 'counts', 'monthlyTotal', 'monthlyApproved', 'readyCount'));
    }

    /* e-Fax 로 청구를 보내던 기능은 걷어냈다. 공단 요양비 청구는 팩스로 하지 않는다 —
       공단 사이트(요양기관정보마당)에 직접 입력하고 서류를 업로드한다. 지자체는 등기로 보낸다.
       한 번도 쓰이지 않은 경로였다(nhis_fax_logs 0건).
       청구 결과 등록과 서류 미리보기는 그대로 쓴다. */

    /**
     * 청구 결과 등록 (공단 회신 처리).
     *
     * 담당자가 공단 사이트에서 결과를 확인하고 옮겨 적는다. 예전에는 팩스 발송 이력에
     * 결과를 매달았는데, 청구를 팩스로 보내지 않으므로 주문에 바로 적는다.
     */
    public function recordResult(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            // 보류는 공단이 판단을 미룬 것이다(2026-08-31 회신)
            'nhis_result'     => 'required|in:approved,rejected,partial,on_hold',
            'reject_stage'    => ['nullable', Rule::in(array_keys(Order::CLAIM_REJECT_STAGES))],
            'approved_amount' => 'nullable|numeric|min:0',
            'nhis_message'    => 'nullable|string|max:500',
        ]);

        $status = $data['nhis_result'] === 'partial' ? 'approved' : $data['nhis_result'];

        $order->update([
            'nhis_claim_status'  => $status,
            /* 보류는 아직 결론이 아니다 — 승인일을 찍으면 정산이 그 날을 받은 날로 읽는다 */
            'nhis_approved_at'   => $status === 'on_hold' ? $order->nhis_approved_at : now(),
            'nhis_reimbursement' => $status === 'on_hold'
                                        ? $order->nhis_reimbursement
                                        : ($data['approved_amount'] ?? $order->nhis_amount),
            // 사유는 반려ㆍ보류일 때만 남긴다 — 승인 건에 남아 있으면 읽는 사람이 헷갈린다
            'nhis_rejection_reason' => in_array($status, ['rejected', 'on_hold'], true)
                                        ? ($data['nhis_message'] ?? null) : null,
            // 반려 뒤의 걸음은 반려일 때만 뜻이 있다
            'nhis_reject_stage'  => $status === 'rejected' ? ($data['reject_stage'] ?? null) : null,
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('공단 청구 결과 등록: ' . $data['nhis_result']
                  . ($data['nhis_message'] ? ' — ' . $data['nhis_message'] : ''));

        return response()->json([
            'success' => true,
            'message' => '청구 결과가 등록되었습니다.',
        ]);
    }

    /** 청구서 미리보기 — 공단 사이트에 옮겨 적을 내용을 눈으로 확인한다 */
    public function previewDocument(Order $order): \Illuminate\Http\Response
    {
        $order->load(['patient', 'prescription']);

        return response($this->buildClaimDocument($order), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** 공단에 옮겨 적을 값을 한 장으로 모은다. 예전에는 팩스 본문이었다. */
    private function buildClaimDocument(Order $order): string
    {
        $inst    = config('nhis.institution');
        $patient = $order->patient;
        $rx      = $order->prescription;

        return implode("\n", [
            '═══════════════════════════════════════════════',
            '         건강보험 요양비 청구 내용',
            '═══════════════════════════════════════════════',
            '',
            '■ 청구 기관 정보',
            "  기관명      : {$inst['name']}",
            "  요양기관기호: {$inst['code']}",
            "  사업자번호  : {$inst['biz_no']}",
            '',
            '■ 환자 정보',
            "  환자명      : {$patient?->name}",
            "  주민번호    : {$patient?->masked_resident_no}",
            "  건강보험번호: {$patient?->health_insurance_no}",
            '',
            '■ 처방 정보',
            "  처방전번호  : {$rx?->rx_number}",
            "  처방일      : {$rx?->issued_date?->format('Y-m-d')}",
            "  병원명      : {$rx?->hospital_name}",
            "  담당의사    : {$rx?->doctor_name}",
            "  상병명      : {$rx?->disease_name}",
            '',
            '■ 급여 품목',
            "  제품명      : {$order->product_name}",
            "  제품코드    : {$order->product_code}",
            "  수량        : {$order->quantity}",
            '  단가        : ' . number_format((float) $order->unit_price) . '원',
            '',
            '■ 청구 금액',
            '  기관 부담금  : ' . number_format((float) $order->nhis_amount) . '원',
            '  본인 부담금  : ' . number_format((float) $order->patient_copay) . '원',
            '  합계         : ' . number_format((float) $order->nhis_amount + (float) $order->patient_copay) . '원',
            '',
            '■ 주문 정보',
            "  주문번호    : {$order->order_number}",
            "  배송주소    : {$order->shipping_address}",
            "  배송완료일  : {$order->delivered_at?->format('Y-m-d')}",
            '',
            '───────────────────────────────────────────────',
            '  출력일시    : ' . now()->format('Y-m-d H:i'),
            '═══════════════════════════════════════════════',
        ]);
    }


    /**
     * 언제까지 청구해야 하는가 — 출고일 + 2주 (요청서 10쪽).
     *
     * 출고일은 창고가 알려 주는 값이라 2026-08-31 부터 쌓인다. 그 전 건에는 없어
     * 배송이 끝난 날로 갈음한다 — 둘 다 없으면 셀 수가 없다.
     */
    private static function claimDue(Order $o): ?\Carbon\Carbon
    {
        $base = $o->shipped_at ?? $o->delivered_at;

        return $base ? \Carbon\Carbon::parse($base)->startOfDay()->addDays(14) : null;
    }

    /**
     * 며칠 남았는가 — 아직 청구하지 않은 건만.
     *
     * 이미 낸 건에 남은 날을 적어 두면 무엇이 급한지가 묻힌다. 넘긴 건은 며칠 넘겼는지를
     * 적는다 — 「지났다」만 알면 얼마나 다급한지가 갈리지 않는다.
     */
    private static function dday(Order $o, ?\Carbon\Carbon $due): string
    {
        if (!$due || $o->nhis_claim_status !== 'pending') {
            return '';
        }

        $left = today()->diffInDays($due, false);

        return $left < 0 ? abs($left) . '일 초과' : 'D-' . $left;
    }

}
