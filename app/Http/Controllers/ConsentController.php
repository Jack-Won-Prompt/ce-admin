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

        /* 개인정보 동의를 이미 받아 둔 사람에게는 그 영역을 보이지 않는다.
           동의는 사람에게 한 번 받으면 그것으로 족하다 — 처방전마다 다시 물으면
           같은 것을 몇 번씩 읽히는 꼴이 되고, 목록에도 같은 줄이 쌓인다. */
        $privacyDone = $this->privacyAlreadyDone($consent);
        $privacyFill = $privacyDone ? [] : $this->privacyPrefill($consent);

        return view('consent.sign', compact(
            'consent', 'niceEnabled', 'niceEnforce', 'verified', 'privacyDone', 'privacyFill'
        ));
    }

    /** 이 사람의 개인정보 동의를 이미 받아 두었는가 — 서명 화면과 제출이 같은 눈으로 본다. */
    private function privacyAlreadyDone(PrescriptionConsent $consent): bool
    {
        $consent->loadMissing('prescription.patient');
        $rx = $consent->prescription;

        $found = \App\Models\PrivacyConsent::findFor(
            $rx?->patient_id,
            $consent->patient_name ?: $rx?->patient?->bare_name,
            $consent->patient_mobile ?: $rx?->patient?->mobile,
        );

        return (bool) $found?->required_agreed;
    }

    /** 신청자 정보 미리 채우기 — 우리가 이미 아는 것을 환자에게 다시 적게 하지 않는다. */
    private function privacyPrefill(PrescriptionConsent $consent): array
    {
        $consent->loadMissing('prescription.patient');
        $p = $consent->prescription?->patient;

        return [
            // IC = 카테터 · OC = 장루. 모르면 고르게 둔다.
            'type'   => match ($p?->care_type) { 'IC' => 'catheter', 'OC' => 'stoma', default => '' },
            'name'   => $consent->patient_name ?: (string) $p?->bare_name,
            'phone'  => $consent->patient_mobile ?: (string) $p?->mobile,
            'phone2' => (string) $p?->phone,
            'zip'    => (string) $p?->postcode,
            'addr1'  => (string) $p?->address,
            'addr2'  => (string) $p?->address_detail,
            'email'  => (string) $p?->email,
            'birth'  => $consent->patient_birth_date?->toDateString()
                        ?? $p?->birth_date?->toDateString() ?? '',
        ];
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
            // 개인정보 수집·이용 동의 — 개인정보동의 페이지(privacy)와 같은 칸·같은 값
            'privacy_type'    => 'nullable|in:catheter,stoma',
            'name'            => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:30',
            'phone2'          => 'nullable|string|max:30',
            'email'           => 'nullable|string|max:150',
            'zip'             => 'nullable|string|max:10',
            'addr1'           => 'nullable|string|max:200',
            'addr2'           => 'nullable|string|max:200',
            'insurance'       => 'nullable|string|max:30',
            'support_qualify' => 'nullable|string|max:40',
            'birth'           => 'nullable|string|max:20',
            'product'         => 'nullable|string|max:40',
            'hospital'        => 'nullable|string|max:100',
            'surgery_date'    => 'nullable|string|max:20',
            'stoma_type'      => 'nullable|string|max:20',
            'stoma_kind'      => 'nullable|string|max:20',
            'agree_general'             => 'nullable|in:동의함,동의하지 않음',
            'agree_sensitive'           => 'nullable|in:동의함,동의하지 않음',
            'agree_third_party'         => 'nullable|in:동의함,동의하지 않음',
            'agree_third_sensitive'     => 'nullable|in:동의함,동의하지 않음',
            'agree_marketing'           => 'nullable|in:동의함,동의하지 않음',
            'agree_marketing_sensitive' => 'nullable|in:동의함,동의하지 않음',
        ]);

        /* 개인정보 동의 — 화면에서도 막지만 서버에서 다시 본다. 필수 칸ㆍ필수 동의는
           개인정보동의 페이지의 그 유형 폼과 같은 것을 본다(카테터/장루가 다르다).
           이미 받아 둔 사람에게는 화면에 그 영역이 없으므로 여기서도 묻지 않는다. */
        $needPrivacy = $request->action === 'agreed' && !$this->privacyAlreadyDone($consent);

        if ($needPrivacy) {
            $type = $request->input('privacy_type') === 'stoma' ? 'stoma' : 'catheter';

            $need = $type === 'stoma'
                ? ['name' => '성명', 'phone' => '연락처', 'birth' => '생년월일']
                : ['name' => '성명', 'phone' => '연락처', 'insurance' => '보험'];
            foreach ($need as $k => $label) {
                if (trim((string) $request->input($k)) === '') {
                    return response()->json(['success' => false, 'message' => "개인정보 동의의 「{$label}」을(를) 적어 주세요."], 422);
                }
            }

            $must = $type === 'stoma'
                ? ['agree_general' => '일반정보 수집·이용', 'agree_sensitive' => '민감정보 수집·이용']
                : ['agree_general' => '일반정보 수집·이용', 'agree_third_party' => '제3자 제공'];
            foreach ($must as $k => $label) {
                if ($request->input($k) !== '동의함') {
                    return response()->json(['success' => false, 'message' => "「{$label}」 동의가 필요합니다."], 422);
                }
            }
        }

        /* 빈 서명은 서명이 아니다. 화면에서도 보지만, 화면을 거치지 않고 들어오는
           요청이 있고 화면 쪽 잣대가 한 번 새기도 했다 — 여기서 다시 본다. */
        if ($request->action === 'agreed') {
            if (!$this->signatureHasInk((string) $request->input('signature'))) {
                return response()->json(['success' => false, 'message' => '서명이 비어 있습니다.'], 422);
            }
            if ($consent->is_minor
                && !$this->signatureHasInk((string) $request->input('guardian_signature'))) {
                return response()->json(['success' => false, 'message' => '보호자 서명이 비어 있습니다.'], 422);
            }
        }

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

        /* 미성년자의 본인확인은 보호자가 한다(matchesPatient 참고). 그때는 이름을
           견주지 않고 넘겼으니, 인증한 사람과 화면에 적은 보호자가 같은 사람인지
           여기서 맞춰 본다. 그러지 않으면 아무나 인증하고 아무 이름이나 적을 수 있다.
           시험용 시뮬레이션은 환자 이름을 그대로 채우므로 가리지 않는다. */
        if ($request->action === 'agreed' && $consent->is_minor
            && !config('nice.simulate') && $consent->isIdentityVerified()
        ) {
            $norm = fn ($v) => preg_replace('/\s+/', '', (string) $v);
            if ($norm($consent->nice_name) !== $norm($request->input('guardian_name'))) {
                return response()->json([
                    'success' => false,
                    'message' => '본인확인을 한 분과 법정대리인 성명이 다릅니다.',
                ], 422);
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

        $applied = [];   // 위임 서명과 함께 주문 등록에 옮겨 적은 칸들

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
            if ($needPrivacy) {
                /* 환자를 잇는 것이 먼저다. 아직 사람으로 맺어지지 않은 처방전이면 여기서
                   맺어지고, 그 번호가 아래 개인정보 동의 줄에도 함께 적힌다. */
                $applied = $this->applyPrivacyToRecords($consent, $request);
                $this->savePrivacyConsent($consent, $request);
            }

            /* 서명이 끝난 건은 주문 관리에도 선다. 처방전 그림이 없어도, 아직 제품을
               고르지 않았어도 그렇다 — 위임을 받아 둔 건은 이미 거래가 시작된 것이고,
               담당자가 그다음에 손댈 것을 찾는 자리가 그 목록이다.
               우리 쪽 줄만 세운다. 창고로 보내는 것은 「주문 생성 및 연계」가 할 일이다. */
            $consent->loadMissing('prescription');
            if ($consent->prescription) {
                \App\Support\OrderSync::ensure($consent->prescription->refresh());
            }
            $this->generateConsentPdf($consent);

            // 요양비위임장(원본 오버레이)도 첨부문서에 자동 추가
            $consent->loadMissing('prescription');
            if ($consent->prescription) {
                $this->saveDelegationDocument($consent->prescription);

                /* 동의가 끝났으니 공단에 등록 서류를 보낸다(2026-09-03 지시 ·
                   시나리오 1.1.x.1). 낼 것이 다 있으면 보내고, 하나라도 빠졌으면
                   보내는 대신 담당자에게 알린다 — 빠진 채로 나간 팩스는 공단이
                   되돌려 보내고, 그때는 처음부터 다시 해야 한다.

                   요양비위임장을 방금 만들었으므로 그 뒤라야 한다. 앞에 두면 늘
                   「위임장이 없다」로 읽힌다.

                   여기서 무슨 일이 있어도 서명은 이미 끝난 것이라 되돌리지 않는다 —
                   환자 화면에 오류가 뜨면 다시 서명하려 든다. */
                try {
                    app(\App\Services\NhisFaxAuto::class)->attempt($consent->prescription->refresh());
                } catch (\Throwable $e) {
                    \Log::warning('[공단 팩스 자동] 실패', [
                        'rx' => $consent->prescription->rx_number, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 관리자 전체에게 실시간 알림 브로드캐스트
        try {
            broadcast(new ConsentSubmitted($consent, $applied));
        } catch (\Throwable $e) {
            \Log::warning('ConsentSubmitted 브로드캐스트 실패: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'action'  => $request->action,
        ]);
    }

    /**
     * 서명 화면에서 함께 받은 개인정보 수집·이용 동의를 남긴다.
     *
     * 개인정보동의 화면(privacy_consents)이 읽는 그 표에 그대로 쌓는다 — 동의를 어디서
     * 받았든 한 자리에서 보아야 하고, 주문 등록의 「개인정보동의 완료」도 이 표를 본다.
     *
     * 줄은 고치지 않고 새로 적는다. 동의는 그때 그 시점의 기록이라, 뒤에 다시 받은 동의가
     * 앞의 것을 덮으면 언제 무엇에 동의했는지가 사라진다(공개 폼도 같게 쌓는다).
     */
    private function savePrivacyConsent(PrescriptionConsent $consent, Request $request): void
    {
        $consent->loadMissing('prescription.patient');
        $rx = $consent->prescription;

        // 환자가 화면에서 고른 유형을 따른다. 고르지 않았으면 카테터 폼과 같은 항목을 받았다.
        $type = $request->input('privacy_type') === 'stoma' ? 'stoma' : 'catheter';

        $fields = [
            'name', 'phone', 'phone2', 'email', 'zip', 'addr1', 'addr2',
            'insurance', 'support_qualify', 'birth', 'product', 'hospital', 'surgery_date',
            'stoma_type', 'stoma_kind',
            'agree_general', 'agree_sensitive', 'agree_third_party',
            'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive',
        ];

        try {
            \App\Models\PrivacyConsent::create(array_merge($request->only($fields), [
                'patient_id'   => $rx?->patient_id,
                'type'         => $type,
                'source'       => 'mobile',
                // 어느 위임동의 서명에서 따라온 동의인지 — 뒤에 되짚을 자리를 남긴다
                'extra'        => [
                    'from'                    => 'consent_sign',
                    'prescription_consent_id' => $consent->id,
                    'rx_number'               => $rx?->rx_number,
                ],
                'ip'           => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 300),
                'submitted_at' => now(),
            ]));
        } catch (\Throwable $e) {
            /* 개인정보 동의를 남기지 못했다고 위임동의 서명까지 되돌릴 수는 없다.
               서명은 환자가 이미 마친 일이다 — 흔적만 남기고 계속 간다. */
            \Log::error('위임동의 서명의 개인정보 동의 저장 실패', [
                'consent_id' => $consent->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 동의서에 적어 준 것을 환자ㆍ처방전에도 옮겨 적는다.
     *
     * 환자가 손수 적은 주소ㆍ이메일ㆍ연락처가 개인정보동의 표에만 남아 있으면, 주문 등록
     * 화면은 여전히 옛 주소를 들고 배송을 건다. 갈 자리가 있는 것은 그 자리로 보낸다.
     *
     * 갈 자리가 없는 것(보험ㆍ지원 자격 원문ㆍ장루 상세)은 옮기지 않는다 — 억지로 메모에
     * 밀어 넣으면 담당자가 적은 것과 섞인다. 그것들은 주문 등록의 「개인정보동의」 단추를
     * 눌러 동의서 그대로 읽는다.
     *
     * @return array<string,string> 주문 등록의 입력칸 id => 새 값
     */
    private function applyPrivacyToRecords(PrescriptionConsent $consent, Request $request): array
    {
        $consent->loadMissing('prescription.patient');
        $rx = $consent->prescription;

        $applied = [];
        $val = fn (string $k): string => trim((string) $request->input($k));

        if (! $rx) {
            return [];
        }

        /* 아직 사람으로 맺어지지 않은 처방전이 많다 — 주문 등록에서 이름만 적어 두고
           저장을 누르지 않은 채 링크를 보낸 건들이다. 그대로 두면 서명이 끝나도 옮겨
           적을 자리가 없어 고객관리에 아무것도 남지 않는다. 여기서 맺는다.

           잇는 규칙은 주문 등록의 저장과 같은 것(PatientLink)을 쓴다 — 주민번호로,
           없으면 이름+휴대폰으로, 그것도 없으면 이름이 하나뿐일 때. 아무것도 걸리지
           않으면 그 이름으로 새로 만든다. */
        if (! $rx->patient_id) {
            \App\Support\PatientLink::attach($rx, [
                'patient_name' => $consent->patient_name ?: $rx->patient_name_ocr,
                'resident_no'  => null,
                'mobile'       => $val('phone') ?: $consent->patient_mobile ?: $rx->mobile_ocr,
                'address'      => $val('addr1'),
                'care_type'    => $request->input('privacy_type') === 'stoma' ? 'OC' : 'IC',
            ]);
            $rx->refresh();
        }

        $p = $rx->patient;

        /* 주소ㆍ휴대폰은 처방전에도 제 칸이 있다. 주문 등록 화면이 읽는 것이 그 칸이라,
           환자에만 적어 두면 새로고침해도 옛 주소가 그대로 보인다. 둘 다 적는다. */
        $rxSet = [];
        foreach ([
            'zip'   => ['postcode',       'f-postcode'],
            'addr1' => ['address_ocr',    'f-address'],
            'addr2' => ['address_detail', 'f-address-detail'],
            'phone' => ['mobile_ocr',     'f-mobile'],
        ] as $from => [$col, $fieldId]) {
            $v = $val($from);
            if ($v !== '' && (string) $rx->{$col} !== $v) {
                $rxSet[$col] = $v;
                $applied[$fieldId] = $v;
            }
        }
        if ($rxSet) {
            $rx->forceFill($rxSet)->save();
        }

        if ($p) {
            $set = [];
            foreach ([
                'zip'    => ['postcode',       'f-postcode'],
                'addr1'  => ['address',        'f-address'],
                'addr2'  => ['address_detail', 'f-address-detail'],
                'email'  => ['email',          'f-email'],
                'phone'  => ['mobile',         'f-mobile'],
                'phone2' => ['phone',          'f-mobile2'],
            ] as $from => [$col, $fieldId]) {
                $v = $val($from);
                if ($v !== '' && (string) $p->{$col} !== $v) {
                    $set[$col] = $v;
                    $applied[$fieldId] = $v;   // 처방전 칸과 겹치면 같은 값이라 덮어도 같다
                }
            }

            // 생년월일은 비어 있을 때만 — 주민번호에서 읽는 값이 더 확실하다
            if ($val('birth') !== '' && ! $p->birth_date) {
                $set['birth_date'] = $val('birth');
                $applied['f-birth'] = $val('birth');
            }

            /* 사업부도 비어 있을 때만 둔다. 이름 앞의 (E) 가 이 칸을 따라 붙고 떨어져,
               환자가 유형을 잘못 골랐을 때 이름까지 바뀐다. */
            if (! $p->care_type) {
                $set['care_type'] = $request->input('privacy_type') === 'stoma' ? 'OC' : 'IC';
            }

            if ($set) {
                $p->forceFill($set)->save();
            }
        }

        /* 자격은 처방전에 붙는다. 이미 골라 둔 것이 있으면 담당자의 손을 덮지 않는다.
           동의서의 「보험」은 산업재해만 자격 칸에 대응하는 값이 있다. */
        if (! $rx->benefit_class) {
            $bc = ['일반' => '일반', '차상위경감대상자' => '차상위경감', '기초생활수급자' => '기초'][$val('support_qualify')]
                  ?? ($val('insurance') === '산업재해' ? '산재' : null);
            if ($bc) {
                $rx->forceFill(['benefit_class' => $bc])->save();
                $applied['f-benefit-class'] = $bc;
            }
        }

        return $applied;
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

        /* 시험 중에는 NICE 에 묻지 않고 통과시킨다(NICE_SIMULATE). 실제 인증에서
           채우는 칸을 그대로 채워 두어, 뒤 화면들이 다른 것을 보지 않게 한다. */
        if (config('nice.simulate')) {
            $consent->update([
                'nice_verified_at' => now(),
                'nice_name'        => $consent->patient_name,
                /* 칸이 좁다(varchar 몇 자) — NICE 가 주는 값도 'M'ㆍ'S' 같은 한 글자다 */
                'nice_authtype'    => 'S',
            ]);
            \Log::info('[NICE][시뮬레이션] 본인확인을 통과시켰습니다', ['consent' => $consent->id]);

            return response()->json(['success' => true, 'simulated' => true, 'name' => $consent->patient_name]);
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
     * 공개 GET/POST: NICE 표준창 return_url 콜백.
     * web_transaction_id 로 인증 결과를 받아 풀고 → 처방전 환자(이름/생년월일) 매칭 → 결과 저장.
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
     * 서명 그림에 획이 하나라도 남아 있는가.
     *
     * 투명한 그림이 올라오면 위임장에 아무것도 얹히지 않은 채 공단으로 간다.
     * GD 가 없거나 그림을 못 읽으면 막지 않는다 — 사람이 그린 것을 우리 사정으로
     * 되돌리지 않는다.
     */
    private function signatureHasInk(string $data): bool
    {
        if (trim($data) === '') return false;
        if (!function_exists('imagecreatefromstring')) return true;

        if (preg_match('#^data:image/\w+;base64,(.+)$#s', $data, $m)) $data = $m[1];
        $bin = base64_decode($data, true);
        if ($bin === false || $bin === '') return false;

        $im = @imagecreatefromstring($bin);
        if (!$im) return true;

        $w = imagesx($im); $h = imagesy($im);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                if ((imagecolorat($im, $x, $y) >> 24 & 0x7F) < 100) return true;
            }
        }

        return false;
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

        /* 미성년자는 본인이 인증하지 못한다 — 휴대폰이 제 이름으로 없고, 위임하는 사람도
           본인이 아니라 법정대리인이다. 그래서 환자 이름ㆍ생년월일과 견주면 보호자가
           제대로 인증해도 「환자와 일치하지 않습니다」로 막힌다.

           여기서는 이름이 돌아왔는지만 본다. 인증한 사람이 정말 그 보호자인지는
           서명할 때 화면에 적은 「법정대리인 성명」과 맞춰 본다. */
        if ($consent->is_minor) {
            return $norm($result['name']) === ''
                ? ['ok' => false, 'message' => '본인확인 결과에 이름이 없습니다.']
                : ['ok' => true, 'message' => ''];
        }

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

        /* 개인정보 동의는 위임동의와 다른 표에 쌓인다. 주문 등록 화면의 두 단추가
           같은 한 번의 확인으로 다시 그려지도록 여기에 함께 싣는다 —
           위임동의를 아직 보내지 않은 처방전에도 개인정보 동의는 있을 수 있어,
           아래 「없음」 답에도 넣는다. */
        $privacy = \App\Models\PrivacyConsent::stateFor(
            $prescription->patient_id,
            $prescription->patient?->bare_name ?? $prescription->patient_name_ocr,
            $prescription->patient?->mobile    ?? $prescription->mobile_ocr,
        );

        if (!$latest) {
            return response()->json(['exists' => false, 'privacy' => $privacy]);
        }

        // pending 이면 실시간으로 만료 여부 체크
        if ($latest->status === 'pending' && $latest->expires_at->isPast()) {
            $latest->update(['status' => 'expired']);
        }

        return response()->json([
            'exists'          => true,
            'privacy'         => $privacy,
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

            /* 쓰지 못했으면 여기서 멈춘다. 그냥 지나가면 파일이 없는 서류 줄이 서고,
               화면에는 요양비위임장이 있는 것으로 보인다 — 공단 팩스가 그 줄을 믿고
               첨부하려다 그때서야 없는 것을 안다. */
            if (!Storage::put($path, $pdfData)) {
                throw new \RuntimeException("요양비위임장 파일을 쓰지 못했습니다 ({$path}).");
            }

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
            /* 위임장이 없으면 공단 청구가 서지 않는다 — 지나가는 일이 아니다 */
            \Log::error('요양비위임장 자동첨부 실패: ' . $e->getMessage(),
                ['prescription' => $prescription->id]);
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

        /* 위임일 — 서명란 바로 위의 「년 월 일」 줄. 비워 두면 언제 위임한 것인지가
           종이에 남지 않는다(공단이 되돌려 보내는 사유다). 서명한 날이 곧 위임한 날이라
           위임기간 시작일과 같은 날을 쓴다. 자리는 설정에서 고친다. */
        $put('sign_date_y', $sd->format('Y'));
        $put('sign_date_m', $sd->format('n'));
        $put('sign_date_d', $sd->format('j'));

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
