<?php
// app/Http/Controllers/NhisController.php

namespace App\Http\Controllers;

use App\Models\NhisFaxLog;
use App\Models\Order;
use App\Services\NhisEFaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NhisController extends Controller
{
    public function __construct(protected NhisEFaxService $faxService) {}

    // ── 목록 ─────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        // nhis_fax_logs 테이블 존재 여부 확인 (마이그레이션 미실행 대비)
        $faxTableExists = \Illuminate\Support\Facades\Schema::hasTable('nhis_fax_logs');
        $hasFaxLogCol   = $faxTableExists && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'latest_fax_log_id');

        $with = ['patient', 'prescription'];
        if ($hasFaxLogCol) {
            $with[] = 'latestFaxLog';
        }

        $query = Order::with($with)
            ->whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->latest();

        // NHIS 청구 상태 필터
        if ($request->filled('nhis_status')) {
            $query->where('nhis_claim_status', $request->nhis_status);
        }

        // 검색 (환자명, 주문번호)
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        // 날짜 필터 (배송 완료일)
        if ($request->filled('date_from')) {
            $query->where('delivered_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('delivered_at', '<=', $request->date_to . ' 23:59:59');
        }

        $orders = $query->paginate(25)->withQueryString();

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

        return view('nhis.index', compact('orders', 'counts', 'monthlyTotal', 'monthlyApproved', 'faxTableExists'));
    }

    // ── 단건 e-Fax 청구 ─────────────────────────────────────────
    public function sendFax(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('nhis_fax_logs')) {
            return response()->json(['success' => false, 'message' => 'DB 마이그레이션을 먼저 실행해주세요. (nhis_fax_logs_SQL.txt)'], 503);
        }

        if (!in_array($order->status, ['delivered', 'shipping', 'confirmed'])) {
            return response()->json(['success' => false, 'message' => '배송 확정 후 청구 가능합니다.'], 422);
        }

        if ($order->nhis_claim_status === 'approved') {
            return response()->json(['success' => false, 'message' => '이미 승인된 청구입니다.'], 409);
        }

        try {
            $log = $this->faxService->send($order);

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("NHIS e-Fax 청구 송신 ({$log->reference_no})");

            return response()->json([
                'success'      => true,
                'message'      => 'e-Fax 청구가 전송되었습니다.',
                'reference_no' => $log->reference_no,
                'sent_at'      => $log->sent_at?->format('Y-m-d H:i:s'),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'e-Fax 전송 실패: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── 일괄 e-Fax 청구 ─────────────────────────────────────────
    public function bulkSendFax(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['order_ids' => 'required|array|min:1|max:50']);

        $orders = Order::whereIn('id', $request->order_ids)
            ->where('nhis_claim_status', 'pending')
            ->whereIn('status', ['delivered', 'shipping', 'confirmed'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['success' => false, 'message' => '청구 가능한 주문이 없습니다.'], 422);
        }

        $results = $this->faxService->sendBulk($orders);

        $successCount = count($results['success']);
        $failCount    = count($results['failed']);

        if ($successCount > 0) {
            activity()->causedBy(Auth::user())
                ->log("NHIS e-Fax 일괄 청구 {$successCount}건 송신" . ($failCount ? ", {$failCount}건 실패" : ''));
        }

        return response()->json([
            'success'       => $successCount > 0,
            'message'       => "{$successCount}건 전송 완료" . ($failCount ? ", {$failCount}건 실패" : ''),
            'success_count' => $successCount,
            'fail_count'    => $failCount,
            'failed'        => collect($results['failed'])->map(fn($f) => [
                'order_number' => $f['order']->order_number,
                'error'        => $f['error'],
            ])->all(),
        ]);
    }

    // ── 청구 결과 등록 (공단 회신 처리) ─────────────────────────
    public function recordResult(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'nhis_result'     => 'required|in:approved,rejected,partial',
            'approved_amount' => 'nullable|numeric|min:0',
            'nhis_message'    => 'nullable|string|max:500',
            'log_id'          => 'nullable|exists:nhis_fax_logs,id',
        ]);

        // 최신 팩스 로그 또는 지정 로그
        $log = $data['log_id']
            ? NhisFaxLog::find($data['log_id'])
            : NhisFaxLog::where('order_id', $order->id)->where('status', 'sent')->latest()->first();

        if (!$log) {
            return response()->json(['success' => false, 'message' => '팩스 발송 내역이 없습니다.'], 422);
        }

        $this->faxService->recordNhisResult(
            $log,
            $data['nhis_result'],
            $data['approved_amount'] ?? null,
            $data['nhis_message'] ?? null,
        );

        // 거부 사유 저장
        if ($data['nhis_result'] === 'rejected' && !empty($data['nhis_message'])) {
            $order->update(['nhis_rejection_reason' => $data['nhis_message']]);
        }

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('NHIS 청구 결과 등록: ' . $data['nhis_result']);

        return response()->json([
            'success' => true,
            'message' => 'NHIS 청구 결과가 등록되었습니다.',
        ]);
    }

    // ── 청구서 미리보기 ─────────────────────────────────────────
    public function previewDocument(Order $order): \Illuminate\Http\Response
    {
        $order->load(['patient', 'prescription']);
        $document = $this->faxService->buildClaimDocument($order);

        return response($document, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ── 팩스 발송 이력 ─────────────────────────────────────────
    public function faxLogs(Order $order): \Illuminate\Http\JsonResponse
    {
        $logs = NhisFaxLog::where('order_id', $order->id)
            ->with('sender')
            ->latest()
            ->get()
            ->map(fn($l) => [
                'id'           => $l->id,
                'status'       => $l->status,
                'status_label' => $l->status_label,
                'nhis_result'  => $l->nhis_result,
                'nhis_result_label' => $l->nhis_result_label,
                'reference_no' => $l->reference_no,
                'fax_number'   => $l->fax_number,
                'nhis_amount'  => $l->nhis_amount,
                'sent_at'      => $l->sent_at?->format('Y-m-d H:i'),
                'nhis_result_at' => $l->nhis_result_at?->format('Y-m-d H:i'),
                'approved_amount'=> $l->approved_amount,
                'nhis_message' => $l->nhis_message,
                'error_message'=> $l->error_message,
                'sender_name'  => $l->sender?->name,
            ]);

        return response()->json(['success' => true, 'logs' => $logs]);
    }

    // ── e-Fax 콜백 (팩스 서비스 → 시스템) ─────────────────────
    public function faxCallback(Request $request): \Illuminate\Http\JsonResponse
    {
        $refNo  = $request->input('faxId') ?? $request->input('reference_no');
        $status = $request->input('status');   // 서비스별 상이

        if (!$refNo) {
            return response()->json(['ok' => false], 400);
        }

        $log = NhisFaxLog::where('reference_no', $refNo)->first();
        if (!$log) {
            return response()->json(['ok' => false, 'message' => 'not found'], 404);
        }

        // 전송 성공 여부 매핑 (HiFaxKorea 기준, 실제 연동 시 조정)
        $isSent   = in_array($status, ['success', 'sent', 'delivered', 'OK', '0']);
        $isFailed = in_array($status, ['failed', 'error', 'FAIL']);

        if ($isSent && $log->status !== 'sent') {
            $log->update([
                'status'       => 'sent',
                'confirmed_at' => now(),
                'raw_payload'  => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ]);
        } elseif ($isFailed) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $request->input('message') ?? '팩스 전송 실패',
                'retry_count'   => $log->retry_count + 1,
                'raw_payload'   => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
