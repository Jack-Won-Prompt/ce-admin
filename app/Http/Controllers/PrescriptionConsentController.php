<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 위임장 서명 목록.
 *
 * 지금까지 서명은 처방전 한 건을 열어야만 볼 수 있었다. 서명한 사람과 번호를 한자리에서
 * 훑고 서류를 받아 가는 일이 잦아 목록 화면을 따로 둔다.
 */
class PrescriptionConsentController extends Controller
{
    /** 상태 칩 — 모델의 statusLabel() 과 같은 순서·문구를 쓴다 */
    public const STATUSES = [
        'agreed'   => '동의 완료',
        'pending'  => '대기 중',
        'declined' => '거절',
        'expired'  => '만료',
    ];

    public function index(Request $request): View
    {
        $query = PrescriptionConsent::with(['prescription.patient'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $kw = $request->q;
            // 번호는 하이픈 없이 저장되므로 검색어에서도 떼고 본다
            $digits = preg_replace('/\D/', '', $kw);
            $query->where(function ($q) use ($kw, $digits) {
                $q->where('patient_name', 'like', "%{$kw}%")
                  ->orWhereHas('prescription', fn ($p) => $p->where('rx_number', 'like', "%{$kw}%"));
                if ($digits !== '') {
                    $q->orWhere('patient_mobile', 'like', "%{$digits}%");
                }
            });
        }

        // 기간은 '서명한 날' 기준이다. 아직 서명하지 않은 건은 요청한 날로 본다.
        if ($request->filled('date_from')) {
            $query->whereDate(DB::raw('COALESCE(responded_at, created_at)'), '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate(DB::raw('COALESCE(responded_at, created_at)'), '<=', $request->date_to);
        }

        if ($request->boolean('signed_only')) {
            $query->whereNotNull('signature_data');
        }

        $gridData = $query->get()->map(function (PrescriptionConsent $c) {
            $rx     = $c->prescription;
            $agreed = $c->status === 'agreed';
            $hasSig = $agreed && !empty($c->signature_data);

            return [
                'id'         => $c->id,
                'status'     => $c->statusLabel(),
                'name'       => $c->patient_name ?? '',
                'mobile'     => $this->formatMobile($c->patient_mobile),
                'rx_number'  => $rx?->rx_number ?? '',
                'signed_at'  => $c->responded_at?->format('Y-m-d H:i') ?? '',
                'requested'  => $c->created_at?->format('Y-m-d H:i') ?? '',
                'identity'   => $c->isIdentityVerified() ? ($c->niceAuthTypeLabel() ?: '확인') : '',
                'signature'  => $hasSig ? '있음' : '',
                'download'   => '',      // 버튼을 그리는 자리 (renderer 가 채운다)
                'action'     => '',      // 위임동의 발송 자리

                // 버튼이 쓰는 값들 — 컬럼으로 세우지 않는다
                'status_key'     => $c->status,
                'png_url'        => $hasSig && $rx ? route('prescriptions.consentSignature', $rx) : null,
                'consent_pdf'    => $agreed && $rx && $c->pdf_path ? route('prescriptions.consentPdf', $rx) : null,
                'delegation_pdf' => $agreed && $rx ? route('prescriptions.delegationPdfOriginal', $rx) : null,
                'sms_url'        => $rx ? route('prescriptions.consentSms', $rx) : null,
            ];
        });

        $statusCounts = PrescriptionConsent::selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('prescription-consents.index', [
            'gridData'     => $gridData,
            'total'        => $gridData->count(),
            'statusCounts' => $statusCounts,
            'statuses'     => self::STATUSES,
        ]);
    }

    /**
     * 신규 위임동의 — 이름과 전화번호만 받아 보낸다.
     *
     * 동의 건은 처방전에 매달린다(prescription_consents.prescription_id 는 필수).
     * 그래서 받은 이름·번호로 처방전을 한 건 만들고 그 위에 동의를 건다.
     * 나중에 그 처방전을 열어 나머지를 채우면 된다.
     *
     * 발송 자체는 검수 화면과 같은 코드(PrescriptionController::issueConsent)를 쓴다.
     * 토큰·유효시간·문구가 갈리면 환자가 받는 링크가 화면마다 달라진다.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'   => 'required|string|max:50',
            'mobile' => 'required|string|max:20',
        ]);

        $name   = trim($request->input('name'));
        $mobile = preg_replace('/\D/', '', $request->input('mobile'));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return response()->json(['success' => false, 'message' => '수신 번호 형식이 올바르지 않습니다.'], 422);
        }

        $prescription = Prescription::create([
            'rx_number'        => Prescription::generateRxNumber(),
            'created_by'       => auth()->id(),
            'status'           => 'pending',
            'upload_source'    => 'web',
            'patient_name_ocr' => $name,
            'mobile_ocr'       => $request->input('mobile'),
            // 이름·번호가 들어 있으니 '아무것도 없는 초안' 은 아니다.
            // true 로 두면 다음에 메뉴를 눌렀을 때 이 건이 재사용돼 덮어써진다.
            'is_blank_draft'   => false,
        ]);

        activity()->causedBy(auth()->user())->performedOn($prescription)
            ->log("위임동의용 처방전 생성 → {$name} {$mobile}");

        $res  = app(PrescriptionController::class)->issueConsent($prescription, $mobile, $name);
        $data = $res->getData(true);

        // 발송이 실패하면 동의 건은 지워지지만 처방전은 남는다. 빈 껍데기를 남기지 않는다.
        if (empty($data['success'])) {
            $prescription->delete();
            return $res;
        }

        $data['rx_number'] = $prescription->rx_number;
        return response()->json($data);
    }

    /** 저장은 숫자만 되어 있다. 읽기 좋게 끊어 준다. */
    private function formatMobile(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        return match (true) {
            strlen($d) === 11 => substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7),
            strlen($d) === 10 => substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6),
            default           => $d,
        };
    }
}
