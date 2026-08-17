<?php
// app/Http/Controllers/NhisController.php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NhisController extends Controller
{
    // ── 목록 ─────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        $query = Order::with(['patient', 'prescription'])
            ->whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->latest();

        // NHIS 청구 상태 필터
        if ($request->filled('nhis_status')) {
            $query->where('nhis_claim_status', $request->nhis_status);
        }

        /* 신환·구환은 주민번호 보유 여부로 갈리므로 조건도 그 컬럼으로 건다.
           환자에 없으면 처방전 OCR 값까지 보는 것이 화면 표시와 같은 규칙이다. */
        if ($request->filled('patient_type')) {
            $hasRrn = fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p->whereNotNull('resident_no_hash'))
                ->orWhereHas('prescription', fn ($p) => $p->whereNotNull('resident_no_ocr_enc'));

            $request->patient_type === 'existing'
                ? $query->where($hasRrn)
                : $query->whereNot($hasRrn);
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

        // 검색 (환자명, 주문번호)
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

        // NHIS 청구 상태 라벨 (배지 → 텍스트)
        $nhisStatusLabels = [
            'pending'   => '미청구',
            'submitted' => '청구완료',
            'approved'  => '승인',
            'rejected'  => '거부',
        ];

        // wwGrid: 필터된 전체를 그리드용 배열로 (클라이언트사이드)
        $gridData = $query->get()->map(function ($o) use ($nhisStatusLabels) {
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
                'result'       => $result,
                // 주민번호를 갖고 있는지로 가른다 — 없으면 앞선 등록 절차가 남아 있다
                'patient_type' => $o->patientTypeLabel(),
                // 무엇이 빠졌는지까지 보여 준다. 「안 됨」만 알면 다시 열어 봐야 한다.
                'claim_ready'  => (bool) $o->claim_ready,
                'claim_missing' => $o->claim_missing ?? '',
                // 공단에 낼 건이 아니면 자료를 따질 것도 없다 — 색을 달리 쓴다
                'claim_na'     => ($o->prescription?->claim_agency ?? \App\Support\ClaimAgency::NHIS)
                                    !== \App\Support\ClaimAgency::NHIS,
                'agency'       => \App\Support\ClaimAgency::LABELS[$o->prescription?->claim_agency] ?? '',
            ];
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
            'nhis_result'     => 'required|in:approved,rejected,partial',
            'approved_amount' => 'nullable|numeric|min:0',
            'nhis_message'    => 'nullable|string|max:500',
        ]);

        $order->update([
            'nhis_claim_status'     => $data['nhis_result'] === 'partial' ? 'approved' : $data['nhis_result'],
            'nhis_approved_at'      => now(),
            'nhis_reimbursement'    => $data['approved_amount'] ?? $order->nhis_amount,
            // 거부 사유는 거부일 때만 남긴다 — 승인 건에 남아 있으면 나중에 읽는 사람이 헷갈린다
            'nhis_rejection_reason' => $data['nhis_result'] === 'rejected' ? ($data['nhis_message'] ?? null) : null,
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

}
