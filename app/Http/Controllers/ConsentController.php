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

            // 요양비위임장(원본 오버레이)도 첨부문서에 자동 추가
            $consent->loadMissing('prescription');
            if ($consent->prescription) {
                $this->saveDelegationDocument($consent->prescription);
            }
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
     * 어드민: 요양비 지급청구 위임장(별지 제19호의7서식) PDF 다운로드
     * — 환자 SMS 서명을 서명란에 삽입. 준요양기관/수령계좌/위임기간은 추후 자동채움 예정.
     */
    public function downloadDelegationPdf(Prescription $prescription)
    {
        \App\Models\DelegationSetting::applyToConfig();  // DB 설정 → config('delegation.*')

        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->firstOrFail();

        $consent->loadMissing('prescription.patient');

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

        $html = view('consent.delegation_pdf', compact('consent'))->render();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
        $filename = '요양비지급청구위임장_' . $consent->patient_name . '_' . $mobile . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    /**
     * 어드민: 원본 양식 PDF(별지 제19호의7)에 환자 서명을 오버레이해 다운로드
     * — FPDI로 관공서 원본 PDF를 그대로 불러와 서명란 좌표에 서명 이미지를 스탬프.
     */
    public function downloadDelegationOverlayPdf(Prescription $prescription)
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest()
            ->firstOrFail();

        $pdfData = $this->buildDelegationOverlayPdf($consent);

        $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
        $filename = '요양비지급청구위임장_' . $consent->patient_name . '_' . $mobile . '.pdf';

        return response($pdfData, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    /**
     * 관리자: 현재 위임장 설정으로 요양비위임장을 재생성해 첨부문서 갱신 (설정 수시 반영 버튼).
     */
    public function regenerateDelegation(Prescription $prescription): JsonResponse
    {
        $doc = $this->saveDelegationDocument($prescription);

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => '서명된 위임동의가 없어 요양비위임장을 생성할 수 없습니다.',
            ], 422);
        }

        return response()->json([
            'success'  => true,
            'message'  => '현재 설정으로 요양비위임장을 재생성해 첨부했습니다.',
            'doc_id'   => $doc->id,
            'filename' => $doc->original_filename,
        ]);
    }

    /**
     * 원본 위임장 PDF 오버레이 생성 → PDF 바이너리 반환 (다운로드·자동첨부 공용).
     * 현재 DB 위임장 설정을 적용한다.
     */
    private function buildDelegationOverlayPdf(PrescriptionConsent $consent): string
    {
        \App\Models\DelegationSetting::applyToConfig();  // DB 설정 → config('delegation.*')

        $templatePath = resource_path('pdf/delegation_form.pdf');
        if (!is_file($templatePath)) {
            throw new \RuntimeException('위임장 원본 양식 파일을 찾을 수 없습니다.');
        }

        // 서명 data URL → 바이너리 PNG
        $raw = $consent->signature_data;
        if (preg_match('#^data:image/\w+;base64,(.+)$#s', $raw, $m)) {
            $raw = $m[1];
        }
        $imgData = base64_decode($raw, true);
        if ($imgData === false) {
            throw new \RuntimeException('서명 이미지를 해석할 수 없습니다.');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($templatePath);

        // 텍스트 필드용 한글 폰트 등록 (최초 1회 생성 후 캐시)
        $fontName = \TCPDF_FONTS::addTTFfont(storage_path('fonts/NanumGothic.ttf'), 'TrueTypeUnicode', '', 32);

        $sigX = (float) config('delegation.signature.x', 164);
        $sigY = (float) config('delegation.signature.y', 266);
        $sigW = (float) config('delegation.signature.w', 28);

        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

            // 1페이지에만 텍스트 필드 + 서명 오버레이
            if ($p === 1) {
                $this->stampDelegationFields($pdf, $consent, $fontName);

                // 서명 오버레이 ('@': 원본 이미지 데이터 직접 사용, 알파채널 PNG는 GD로 처리)
                $pdf->Image('@' . $imgData, $sigX, $sigY, $sigW, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

                // 서명일(년/월/일)은 서명 위에 얹어 가독성 확보
                $sd = $consent->responded_at ?? now();
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont($fontName, '', 7);
                $pdf->Text(151, 270, $sd->format('Y'));
                $pdf->Text(167, 270, $sd->format('n'));
                $pdf->Text(181, 270, $sd->format('j'));
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * 요양비위임장 PDF를 생성해 첨부문서(type=delegation)로 저장·갱신.
     * 서명 완료 시 자동 호출 + 설정 변경 시 재생성 버튼에서 호출.
     * 서명이 없으면 null 반환.
     */
    public function saveDelegationDocument(Prescription $prescription): ?PrescriptionDocument
    {
        try {
            $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
                ->where('status', 'agreed')
                ->whereNotNull('signature_data')
                ->latest()
                ->first();

            if (!$consent) {
                return null;
            }

            $pdfData = $this->buildDelegationOverlayPdf($consent);

            $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
            $filename = '요양비위임장_' . $consent->patient_name . '_' . $mobile . '_' . now()->format('Ymd') . '.pdf';
            $path     = 'delegations/' . $prescription->id . '_' . now()->format('YmdHis') . '.pdf';

            Storage::put($path, $pdfData);

            // 기존 위임장 문서 교체 (파일·레코드 정리)
            $olds = PrescriptionDocument::where('prescription_id', $prescription->id)
                ->where('type', 'delegation')->get();
            foreach ($olds as $old) {
                if ($old->file_path && Storage::exists($old->file_path)) {
                    Storage::delete($old->file_path);
                }
                $old->delete();
            }

            return PrescriptionDocument::create([
                'prescription_id'   => $prescription->id,
                'patient_id'        => $prescription->patient_id,
                'created_by'        => Auth::id(),
                'type'              => 'delegation',
                'file_path'         => $path,
                'original_filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('요양비위임장 자동첨부 실패: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 원본 위임장 PDF에 텍스트 필드 자동채움 (좌표는 A4 mm 기준).
     * ①위임인(성명·주민번호·전화) ②준요양기관 ③수령계좌 ④자가도뇨 체크 ⑤위임기간.
     * 준요양기관·수령계좌 값은 config/delegation.php(.env) 에서 온다.
     */
    private function stampDelegationFields(\setasign\Fpdi\Tcpdf\Fpdi $pdf, PrescriptionConsent $consent, string $fontName): void
    {
        $patient = $consent->prescription?->patient;
        $prov    = config('delegation.provider', []);
        $acct    = config('delegation.account', []);
        $sd      = $consent->responded_at ?? now();
        $py      = min(5, max(1, (int) config('delegation.period_years', 5)));
        $ed      = $sd->copy()->addYears($py);

        $pdf->SetTextColor(0, 0, 0);
        $put = function (float $x, float $y, ?string $t, float $size = 8) use ($pdf, $fontName): void {
            $t = trim((string) $t);
            if ($t === '') {
                return;
            }
            $pdf->SetFont($fontName, '', $size);
            $pdf->Text($x, $y, $t);
        };

        // ① 위임인
        $put(133, 52, $consent->patient_name ?: $patient?->name);
        $put(133, 59, $patient?->resident_no);
        $put(80, 90, $consent->patient_mobile ?: $patient?->mobile);

        // ② 준요양기관
        $put(78, 116, $prov['name']   ?? '');
        $put(78, 125, $prov['biz_no'] ?? '');
        $put(78, 135, $prov['ceo']    ?? '');
        $put(78, 144, $prov['phone']  ?? '');

        // ③ 요양비 수령계좌
        $put(100, 156, $acct['receiver'] ?? '');
        $put(140, 163, $acct['bank']     ?? '');
        $put(140, 171, $acct['holder']   ?? '');
        $put(140, 179, $acct['number']   ?? '');

        // ④ 위임사항 — 4) 자가도뇨 소모성 재료 체크
        // 폰트 글리프(✔) 대신 벡터 선으로 직접 그려 깨짐 방지 (작게, 왼쪽으로)
        $pdf->SetLineStyle(['width' => 0.4, 'cap' => 'round', 'join' => 'round', 'color' => [0, 0, 0]]);
        $pdf->Line(60.6, 200.9, 61.8, 202.2);
        $pdf->Line(61.8, 202.2, 64.1, 199.0);

        // ⑤ 위임기간 (서명일부터 N년) — 인쇄된 년/월/일 글자와 겹치지 않게 작게·여백 배치
        $put(54, 246,  $sd->format('Y'), 7);
        $put(73, 246,  $sd->format('n'), 7);
        $put(83, 246,  $sd->format('j'), 7);
        $put(108, 246, $ed->format('Y'), 7);
        $put(128, 246, $ed->format('n'), 7);
        $put(139, 246, $ed->format('j'), 7);
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
