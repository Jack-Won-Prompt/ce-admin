<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PrivacyConsent;
use App\Support\WebhookKeys;
use App\Support\WebhookLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 공개 개인정보 동의서가 두드리는 자리 (2026-10-02 지시).
 *
 * 「https://www.ceadmin.co.kr/privacy => 동의 하면 웹훅으로 운영 서버에 보내고
 *   운영 서버 웹훅 실행 시 서명동의 완료 항목에 업데이트도 가능하게」
 *
 * ## 왜 웹훅인가
 *
 * 공개 동의서는 **다른 서버**에 있다. `www.ceadmin.co.kr` 은 3.34.53.36 이고 운영은
 * 75.2.99.52 다(2026-10-02 확인). 같은 코드가 돌지만 **DB가 다르다** — 그쪽에 담긴
 * 동의는 운영 화면에서 보이지 않는다. 그래서 건너오게 한다.
 *
 * ## 받은 값을 어디까지 믿는가
 *
 * 팝빌 웹훅은 본문을 믿지 않고 저쪽에 다시 물어 확인한다. 여기서는 그럴 수 없다 —
 * **되물을 창구가 없고**, 서명 그림 자체가 본문에 실려 온다. 그래서 지키는 길은
 * 주소에 박은 열쇠 하나다. 열쇠가 맞을 때만 받는다.
 *
 * 그 대신 **들어온 값으로 사람을 새로 만들지 않는다.** 이름과 전화번호로 이미 있는
 * 거래처를 찾아 잇기만 하고, 못 찾으면 `patient_id` 를 비워 담는다 — 담당자가 화면에서
 * 확인해 맺는다. 밖에서 온 글로 거래처를 세우면 동명이인ㆍ오기입이 그대로 들어온다.
 *
 * ## 같은 것이 두 번 와도 한 줄이다
 *
 * 웹훅은 실패하면 다시 온다. 보내는 쪽의 줄 번호(`source_ref`)와 보낸 자리
 * (`source_host`)로 맞춰, 있으면 고치고 없으면 담는다.
 */
class PrivacyConsentWebhookController extends Controller
{
    /** 서명 그림이 이보다 크면 받지 않는다 — 서명 한 장은 보통 20~60KB 다 */
    private const 서명최대 = 2 * 1024 * 1024;

