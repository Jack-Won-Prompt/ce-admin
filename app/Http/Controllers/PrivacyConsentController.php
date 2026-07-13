<?php
// app/Http/Controllers/PrivacyConsentController.php
// mcoloplast 개인정보 수집·이용 동의서 — 공개(환자 작성) 페이지

namespace App\Http\Controllers;

use App\Models\PrivacyConsent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyConsentController extends Controller
{
    /** 랜딩: 카테터/장루 선택 */
    public function landing(): View
    {
        return view('privacy.landing');
    }

    /** 카테터 동의서 폼 */
    public function catheter(): View
    {
        return view('privacy.catheter');
    }

    /** 장루 동의서 폼 */
    public function stoma(): View
    {
        return view('privacy.stoma');
    }

    /** 동의서 제출 저장 */
    public function submit(Request $request, string $type)
    {
        abort_unless(in_array($type, ['catheter', 'stoma'], true), 404);

        $rules = [
            'name'          => 'required|string|max:100',
            'phone'         => 'required|string|max:30',
            'email'         => 'nullable|string|max:150',
            'zip'           => 'nullable|string|max:10',
            'addr1'         => 'nullable|string|max:200',
            'addr2'         => 'nullable|string|max:200',
            'agree_general' => 'required|in:동의함',
        ];

        if ($type === 'catheter') {
            $rules['insurance']         = 'required|string|max:30';
            $rules['agree_third_party'] = 'required|in:동의함';
        } else { // stoma
            $rules['birth']           = 'required|string|max:20';
            $rules['agree_sensitive'] = 'required|in:동의함';
        }

        $data = $request->validate($rules, [
            'name.required'          => '성명을 입력해 주세요.',
            'phone.required'         => '연락처를 입력해 주세요.',
            'agree_general.in'       => '필수 동의 항목에 동의해 주세요.',
            'agree_third_party.in'   => '필수 동의 항목에 동의해 주세요.',
            'agree_sensitive.in'     => '필수 동의 항목에 동의해 주세요.',
            'insurance.required'     => '보험 구분을 선택해 주세요.',
            'birth.required'         => '생년월일을 입력해 주세요.',
        ]);

        $consent = PrivacyConsent::create(array_merge(
            $request->only([
                'name', 'phone', 'phone2', 'email', 'zip', 'addr1', 'addr2',
                'insurance', 'support_qualify',
                'birth', 'product', 'hospital', 'surgery_date', 'stoma_type', 'stoma_kind',
                'agree_general', 'agree_sensitive', 'agree_third_party',
                'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive',
            ]),
            [
                'type'         => $type,
                'extra'        => $request->except(['_token']),
                'ip'           => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 300),
                'submitted_at' => now(),
            ]
        ));

        return redirect()->route('privacy.done', ['type' => $type]);
    }

    /** 제출 완료 */
    public function done(string $type): View
    {
        abort_unless(in_array($type, ['catheter', 'stoma'], true), 404);
        return view('privacy.done', ['type' => $type]);
    }
}
