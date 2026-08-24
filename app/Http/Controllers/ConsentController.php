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

        // 자격증명·정책은 관리자 설정(DB)에서 오며, 서비스 생성 시 config('nice.*')에 반영된다.
        $nice        = app(\App\Services\Nice\NiceIdentityService::class);
        $niceEnabled = $nice->enabled();
        $niceEnforce = $nice->enforce();
        $verified    = $consent->isIdentityVerified();

        return view('consent.sign', compact('consent', 'niceEnabled', 'niceEnforce', 'verified'));
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
            'action'             => 'required|in:agreed,declined',
            'signature'          => 'nullable|string|max:500000',
            // 미성년자 — 법정대리인
            'guardian_name'      => 'nullable|string|max:50',
            'guardian_relation'  => 'nullable|string|max:50',
            'guardian_birth'     => 'nullable|date',
            'guardian_phone'     => 'nullable|string|max:40',
            'guardian_signature' => 'nullable|string|max:500000',
            'guardian_id'        => 'nullable|string|max:8000000',
        ]);

        /* 미성년자는 혼자 위임할 수 없다. 화면에서도 막지만, 화면을 거치지 않고 들어오는
           요청이 있으므로 서버에서 다시 본다. */
        if ($request->action === 'agreed' && $consent->is_minor) {
            foreach (['guardian_name' => '보호자 성명', 'guardian_relation' => '보호자 관계',
                      'guardian_birth' => '보호자 생년월일',
                      'guardian_signature' => '보호자 서명', 'guardian_id' => '보호자 신분증'] as $k => $label) {
                if (!trim((string) $request->input($k))) {
                    return response()->json(['success' => false, 'message' => "{$label}이(가) 필요합니다."], 422);
                }
            }
        }

        // NICE 본인확인 강제: 동의 서명은 본인확인 완료 후에만 허용
        if ($request->action === 'agreed'
            && app(\App\Services\Nice\NiceIdentityService::class)->enforce()
            && !$consent->isIdentityVerified()
        ) {
            return response()->json([
                'success' => false,
                'message' => '본인확인을 먼저 완료해 주세요.',
            ], 422);
        }

        $payload = [
            'status'         => $request->action,
            'signature_data' => $request->action === 'agreed' ? $request->input('signature') : null,
            'responded_at'   => now(),
        ];

        if ($request->action === 'agreed' && $consent->is_minor) {
            $payload['guardian_name']           = trim((string) $request->input('guardian_name'));
            $payload['guardian_relation']       = trim((string) $request->input('guardian_relation'));
            $payload['guardian_birth_date']     = $request->input('guardian_birth') ?: null;
            $payload['guardian_phone']          = trim((string) $request->input('guardian_phone')) ?: null;
            $payload['guardian_signature_data'] = $request->input('guardian_signature');
            // 신분증은 본문에 담지 않고 파일로 둔다. 공개되지 않는 디스크에 쓴다.
            [$payload['guardian_id_path'], $payload['guardian_id_mime']] =
                $this->storeGuardianId($consent, (string) $request->input('guardian_id'));
        }

        $consent->update($payload);

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
     * 보호자 신분증을 파일로 저장한다.
     *
     * 신분증은 주민등록증·운전면허증이라 그 자체가 고유식별정보를 담는다. DB 본문에 두지 않고
     * 공개되지 않는 디스크에 쓰고 경로만 남긴다(처방전 이미지와 같은 취급).
     *
     * @return array{0: ?string, 1: ?string} [경로, mime]
     */
    private function storeGuardianId(PrescriptionConsent $consent, string $dataUrl): array
    {
        if (!preg_match('#^data:(image/[\w.+-]+);base64,(.+)$#s', $dataUrl, $m)) {
            return [null, null];
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '') {
            return [null, null];
        }

        $ext  = match ($m[1]) { 'image/png' => 'png', 'image/heic', 'image/heif' => 'heic', default => 'jpg' };
        $path = 'consents/guardian-id/' . $consent->id . '_' . \Illuminate\Support\Str::random(16) . '.' . $ext;

        \Illuminate\Support\Facades\Storage::put($path, $bytes);

        return [$path, $m[1]];
    }

    /**
     * 공개 POST: NICE 표준창 호출 파라미터 발급 (서명 페이지 fetch)
     * 자격증명/암호화는 모두 서버에서 처리하고, 클라이언트에는 표준창 폼 값만 전달한다.
     */
    public function niceStart(string $token): JsonResponse
    {
        $consent = PrescriptionConsent::where('token', $token)->firstOrFail();

        if (!$consent->isPending()) {
            return response()->json(['success' => false, 'message' => '이미 처리되었거나 만료된 요청입니다.'], 422);
        }

        $nice = app(\App\Services\Nice\NiceIdentityService::class);
        if (!$nice->enabled()) {
            return response()->json(['success' => false, 'message' => '본인확인 서비스가 아직 설정되지 않았습니다.'], 503);
        }

        try {
            $returnUrl = route('consent.nice.callback', ['token' => $consent->token]);
            $params    = $nice->startVerification($consent, $returnUrl);
            return response()->json(['success' => true] + $params);
        } catch (\Throwable $e) {
            \Log::error('NICE startVerification 실패', ['token' => $token, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '본인확인 요청 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 공개 GET/POST: NICE 표준창 returnurl 콜백.
     * enc_data 복호화 → 처방전 환자(이름/생년월일) 매칭 → 결과 저장.
     * 결과는 팝업 뷰에서 opener(서명 페이지)로 postMessage 후 창을 닫는다.
     */
    public function niceCallback(Request $request, string $token): View
    {
        $consent = PrescriptionConsent::where('token', $token)->firstOrFail();
        $nice    = app(\App\Services\Nice\NiceIdentityService::class);

        // 이미 처리·만료된 건에는 본인확인 결과를 붙이지 않는다.
        if (!$consent->isPending()) {
            return view('consent.nice_callback', [
                'ok'      => false,
                'message' => '이미 처리되었거나 만료된 요청입니다.',
            ]);
        }

        try {
            $result = $nice->handleCallback($consent, $request->all());
        } catch (\Throwable $e) {
            \Log::warning('NICE 콜백 처리 실패', ['token' => $token, 'error' => $e->getMessage()]);
            return view('consent.nice_callback', ['ok' => false, 'message' => $e->getMessage()]);
        }

        // 처방전 환자 본인 매칭
        $match = $this->matchesPatient($consent, $result);
        if (!$match['ok']) {
            return view('consent.nice_callback', ['ok' => false, 'message' => $match['message']]);
        }

        $consent->update([
            'nice_verified_at' => now(),
            'nice_name'        => $result['name'],
            'nice_birthdate'   => $result['birthdate'] ?: null,
            'nice_gender'      => $result['gender'] ?: null,
            'nice_nation'      => $result['nation'] ?: null,
            'nice_mobileco'    => $result['mobileco'] ?: null,
            'nice_mobile'      => $result['mobile'] ?: null,
            'nice_authtype'    => $result['authtype'] ?: null,
            'nice_response_no' => $result['response_no'] ?: null,
            'nice_ci'          => $result['ci'] ?: null,
            'nice_di'          => $result['di'] ?: null,
        ]);

        return view('consent.nice_callback', ['ok' => true, 'name' => $result['name']]);
    }

    /**
     * NICE 본인확인 결과가 처방전 환자 본인과 일치하는지 검증.
     * 정책: 이름 일치 필수 + (환자 생년월일이 있으면) 생년월일 일치 필수.
     *
     * @return array{ok:bool, message:string}
     */
    private function matchesPatient(PrescriptionConsent $consent, array $result): array
    {
        $consent->loadMissing('prescription.patient');
        $patient = $consent->prescription?->patient;

        $expectedName  = $patient?->name ?? $consent->patient_name;
        $expectedBirth = $this->patientBirthYmd($consent);   // YYYYMMDD or null

        $norm = fn ($s) => preg_replace('/\s+/', '', (string) $s);

        if (config('nice.match.require_name', true)) {
            if ($norm($result['name']) === '' || $norm($result['name']) !== $norm($expectedName)) {
                return ['ok' => false, 'message' => '본인확인 정보가 처방전 환자와 일치하지 않습니다. (이름)'];
            }
        }

        if (config('nice.match.require_birth', true) && $expectedBirth) {
            if ($result['birthdate'] === '' || $result['birthdate'] !== $expectedBirth) {
                return ['ok' => false, 'message' => '본인확인 정보가 처방전 환자와 일치하지 않습니다. (생년월일)'];
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    /** 처방전 환자 생년월일을 YYYYMMDD 로 도출(환자 레코드 우선, 없으면 OCR 주민번호). */
    private function patientBirthYmd(PrescriptionConsent $consent): ?string
    {
        $patient = $consent->prescription?->patient;
        if ($patient?->birth_date) {
            return $patient->birth_date->format('Ymd');
        }

        // 폴백: OCR 주민번호 앞 7자리에서 도출
        // 생년월일·성별은 마스킹에도 남아 있으므로 평문을 열 이유가 없다(P0-1)
        $rrn = preg_replace('/\D/', '', (string) $consent->prescription?->masked_resident_no_ocr);
        if (strlen($rrn) >= 7) {
            $yy = substr($rrn, 0, 2);
            $md = substr($rrn, 2, 4);
            $g  = $rrn[6];
            $century = match ($g) {
                '1', '2', '5', '6' => '19',
                '3', '4', '7', '8' => '20',
                '9', '0'           => '18',
                default            => '19',
            };
            return $century.$yy.$md;
        }

        return null;
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
            // NICE 본인확인 (CI/DI 등 민감식별정보는 내려보내지 않는다)
            'nice_verified'    => $latest->isIdentityVerified(),
            'nice_verified_at' => $latest->nice_verified_at?->format('Y-m-d H:i'),
            'nice_name'        => $latest->nice_name,
            'nice_mobile'      => $latest->nice_mobile,
            'nice_authtype'    => $latest->niceAuthTypeLabel(),
            'signature_data'  => $latest->status === 'agreed' ? $latest->signature_data : null,
            // 미성년자 — 법정대리인
            'is_minor'            => (bool) $latest->is_minor,
            'patient_birth_date'  => $latest->patient_birth_date?->toDateString(),
            'guardian_name'       => $latest->guardian_name,
            'guardian_relation'   => $latest->guardian_relation,
            'guardian_birth_date' => $latest->guardian_birth_date?->toDateString(),
            'guardian_phone'     => $latest->guardian_phone,
            'guardian_signature'  => $latest->status === 'agreed' ? $latest->guardian_signature_data : null,
            // 신분증은 본문으로 내리지 않는다. 볼 때만 권한을 거쳐 나가는 주소를 준다.
            'guardian_id_url'     => $latest->guardian_id_path
                                      ? route('files.consent-guardian-id', $latest) : null,
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
        // 쓰인 글자만 심는다. 나눔고딕 원본이 4.5MB 라 통째로 심으면 산출물이 2.7MB 가 되고
        // 만드는 동안 메모리가 128MB 를 넘겨 위임장 내려받기가 500 으로 떨어졌다.
        $options->setIsFontSubsettingEnabled(true);
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
     * 어드민: 서명 이미지 PNG 다운로드
     *
     * 서명은 data URL 문자열로만 들고 있어 그대로는 파일로 쓸 수 없다.
     * 앞머리를 떼고 디코드해 원본 바이트를 그대로 내려준다.
     */
    public function downloadSignature(Prescription $prescription)
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest()
            ->firstOrFail();

        $raw  = (string) $consent->signature_data;
        $mime = 'image/png';
        if (preg_match('#^data:(image/\w+);base64,(.+)$#s', $raw, $m)) {
            $mime = $m[1];
            $raw  = $m[2];
        }
        $bytes = base64_decode($raw, true);
        if ($bytes === false || $bytes === '') {
            abort(422, '서명 이미지를 해석할 수 없습니다.');
        }

        $ext      = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $mobile   = preg_replace('/[^0-9]/', '', $consent->patient_mobile ?? '');
        $filename = '서명_' . $consent->patient_name . '_' . $mobile . '_'
                  . ($consent->responded_at?->format('Ymd') ?? date('Ymd')) . '.' . $ext;

        // 서명도 개인정보다 — 누가 언제 받아 갔는지 남긴다
        activity()->causedBy(auth()->user())->performedOn($prescription)
            ->log("위임동의 서명 이미지 다운로드 → {$consent->patient_name}");

        return response($bytes, 200, [
            'Content-Type'        => $mime,
            'Content-Length'      => strlen($bytes),
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
        // 쓰인 글자만 심는다. 나눔고딕 원본이 4.5MB 라 통째로 심으면 산출물이 2.7MB 가 되고
        // 만드는 동안 메모리가 128MB 를 넘겨 위임장 내려받기가 500 으로 떨어졌다.
        $options->setIsFontSubsettingEnabled(true);
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
     * 요양비위임장 오버레이 PDF 바이트 반환 (서명 없으면 null).
     * 팩스통합본 병합 등 외부 재사용용.
     */
    public function overlayPdfBytes(Prescription $prescription): ?string
    {
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest()
            ->first();

        if (!$consent) {
            return null;
        }

        try {
            return $this->buildDelegationOverlayPdf($consent);
        } catch (\Throwable $e) {
            \Log::warning('요양비위임장 생성 실패(팩스통합): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * TCPDF 나눔고딕 폰트를 vendor 폰트 디렉터리에 확보.
     * 이미 있으면 아무것도 안 함(런타임 쓰기 없음). 없으면 커밋된 사전생성본을 복사 시도.
     * (배포 시 composer post-autoload-dump 훅이 미리 복사하므로 평소엔 여기서 스킵됨)
     */
    private function ensureNanumGothicTcpdfFont(): void
    {
        $dir = defined('K_PATH_FONTS') ? K_PATH_FONTS : base_path('vendor/tecnickcom/tcpdf/fonts/');
        if (is_file($dir . 'nanumgothic.php')) {
            return;
        }
        $src = resource_path('fonts/tcpdf/');
        foreach (['nanumgothic.php', 'nanumgothic.z', 'nanumgothic.ctg.z'] as $f) {
            if (is_file($src . $f)) {
                @copy($src . $f, $dir . $f);
            }
        }
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

        // 한글 폰트: 커밋된 사전생성 폰트 사용 (런타임 vendor 쓰기 없이). 없으면 복사 시도.
        $this->ensureNanumGothicTcpdfFont();
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

                // 미성년자면 법정대리인 서명도 함께 찍는다
                if ($consent->is_minor && $consent->guardian_signature_data) {
                    $graw = $consent->guardian_signature_data;
                    if (preg_match('#^data:image/\w+;base64,(.+)$#s', $graw, $gm)) $graw = $gm[1];
                    $gimg = base64_decode($graw, true);
                    if ($gimg !== false && $gimg !== '') {
                        $pdf->Image('@' . $gimg,
                            (float) config('delegation.guardian_signature.x', 164),
                            (float) config('delegation.guardian_signature.y', 280),
                            (float) config('delegation.guardian_signature.w', 28),
                            0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
                    }
                }

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
        /* 종료일은 시작일 + N년에서 하루를 뺀다.
           2026-08-11 부터 5년이면 2031-08-10 까지다 — 건보 사이트도 그렇게 잡는다.
           하루를 빼지 않으면 5년 하고 하루가 되어 최장 기간을 넘긴다. */
        $ed      = $sd->copy()->addYears($py)->subDay();

        $pdf->SetTextColor(0, 0, 0);

        /* 좌표는 설정에서 온다. 예전에는 이 자리에 숫자가 박혀 있어 양식이 조금만 달라져도
           배포를 해야 했다. 화면(위임장 설정)에서 항목마다 x·y·글자크기를 고친다. */
        $fields = (array) config('delegation.fields', []);
        $put = function (string $key, ?string $t) use ($pdf, $fontName, $fields): void {
            $t = trim((string) $t);
            $f = $fields[$key] ?? null;
            if ($t === '' || !$f) {
                return;
            }
            $pdf->SetFont($fontName, '', (float) ($f['size'] ?? 8));
            $pdf->Text((float) $f['x'], (float) $f['y'], $t);
        };

        // ① 위임인
        $put('patient_name', $consent->patient_name ?: $patient?->name);
        // 법정서식(요양비 지급청구 위임장) — 평문이 필요한 지점. 감사로그가 남는다(P0-1).
        // 처방전에 적힌 번호를 먼저 쓰고, 없으면 환자 정보의 번호를 쓴다.
        $rrn = $consent->prescription?->residentNoOcrFor('nhis_claim_form')
               ?: $patient?->residentNoFor('nhis_claim_form');
        // 서식에는 하이픈을 넣어 적는다. 저장은 숫자 열세 자리다.
        if ($rrn && preg_match('/^(\d{6})-?(\d{7})$/', preg_replace('/\s/', '', $rrn), $rm)) {
            $rrn = $rm[1] . '-' . $rm[2];
        }
        $put('patient_rrn', $rrn);
        $put('patient_mobile', $consent->patient_mobile ?: $patient?->mobile);

        /* 미성년자는 혼자 위임할 수 없다. 양식의 '법정대리인 또는 가족' 세 줄을 채운다 —
           성명 / 생년월일 / 가입자와의 관계. 생년월일 줄은 환자가 아니라 대리인의 것이다. */
        if ($consent->is_minor) {
            $put('guardian_name',     $consent->guardian_name);
            $put('guardian_birth',    $consent->guardian_birth_date?->format('Y-m-d'));
            $put('guardian_relation', $consent->guardian_relation);
        }

        /* ① 전화번호 줄의 '[ ] 문자메시지 수신동의' — 번호를 받아 두었으니 동의로 표시한다.
           글리프 대신 선으로 긋는다(④ 위임사항과 같은 방식). */
        $chk = (array) config('delegation.sms_consent_check', []);
        if (isset($chk['x'], $chk['y'])) {
            $cx = (float) $chk['x']; $cy = (float) $chk['y'];
            $pdf->SetLineStyle(['width' => 0.4, 'cap' => 'round', 'join' => 'round', 'color' => [0, 0, 0]]);
            $pdf->Line($cx,       $cy + 0.9, $cx + 1.2, $cy + 2.2);
            $pdf->Line($cx + 1.2, $cy + 2.2, $cx + 3.5, $cy - 1.0);
        }

        // ② 준요양기관
        $put('provider_name',   $prov['name']   ?? '');
        $put('provider_biz_no', $prov['biz_no'] ?? '');
        $put('provider_ceo',    $prov['ceo']    ?? '');
        $put('provider_phone',  $prov['phone']  ?? '');

        // ③ 요양비 수령계좌
        $put('account_receiver', $acct['receiver'] ?? '');
        $put('account_bank',     $acct['bank']     ?? '');
        $put('account_holder',   $acct['holder']   ?? '');
        $put('account_number',   $acct['number']   ?? '');

        // ④ 위임사항 — 4) 자가도뇨 소모성 재료 체크
        // 폰트 글리프(✔) 대신 벡터 선으로 직접 그려 깨짐 방지 (작게, 왼쪽으로)
        $pdf->SetLineStyle(['width' => 0.4, 'cap' => 'round', 'join' => 'round', 'color' => [0, 0, 0]]);
        $pdf->Line(60.6, 200.9, 61.8, 202.2);
        $pdf->Line(61.8, 202.2, 64.1, 199.0);

        // ⑤ 위임기간 (서명일부터 N년) — 인쇄된 년/월/일 글자와 겹치지 않게 작게·여백 배치
        $put('period_from_y', $sd->format('Y'));
        $put('period_from_m', $sd->format('n'));
        $put('period_from_d', $sd->format('j'));
        $put('period_to_y',   $ed->format('Y'));
        $put('period_to_m',   $ed->format('n'));
        $put('period_to_d',   $ed->format('j'));

        /* 서명란의 이름 — 양식에는 「위임인    (서명 또는 인)」 한 줄뿐이라 이름 적을 자리가
           없다. 서명 그림만 남으면 누가 위임했는지 그림으로 읽어야 한다. 그림 왼쪽에
           이름을 적어 둔다. 자리는 설정에서 고친다(다른 칸과 같은 방식). */
        $put('signature_name', $consent->patient_name ?: $patient?->name);
        if ($consent->is_minor) {
            $put('guardian_sig_name', $consent->guardian_name ?: $patient?->guardian_name);
        }
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