    public function handle(Request $request, ?string $key = null): JsonResponse
    {
        $기록 = WebhookLogger::inbound('privacy', 'consent', $request);

        if (! WebhookKeys::맞나('privacy', $request, $key)) {
            Log::warning('[개인정보 동의 웹훅] 열쇠가 맞지 않는다', ['ip' => $request->ip()]);
            WebhookLogger::finish($기록, ok: false, status: 401, error: '인증 키 불일치');

            return response()->json(['ok' => false, 'message' => '인증 키가 일치하지 않습니다.'], 401);
        }

        try {
            $값 = $this->고르게($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $말 = implode(' · ', collect($e->errors())->flatten()->all());
            WebhookLogger::finish($기록, ok: false, status: 422, error: $말);

            return response()->json(['ok' => false, 'message' => $말], 422);
        }

        try {
            [$동의, $새것인가] = $this->담기($값, $request);
        } catch (\Throwable $e) {
            Log::error('[개인정보 동의 웹훅] 담지 못했다', [
                'error' => $e->getMessage(), 'name' => $값['name'] ?? null,
            ]);
            WebhookLogger::finish($기록, ok: false, status: 500, error: $e->getMessage());

            /* 실패로 답한다 — 보내는 쪽이 다시 보내 주어야 동의가 유실되지 않는다.
               담는 자리가 멈춘 것은 우리 사정이고, 그 사이 받은 동의는 그쪽에만 있다. */
            return response()->json(['ok' => false, 'message' => '처리 중 오류가 발생했습니다.'], 500);
        }

        $답 = [
            'ok'         => true,
            'id'         => $동의->id,
            'created'    => $새것인가,
            'patient_id' => $동의->patient_id,
            'signed'     => $동의->서명있나(),
            'message'    => $동의->서명있나()
                ? '서명까지 받은 동의로 담았습니다.'
                : '동의를 담았습니다(서명 없음).',
        ];

        WebhookLogger::finish($기록, ok: true, status: 200, response: $답,
            ref: $동의->name . ' ' . $동의->phone);

        return response()->json($답);
    }

    /**
     * 보낸 값을 거른다.
     *
     * 동의 항목은 「동의함 / 동의하지 않음」 두 글자 가운데 하나다. 다른 글이 오면
     * 받지 않는다 — 「Y」나 「1」을 그대로 담으면 `required_agreed` 가 영영 거짓이 되어,
     * 받은 동의가 안 받은 것으로 읽힌다.
     */
    private function 고르게(Request $request): array
    {
        $동의값 = ['nullable', 'string', 'in:동의함,동의하지 않음'];

        return $request->validate([
            'type'            => 'required|in:catheter,stoma',
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
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

            'agree_general'             => $동의값,
            'agree_sensitive'           => $동의값,
            'agree_third_party'         => $동의값,
            'agree_marketing'           => $동의값,
            'agree_marketing_sensitive' => $동의값,
            'agree_third_sensitive'     => $동의값,
            'agree_ads'                 => $동의값,

            /* 서명이 없어도 받는다 — 동의만 받은 건도 개인정보 동의로는 쓸 수 있다.
               위임까지 인정하는 것은 서명이 있을 때뿐이다(DelegationGate). */
            'signature'    => 'nullable|string|max:' . self::서명최대,
            'signed_at'    => 'nullable|date',
            'submitted_at' => 'nullable|date',

            'source_ref'  => 'nullable|string|max:60',
            'source_host' => 'nullable|string|max:120',
        ], [
            'type.in'       => '유형은 catheter 또는 stoma 여야 합니다.',
            'name.required' => '성명이 없습니다.',
            'phone.required' => '연락처가 없습니다.',
            'signature.max' => '서명 그림이 너무 큽니다.',
        ]);
    }

    /**
     * 담거나 고친다.
     *
     * @return array{0: PrivacyConsent, 1: bool} 담긴 줄과 「새로 담았는가」
     */
    private function 담기(array $값, Request $request): array
    {
        $서명 = $this->서명다듬기($값['signature'] ?? null);
        $보낸자리 = $this->보낸자리($값, $request);

        $적을것 = [
            'type'   => $값['type'],
            /* 공개 동의서에서 온 것임을 갈래로 남긴다 — 모바일 동의와 가려야 한다 */
            'source' => 'web',
            'name'   => trim($값['name']),
            'phone'  => trim($값['phone']),
            'signature_data' => $서명,
            'signed_at'      => $서명 === null
                ? null
                : ($값['signed_at'] ?? $값['submitted_at'] ?? now()),
            'submitted_at'   => $값['submitted_at'] ?? now(),
            'source_ref'     => $값['source_ref'] ?? null,
            'source_host'    => $보낸자리,
            'ip'             => $request->ip(),
            'user_agent'     => mb_substr((string) $request->userAgent(), 0, 300),
            /* 보낸 본문을 그대로 둔다 — 뒤에 칸이 늘어도 지난 건을 되살려 읽을 수 있다.
               서명 그림은 빼고 담는다. 같은 그림을 두 칸에 쥐면 줄마다 수십KB 가 겹친다. */
            'extra'          => collect($값)->except(['signature'])->all(),
        ];

        foreach ([
            'phone2', 'email', 'zip', 'addr1', 'addr2', 'insurance', 'support_qualify',
            'birth', 'product', 'hospital', 'surgery_date', 'stoma_type', 'stoma_kind',
            'agree_general', 'agree_sensitive', 'agree_third_party',
            'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive', 'agree_ads',
        ] as $칸) {
            if (array_key_exists($칸, $값)) {
                $적을것[$칸] = $값[$칸];
            }
        }

        return DB::transaction(function () use ($적을것, $값, $보낸자리) {
            $이미 = null;

            /* 보낸 쪽의 줄 번호가 있으면 그것으로 맞춘다 — 다시 와도 한 줄이다 */
            if (! empty($값['source_ref'])) {
                $이미 = PrivacyConsent::where('source_ref', $값['source_ref'])
                    ->where('source_host', $보낸자리)
                    ->lockForUpdate()
                    ->first();
            }

            if ($이미) {
                /* 거래처 연결은 담당자가 손으로 맺어 둔 것일 수 있다 — 덮지 않는다 */
                $이미->fill($적을것)->save();

                return [$이미->refresh(), false];
            }

            $동의 = new PrivacyConsent($적을것);
            $동의->patient_id = $this->거래처찾기($적을것['name'], $적을것['phone']);
            $동의->save();

            return [$동의, true];
        });
    }

    /**
     * 서명 그림을 다듬는다 — 쓸 수 없는 것은 없는 것으로 본다.
     *
     * 빈 서명판을 그대로 보내면 아주 짧은 글이 온다. 그것을 받아 두면 위임 관문이
     * 열리고 **빈 서명란으로 위임장이 공단에 나간다.** 꼴과 길이를 함께 본다.
     */
    private function 서명다듬기(?string $값): ?string
    {
        $글 = trim((string) $값);

        if ($글 === '') {
            return null;
        }

        /* data URI 로 왔든 알맹이만 왔든, 담는 꼴은 하나로 맞춘다 */
        if (! str_starts_with($글, 'data:')) {
            $글 = 'data:image/png;base64,' . $글;
        }

        if (! preg_match('~^data:image/(png|jpeg|jpg);base64,([A-Za-z0-9+/=\s]+)$~', $글, $m)) {
            return null;
        }

        $알맹이 = preg_replace('/\s+/', '', $m[2]);

        /* 1,000자보다 짧은 PNG 는 손가락이 닿지 않은 빈 판이다 (2026-10-02 확인:
           실제 서명 한 장은 20~60KB, base64 로 3만 자가 넘는다). */
        if (strlen($알맹이) < 1000) {
            return null;
        }

        return 'data:image/' . ($m[1] === 'jpg' ? 'jpeg' : $m[1]) . ';base64,' . $알맹이;
    }

    /** 어디서 보낸 것인가 — 적어 보냈으면 그것을, 없으면 부른 자리를 적는다 */
    private function 보낸자리(array $값, Request $request): string
    {
        $적힌것 = trim((string) ($값['source_host'] ?? ''));

        if ($적힌것 !== '') {
            return mb_substr($적힌것, 0, 120);
        }

        return mb_substr((string) ($request->header('Origin')
            ?: $request->header('Referer')
            ?: $request->ip()), 0, 120);
    }

    /**
     * 이름과 전화번호로 이미 있는 거래처를 찾는다 — 없으면 null.
     *
     * **새로 만들지 않는다.** 밖에서 온 글로 거래처를 세우면 동명이인과 오기입이
     * 그대로 들어온다. 못 찾으면 비워 두고, 담당자가 화면에서 맺는다.
     *
     * 둘 이상 걸리면 맺지 않는다 — 동명이인에 같은 번호를 적어 둔 줄이 있을 수 있고,
     * 그때 아무 쪽에나 붙이면 남의 동의가 된다.
     */
    private function 거래처찾기(string $이름, string $전화): ?int
    {
        $숫자 = preg_replace('/\D/', '', $전화);

        if ($숫자 === '' || $이름 === '') {
            return null;
        }

        /* (E) 는 사업부 표시다 — 동의서에 적히는 이름에는 없다 */
        $맨이름 = trim(preg_replace('/^\s*\(E\)\s*/u', '', $이름));

        $찾은것 = Patient::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(mobile,''),'-',''),' ',''),'+','') = ?", [$숫자])
            ->whereRaw("TRIM(REPLACE(COALESCE(name,''),'(E)','')) = ?", [$맨이름])
            ->limit(2)
            ->pluck('id');

        return $찾은것->count() === 1 ? (int) $찾은것->first() : null;
    }
}
