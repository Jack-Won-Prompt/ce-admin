<?php
// app/Http/Controllers/ConsentController.php

namespace App\Http\Controllers;

use App\Events\ConsentSubmitted;
use App\Models\Prescription;
use App\Models\PrescriptionConsent;
use App\Models\PrescriptionDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ConsentController extends Controller
{
    /**
     * 공개 페이지: 위임동의 서명 화면 (로그인 불필요)
     */
    public function show(string $token): View
    {
        $consent = PrescriptionConsent::where('token', $token)->firstOrFail();

        // 이미 처리된 경우
        if (in_array($consent->status, ['agreed', 'declined'])) {
            return view('consent.done', compact('consent'));
        }

        // 만료 처리
        if ($consent->expires_at->isPast()) {
            if ($consent->status === 'pending') {
                $consent->update(['status' => 'expired']);
            }
            return view('consent.expired', compact('consent'));
        }

        return view('consent.sign', compact('consent'));
    }

    /**
     * 공개 POST: 동의 / 거절 제출
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $consent = PrescriptionConsent::where('token', $token)->firstOrFail();

        if (!$consent->isPending()) {
            return response()->json([
                'success' => false,
                'message' => '이미 처리되었거나 만료된 요청입니다.',
            ], 422);
        }

        $request->validate([
            'action'    => 'required|in:agreed,declined',
            'signature' => 'nullable|string|max:500000',
        ]);

        $consent->update([
            'status'         => $request->action,
            'signature_data' => $request->action === 'agreed' ? $request->input('signature') : null,
            'responded_at'   => now(),
        ]);

        // 동의 완료 시 PDF 자동 생성
        if ($request->action === 'agreed') {
            $this->generateConsentPdf($consent);
        }

        // 관리자 전체에게 실시간 알림 브로드캐스트
        try {
            broadcast(new ConsentSubmitted($consent));
        } catch (\Throwable $e) {
            \Log::warning('ConsentSubmitted 브로드캐스트 실패: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'action'  => $request->action,
        ]);
    }

    /**
     * 어드민: 처방전에 연결된 동의 현황 조회 (AJAX)
     */
    public function statusCheck(Request $request, Prescription $prescription): JsonResponse
    {
        $latest = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->latest()
            ->first();

        if (!$latest) {
            return response()->json(['exists' => false]);
        }

        // pending 이면 실시간으로 만료 여부 체크
        if ($latest->status === 'pending' && $latest->expires_at->isPast()) {
            $latest->update(['status' => 'expired']);
        }

        return response()->json([
            'exists'          => true,
            'status'          => $latest->status,
            'status_label'    => $latest->statusLabel(),
            'responded_at'    => $latest->responded_at?->format('Y-m-d H:i:s'),
            'expires_at'      => $latest->expires_at->format('Y-m-d H:i'),
            'remaining_min'   => $latest->remainingMinutes(),
            'has_signature'   => !empty($latest->signature_data),
            'patient_name'    => $latest->patient_name,
            'patient_mobile'  => $latest->patient_mobile,
            'signature_data'  => $latest->status === 'agreed' ? $latest->signature_data : null,
            'pdf_url'         => ($latest->status === 'agreed' && $latest->pdf_path)
                                  ? route('prescriptions.consentPdf', $prescription)
                                  : null,
        ]);
    }

    /**
     * 어드민: 위임동의 PDF 다운로드
     */
    public function downloadPdf(Prescription $prescription)
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->firstOrFail();

        $this->ensureNanumGothicVariantsRegistered();

        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->setIsFontSubsettingEnabled(false);
        $options->setDefaultFont('NanumGothic');
        $dompdf = new \Dompdf\Dompdf($options);

        $html = view('consent.pdf', compact('consent'))->render();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
        $filename = '위임동의서_' . $consent->patient_name . '_' . $mobile . '_' . $consent->responded_at?->format('Ymd') . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    /**
     * 동의 완료 시 PDF 생성 및 스토리지 저장
     */
    private function generateConsentPdf(PrescriptionConsent $consent): void
    {
        try {
            $consent->loadMissing('prescription');
            $this->ensureNanumGothicVariantsRegistered();

            $pdf  = Pdf::loadView('consent.pdf', compact('consent'))
                       ->setPaper('a4', 'portrait');
            $path = 'consents/' . $consent->id . '_' . $consent->token . '.pdf';

            Storage::put($path, $pdf->output());
            $consent->update(['pdf_path' => $path]);

            $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
            $filename = '위임동의서_' . $consent->patient_name . '_' . $mobile . '_' . $consent->responded_at?->format('Ymd') . '.pdf';

            PrescriptionDocument::create([
                'prescription_id'   => $consent->prescription_id,
                'patient_id'        => $consent->prescription?->patient_id,
                'created_by'        => Auth::id(),
                'type'              => 'consent',
                'file_path'         => $path,
                'original_filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('위임동의 PDF 생성 실패: ' . $e->getMessage());
        }
    }

    private function ensureNanumGothicVariantsRegistered(): void
    {
        $path = storage_path('fonts/installed-fonts.json');
        if (!file_exists($path)) {
            return;
        }
        $fonts = json_decode(file_get_contents($path), true) ?? [];
        if (!isset($fonts['nanumgothic']['normal'])) {
            return;
        }
        $normalKey = $fonts['nanumgothic']['normal'];
        $changed   = false;
        foreach (['bold', 'italic', 'bold_italic'] as $variant) {
            if (!isset($fonts['nanumgothic'][$variant])) {
                $fonts['nanumgothic'][$variant] = $normalKey;
                $changed = true;
            }
        }
        if ($changed) {
            file_put_contents($path, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
