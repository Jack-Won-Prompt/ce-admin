<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 위드웍스로 보낸 것을 그대로 이메일로도 남긴다 (2026-09-07 지시).
 *
 * 연계가 잘 갔는지는 지금 저쪽 화면에 들어가 눈으로 찾아야 안다. 무엇을 어떤
 * 값으로 보냈는지는 로그에만 남고, 로그는 서버에 들어가야 읽는다. 그래서
 * 「보냈다는데 저쪽에 없다」가 생기면 무엇이 틀렸는지 짚는 데 한참 걸렸다 —
 * 실제로 반품 사유 칸(udf6)을 남의 자리에 얹어 두고 여러 날을 흘려보냈다.
 *
 * 보낸 것을 그대로 받아 두면 저쪽 화면과 나란히 놓고 견줄 수 있다.
 *
 * **업무를 막지 않는다.** 메일이 안 나가도 주문은 이미 갔다. 여기서 던지면
 * 성공한 연계가 실패로 보이므로, 어떤 잘못이든 삼키고 로그에만 남긴다.
 */
class WithworksNotice
{
    /**
     * @param  string  $what     무엇을 보냈는가 (예: '주문 등록', '반품 등록')
     * @param  string  $endpoint 어느 자리로 (예: 'so_store')
     * @param  array   $payload  보낸 값 그대로
     * @param  mixed   $result   저쪽이 돌려준 것 (배열이면 그대로, 아니면 문자열로)
     */
    public static function sent(string $what, string $endpoint, array $payload, $result = null): void
    {
        $to = trim((string) config('web.withworks_email'));

        if ($to === '') {
            return;
        }

        try {
            $body = static::body($what, $endpoint, $payload, $result);

            /* 본문만 있는 편지다. 서식을 갖춘 화면을 만들 까닭이 없다 — 읽는 사람은
               「무엇을 어떤 값으로 보냈나」만 보면 되고, 그것은 글로 충분하다. */
            Mail::raw($body, function ($m) use ($to, $what, $payload) {
                $m->to($to)->subject(static::subject($what, $payload));
            });
        } catch (\Throwable $e) {
            Log::warning('[위드웍스 연계 알림] 메일 실패 (업무는 계속 진행)', [
                'to' => $to, 'what' => $what, 'error' => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 제목에 우리 번호를 넣는다 — 받은 편지함에서 그 번호로 찾는다.
     *
     * 인코딩은 Symfony 에 맡긴다. 한때 우리가 먼저 base64 로 감싸 보았는데,
     * Symfony 가 그것을 다시 Q 인코딩으로 감싸 =?utf-8?Q?=3D=3FUTF-8=3FB… 처럼
     * 두 겹이 되었다 — 고치려던 깨짐을 도리어 만들었다. 손대지 않는 것이 맞다.
     *
     * 줄표(—)는 쓰지 않는다. 굳이 없어도 읽히고, 클라이언트마다 다루는 품이 다르다.
     */
    private static function subject(string $what, array $payload): string
    {
        $no = $payload['ce_order_number']
            ?? $payload['ce_return_number']
            ?? $payload['rx_number']
            ?? '';

        $raw = '[CE Admin] 위드웍스 ' . $what . ($no !== '' ? ' ' . $no : '');

        return $raw;
    }

    private static function body(string $what, string $endpoint, array $payload, $result): string
    {
        $json = static function ($v): string {
            return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                ?: '(옮길 수 없는 값)';
        };

        $lines = [
            $what,
            '',
            '보낸 때   : ' . now()->format('Y-m-d H:i:s'),
            '보낸 곳   : ' . rtrim((string) config('services.demoworks.api_url'), '/')
                           . '/api/v1/ce-admin/' . $endpoint,
            '보낸 사람 : ' . (auth()->user()?->name ?? '(자동)'),
            '',
            '── 보낸 값 ────────────────────────────',
            $json($payload),
            '',
            '── 저쪽이 돌려준 것 ───────────────────',
            is_array($result) ? $json($result) : (string) ($result ?? '(없음)'),
        ];

        return implode("\n", $lines);
    }
}
