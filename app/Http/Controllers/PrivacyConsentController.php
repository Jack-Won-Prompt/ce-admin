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
            $rules['insurance'] = 'required|string|max:30';

            /* 카테터는 2026-09-10 부터 기존 동의서와 같은 다섯 영역을 받는다 —
               필수 넷을 모두 본다(App\Support\ConsentTerms) */
            foreach (\App\Support\ConsentTerms::카테터필수 as $칸) {
                $rules[$칸] = 'required|in:동의함';
            }
        } else { // stoma
            $rules['birth']           = 'required|string|max:20';
            $rules['agree_sensitive'] = 'required|in:동의함';
        }

        /* 서명을 함께 받는다 (2026-10-02 지시). 처방을 끼지 않고 사는 사람은 이 링크
           하나로 끝나야 하고, 그 서명이 **위임 서명으로도** 인정된다
           (DelegationGate::공개동의서서명). 빈 판은 받는 쪽에서 한 번 더 걸러진다. */
        $rules['signature'] = 'required|string|max:' . (2 * 1024 * 1024);

        $data = $request->validate($rules, [
            'name.required'          => '성명을 입력해 주십시오.',
            'phone.required'         => '연락처를 입력해 주십시오.',
            'agree_general.in'         => '필수 동의 항목에 동의해 주십시오.',
            'agree_third_party.in'     => '필수 동의 항목에 동의해 주십시오.',
            'agree_sensitive.in'       => '필수 동의 항목에 동의해 주십시오.',
            'agree_third_sensitive.in' => '필수 동의 항목에 동의해 주십시오.',
            'insurance.required'     => '보험 구분을 선택해 주십시오.',
            'birth.required'         => '생년월일을 입력해 주십시오.',
            'signature.required'     => '서명을 입력해 주십시오.',
            'signature.max'          => '서명 그림이 너무 큽니다. 다시 서명해 주십시오.',
        ]);

        $consent = PrivacyConsent::create(array_merge(
            $request->only([
                'name', 'phone', 'phone2', 'email', 'zip', 'addr1', 'addr2',
                'insurance', 'support_qualify',
                'birth', 'product', 'hospital', 'surgery_date', 'stoma_type', 'stoma_kind',
                'agree_general', 'agree_sensitive', 'agree_third_party',
                'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive',
                'agree_ads',
            ]),
            [
                'type'         => $type,
                /* 공개 동의서 링크에서 서명까지 받은 것 — 모바일 동의와 가려야 한다 */
                'source'         => 'web',
                'signature_data' => $this->서명다듬기($request->input('signature')),
                'signed_at'      => now(),
                /* 서명 그림은 `extra` 에 담지 않는다 — 같은 그림을 두 칸에 쥐면
                   줄마다 수십KB 가 겹치고, 자취를 읽는 화면이 그만큼 무거워진다. */
                'extra'        => $request->except(['_token', 'signature']),
                'ip'           => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 300),
                'submitted_at' => now(),
            ]
        ));

        /* 운영 서버로 건넨다 (2026-10-02 지시).

           **담는 것이 먼저고 보내는 것은 그 뒤다.** 환자는 이미 서명을 마쳤다 —
           운영 서버가 멈춰 있다고 이 화면에 잘못을 띄우면 환자는 자기가 뭘 잘못한 줄
           알고 다시 쓴다. 보내다 실패해도 줄은 이쪽에 남아 있어, 주소를 고친 뒤
           다시 보내면 된다(자취는 웹훅 로그에 남는다). */
        try {
            app(\App\Services\PrivacyConsentForwarder::class)->보내기($consent);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[개인정보 동의] 운영 서버로 건네지 못했다', [
                'id' => $consent->id, 'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('privacy.done', ['type' => $type]);
    }

    /**
     * 서명 그림을 다듬는다 — 쓸 수 없는 것은 없는 것으로 본다.
     *
     * 받는 쪽(PrivacyConsentWebhookController::서명다듬기)과 **같은 잣대**를 쓴다.
     * 두 곳이 다르게 재면, 이쪽에서는 서명으로 담긴 것이 저쪽에서 버려져 운영
     * 화면에만 서명이 비어 보인다.
     */
    private function 서명다듬기(?string $값): ?string
    {
        $글 = trim((string) $값);

        if ($글 === '') {
            return null;
        }

        if (! str_starts_with($글, 'data:')) {
            $글 = 'data:image/png;base64,' . $글;
        }

        if (! preg_match('~^data:image/(png|jpeg|jpg);base64,([A-Za-z0-9+/=\s]+)$~', $글, $m)) {
            return null;
        }

        $알맹이 = preg_replace('/\s+/', '', $m[2]);

        /* 1,000자보다 짧은 PNG 는 손가락이 닿지 않은 빈 판이다 */
        if (strlen($알맹이) < 1000) {
            return null;
        }

        return 'data:image/' . ($m[1] === 'jpg' ? 'jpeg' : $m[1]) . ';base64,' . $알맹이;
    }

    /** 제출 완료 */
    public function done(string $type): View
    {
        abort_unless(in_array($type, ['catheter', 'stoma'], true), 404);
        return view('privacy.done', ['type' => $type]);
    }
}
