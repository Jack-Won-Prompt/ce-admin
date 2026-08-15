<?php
// app/Http/Controllers/PrivacyConsentAdminController.php
// mcoloplast 개인정보 수집·이용 동의서 — 관리자 조회/관리

namespace App\Http\Controllers;

use App\Models\PrivacyConsent;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyConsentAdminController extends Controller
{
    /** 리스트 */
    public function index(Request $request): View
    {
        $counts = [
            'all'      => PrivacyConsent::count(),
            'catheter' => PrivacyConsent::where('type', 'catheter')->count(),
            'stoma'    => PrivacyConsent::where('type', 'stoma')->count(),
        ];

        // wwGrid: 필터된 전체 데이터를 그리드용 배열로 전달 (클라이언트사이드 그리드)
        // 표시 컬럼 + 상세보기 탭용 전체 필드를 함께 담는다(더블클릭 시 인페이지 상세 표시).
        $gridData = $this->filtered($request)->latest('submitted_at')->get()->map(fn ($r) => [
            // ── 그리드 표시 컬럼 ──
            'id'        => $r->id,
            'source'    => $r->source_label,
            'type'      => $r->type_label,
            'name'      => $r->name,
            'phone'     => $r->phone,
            'email'     => $r->email ?: '',
            'address'   => $r->full_address,
            // 보험·지원자격은 값이 이미 쌓여 있었는데 상세탭에만 있었다. 그리드로 올린다.
            'insurance_col'       => $r->insurance ?: '',
            'support_qualify_col' => $r->support_qualify ?: '',
            'required'  => $r->required_agreed ? '완료' : '미완',
            'marketing' => $r->agree_marketing === '동의함' ? '동의' : '',
            'submitted' => $r->submitted_at?->format('Y-m-d H:i'),
            'admin_memo'=> $r->admin_memo ?: '',
            // ── 상세보기 탭용 필드 ──
            'type_raw'                 => $r->type,               // catheter | stoma
            'phone2'                   => $r->phone2 ?: '',
            'insurance'                => $r->insurance ?: '',
            'support_qualify'          => $r->support_qualify ?: '',
            'birth'                    => $r->birth ?: '',
            'product'                  => $r->product ?: '',
            'hospital'                 => $r->hospital ?: '',
            'surgery_date'             => $r->surgery_date ?: '',
            'stoma_type'               => $r->stoma_type ?: '',
            'stoma_kind'               => $r->stoma_kind ?: '',
            'agree_general'            => $r->agree_general ?: '',
            'agree_sensitive'          => $r->agree_sensitive ?: '',
            'agree_third_party'        => $r->agree_third_party ?: '',
            'agree_marketing'          => $r->agree_marketing ?: '',
            'agree_marketing_sensitive'=> $r->agree_marketing_sensitive ?: '',
            'agree_third_sensitive'    => $r->agree_third_sensitive ?: '',
            'submitted_full'           => $r->submitted_at?->format('Y-m-d H:i:s') ?: '',
            'ip'                       => $r->ip ?: '',
            'user_agent'               => $r->user_agent ?: '',
        ])->values();

        return view('privacy-consents.index', [
            'gridData' => $gridData,
            'counts'   => $counts,
            'type'     => $request->input('type', 'all'),
            'search'   => $request->input('search'),
            'from'     => $request->input('from'),
            'to'       => $request->input('to'),
        ]);
    }

    /** PUT /privacy-consents/{consent}/memo — 관리자 메모 저장 */
    public function saveMemo(PrivacyConsent $consent, Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['admin_memo' => 'nullable|string|max:2000']);

        $consent->update(['admin_memo' => $request->input('admin_memo')]);

        activity()->causedBy(auth()->user())->performedOn($consent)
            ->log("개인정보 동의 관리자 메모 수정 → {$consent->name}");

        return response()->json(['ok' => true, 'admin_memo' => $consent->admin_memo ?: '']);
    }

    /** CSV(엑셀) 다운로드 */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->latest('submitted_at')->get();

        $filename = 'privacy_consents_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            '구분', '유형', '성명', '연락처', '연락처2', '이메일', '우편번호', '기본주소', '상세주소',
            '보험', '지원자격', '생년월일', '사용제품', '수술병원', '수술일자', '장루타입', '장루종류',
            '일반정보동의', '민감정보동의', '제3자제공동의', '마케팅동의', '민감마케팅동의', '민감제3자동의',
            '작성일', 'IP', '관리자메모',
        ];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            // Excel 한글 깨짐 방지 (UTF-8 BOM)
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->source_label, $r->type_label, $r->name, $r->phone, $r->phone2, $r->email, $r->zip, $r->addr1, $r->addr2,
                    $r->insurance, $r->support_qualify, $r->birth, $r->product, $r->hospital, $r->surgery_date,
                    $r->stoma_type, $r->stoma_kind,
                    $r->agree_general, $r->agree_sensitive, $r->agree_third_party,
                    $r->agree_marketing, $r->agree_marketing_sensitive, $r->agree_third_sensitive,
                    $r->submitted_at?->format('Y-m-d H:i:s'), $r->ip, $r->admin_memo,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** 공통 필터 */
    private function filtered(Request $request)
    {
        $query = PrivacyConsent::query();

        if (in_array($request->input('type'), ['catheter', 'stoma'], true)) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $kw = $request->search;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('phone', 'like', "%{$kw}%")
                  ->orWhere('email', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('submitted_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('submitted_at', '<=', $request->to);
        }

        return $query;
    }
}
