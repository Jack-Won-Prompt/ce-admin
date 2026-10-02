<?php

namespace App\Services;

use App\Models\PrivacyConsent;
use App\Support\WebhookLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 공개 동의서에서 받은 동의ㆍ서명을 운영 서버로 건넨다 (2026-10-02 지시).
 *
 * 「동의 하면 웹훅으로 운영 서버에 보내고 운영 서버 웹훅 실행 시 서명동의 완료
 *   항목에 업데이트도 가능하게」
 *
 * ## 왜 보내야 하는가
 *
 * 공개 동의서는 `www.ceadmin.co.kr`(3.34.53.36)에 있고 운영은 75.2.99.52 다. 같은
 * 코드가 돌지만 **DB가 다르다** — 여기 담긴 동의는 운영 화면에 보이지 않는다.
 *
 * ## 보내는 일이 받는 일을 막아서는 안 된다
 *
 * 환자는 이미 서명을 마쳤다. 운영 서버가 멈춰 있다고 그 화면에 잘못을 띄우면,
 * 환자는 자기가 뭘 잘못한 줄 알고 다시 쓴다. **담는 것이 먼저고 보내는 것은 그 뒤다.**
 * 보내다 실패해도 조용히 자취만 남긴다 — 어차피 줄은 이쪽 DB에 남아 있어, 주소를
 * 고친 뒤 다시 보내면 된다.
 *
 * ## 같은 것을 두 번 보내도 한 줄이다
 *
 * 받는 쪽이 `source_ref`(이 줄의 번호)와 `source_host`(보낸 자리)로 맞춘다. 그래서
 * 다시 보내는 것이 안전하다 — 실패한 건을 손으로 다시 보내도 겹치지 않는다.
 */
class PrivacyConsentForwarder
{
    /** 운영 서버가 늦더라도 환자를 오래 기다리게 하지 않는다 (초) */
    private const 기다림 = 8;

    /**
     * 한 줄을 보낸다. 보냈으면 true.
     *
     * 터지지 않는다 — 부르는 자리(공개 폼의 submit)가 이것 때문에 멈추면 안 된다.
     */
    public function 보내기(PrivacyConsent $동의): bool
    {
        $주소 = $this->주소();

        if ($주소 === null) {
            /* 보낼 곳이 정해지지 않았다 — 옛 서버가 아니라 운영 서버 자신에서
               이 폼을 열었을 때가 그렇다. 그때는 이미 같은 DB에 담겼으니 보낼 일이 없다. */
            return false;
        }

        $본문 = $this->본문($동의);
        $자취 = $동의->name . ' ' . $동의->phone;
        $시작 = microtime(true);

        /* 보낸 것은 **한 번에** 적는다 — `WebhookLogger::outbound` 는 결과까지 받아
           한 줄을 세운다(받는 쪽의 inbound + finish 와 꼴이 다르다). */
        $적기 = fn (bool $됐나, ?int $코드, mixed $답, ?string $탈) => WebhookLogger::outbound(
            provider: 'privacy',
            eventCode: 'consent',
            url: $주소,
            payload: $본문,
            ok: $됐나,
            status: $코드,
            response: $답,
            error: $탈,
            durationMs: (int) round((microtime(true) - $시작) * 1000),
            ref: $자취,
        );

        try {
            $답 = Http::timeout(self::기다림)
                ->connectTimeout(4)
                ->acceptJson()
                ->withHeaders(['X-Webhook-Key' => (string) config('privacy_forward.key')])
                ->post($주소, $본문);

            if ($답->successful()) {
                $적기(true, $답->status(), $답->json() ?? $답->body(), null);

                return true;
            }

            Log::warning('[개인정보 동의 전달] 운영 서버가 받지 않았다', [
                'status' => $답->status(),
                'body'   => mb_substr($답->body(), 0, 300),
                'id'     => $동의->id,
            ]);

            $적기(false, $답->status(), $답->body(), '운영 서버가 거절했습니다');

            return false;
        } catch (\Throwable $e) {
            Log::error('[개인정보 동의 전달] 보내지 못했다', [
                'error' => $e->getMessage(), 'id' => $동의->id,
            ]);

            $적기(false, null, null, $e->getMessage());

            return false;
        }
    }

    /**
     * 보낼 주소 — 열쇠를 길에 싣는다.
     *
     * 열쇠를 머리말로도 함께 보낸다(`X-Webhook-Key`). 받는 쪽이 길에 든 것을 먼저 보고
     * 없으면 머리말을 보므로, 둘 중 어느 쪽이 중간에서 깎여도 닿는다.
     */
    private function 주소(): ?string
    {
        $바탕 = rtrim((string) config('privacy_forward.url'), '/');

        if ($바탕 === '') {
            return null;
        }

        $열쇠 = trim((string) config('privacy_forward.key'));

        return $열쇠 === ''
            ? $바탕 . '/webhooks/privacy-consent'
            : $바탕 . '/webhooks/privacy-consent/' . rawurlencode($열쇠);
    }

    /** 보낼 값 — 받는 쪽이 거르는 이름과 똑같이 맞춘다 */
    private function 본문(PrivacyConsent $동의): array
    {
        $값 = [
            'type'   => $동의->type,
            'name'   => $동의->name,
            'phone'  => $동의->phone,
            'phone2' => $동의->phone2,
            'email'  => $동의->email,
            'zip'    => $동의->zip,
            'addr1'  => $동의->addr1,
            'addr2'  => $동의->addr2,

            'insurance'       => $동의->insurance,
            'support_qualify' => $동의->support_qualify,
            'birth'           => $동의->birth,
            'product'         => $동의->product,
            'hospital'        => $동의->hospital,
            'surgery_date'    => $동의->surgery_date,
            'stoma_type'      => $동의->stoma_type,
            'stoma_kind'      => $동의->stoma_kind,

            'signature'    => $동의->signature_data,
            'signed_at'    => $동의->signed_at?->toIso8601String(),
            'submitted_at' => $동의->submitted_at?->toIso8601String(),

            /* 다시 보내도 한 줄이 되게 — 이 줄의 번호와 보낸 자리를 함께 적는다 */
            'source_ref'  => (string) $동의->id,
            'source_host' => (string) (parse_url((string) config('app.url'), PHP_URL_HOST)
                             ?: config('app.url')),
        ];

        foreach ([
            'agree_general', 'agree_sensitive', 'agree_third_party',
            'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive', 'agree_ads',
        ] as $칸) {
            $값[$칸] = $동의->{$칸};
        }

        /* 빈 값은 보내지 않는다 — 받는 쪽의 `nullable` 을 거치긴 하지만, 본문이
           짧아야 자취를 읽기 쉽다. 서명은 비어도 칸을 남긴다(없다는 뜻이 뜻이다). */
        return array_filter($값, fn ($v, $k) => $k === 'signature' || ($v !== null && $v !== ''),
            ARRAY_FILTER_USE_BOTH);
    }
}
