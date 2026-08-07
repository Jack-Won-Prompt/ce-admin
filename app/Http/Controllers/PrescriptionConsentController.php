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
     * 신규 위임동의를 보낼 처방전 찾기.
     *
     * 위임동의는 처방전 한 건 위에서만 만들어진다. 목록에 아직 없는 건에 보내려면
     * 어느 처방전인지부터 골라야 해서 검색을 따로 둔다.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => false, 'message' => '두 글자 이상 입력해 주세요.', 'rows' => []]);
        }
        $digits = preg_replace('/\D/', '', $q);

        $rows = Prescription::with(['patient', 'consents'])
            // 메뉴만 눌러 생긴 빈 초안은 보낼 대상이 아니다
            ->where(fn ($w) => $w->where('is_blank_draft', false)->orWhereNull('is_blank_draft'))
            ->where(function ($w) use ($q, $digits) {
                $w->where('rx_number', 'like', "%{$q}%")
                  ->orWhere('patient_name_ocr', 'like', "%{$q}%")
                  ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
                if ($digits !== '' && strlen($digits) >= 4) {
                    // 번호는 010-1234-5678 처럼 하이픈째 저장돼 있다.
                    // 숫자만 친 검색어와 맞추려면 비교할 때 구분자를 떼야 한다.
                    $bare = fn (string $col) => "REPLACE(REPLACE(REPLACE({$col}, '-', ''), ' ', ''), '.', '')";
                    $w->orWhereRaw($bare('mobile_ocr') . ' LIKE ?', ["%{$digits}%"])
                      ->orWhereHas('patient', fn ($p) => $p
                          ->whereRaw($bare('mobile') . ' LIKE ?', ["%{$digits}%"])
                          ->orWhereRaw($bare('phone')  . ' LIKE ?', ["%{$digits}%"]));
                }
            })
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'rows'    => $rows->map(function (Prescription $p) {
                $last = $p->consents->first();   // consents() 는 이미 latest() 다
                return [
                    'rx_number' => $p->rx_number,
                    'name'      => $p->patient?->name ?? $p->patient_name_ocr ?? '',
                    'mobile'    => $this->formatMobile($p->patient?->mobile ?? $p->mobile_ocr),
                    'hospital'  => $p->hospital_name ?? '',
                    'issued'    => $p->created_at?->format('Y-m-d') ?? '',
                    'last'      => $last ? $last->statusLabel() : '',
                    'sms_url'   => route('prescriptions.consentSms', $p),
                ];
            })->values(),
        ]);
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
