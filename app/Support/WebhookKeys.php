<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 밖에서 두드리는 자리를 지키는 열쇠 (2026-09-18 지시).
 *
 * 위드웍스는 공유 비밀 헤더를, 토스는 서명을 준다. **팝빌은 아무것도 주지 않는다** —
 * 받는 자리가 로그인 없이 열려 있어 누구나 두드릴 수 있었다. 본문을 믿지 않고 팝빌에
 * 다시 물어 확인하므로 거짓 상태가 심기지는 않지만, 부르는 만큼 우리 쪽에서 팝빌로
 * 나간다. 막을 장치가 하나도 없는 것과 다르지 않다.
 *
 * 보내는 쪽이 서명을 주지 않을 때 쓰는 길은 하나다 — **등록할 주소를 우리가 정한다**는
 * 점을 쓴다. 주소에 열쇠를 박아 두고, 그 열쇠가 맞을 때만 받는다.
 *
 * 열쇠는 DB(settings 표)에 담는다. 파일에 두면 서버마다 갈리고, 바꾸려면 서버를
 * 만져야 한다. 비밀로 담으므로 표에는 암호화된 채로 눕는다.
 *
 * **켜는 일은 따로 둔다.** 열쇠가 생겼다고 곧바로 막으면, 이미 등록해 둔 옛 주소로
 * 오던 것이 그 순간 끊긴다 — 토스 가상계좌 입금이 그렇다. 주소를 먼저 바꿔 등록하고,
 * 그 다음에 「열쇠 확인」을 켠다.
 */
final class WebhookKeys
{
    /** 서비스마다 두드리는 자리 — 설정 화면이 주소를 그릴 때 쓴다 */
    public const 자리 = [
        'popbill' => [
            'fax'        => '팩스',
            'sms'        => '문자',
            'kakao'      => '알림톡',
            'taxinvoice' => '세금계산서',
            'cashbill'   => '현금영수증',
        ],
        'toss' => [
            '' => '결제ㆍ가상계좌',
        ],
    ];

    public const 열쇠칸 = 'webhook_key';
    public const 확인칸 = 'webhook_key_required';

    /** 주소에 열쇠를 태울 수 없을 때 쓰는 머리말 — 팝빌ㆍ토스는 길에 싣는다 */
    private const 머리말 = 'X-Webhook-Key';

    /**
     * 이 서비스의 열쇠. 없으면 그 자리에서 지어 DB 에 담는다.
     *
     * 지어 두기만 하고 막지는 않는다 — 막는 것은 「열쇠 확인」이 정한다. 미리 지어
     * 두어야 설정 화면이 등록할 주소를 보여 줄 수 있다.
     */
    public static function 열쇠(string $서비스): string
    {
        $값 = self::담긴열쇠($서비스);

        if ($값 !== null) {
            return $값;
        }

        /* 주소에 실리는 값이라 길에서 뜻이 갈리지 않는 글자만 쓴다 */
        $새것 = Str::lower(Str::random(40));

        try {
            $줄 = Setting::where('group', $서비스)->where('key', self::열쇠칸)->first()
                  ?? new Setting(['group' => $서비스, 'key' => self::열쇠칸]);
            $줄->setPlainValue($새것, secret: true);
            $줄->save();
        } catch (\Throwable $e) {
            Log::warning('[웹훅 열쇠] 담지 못했다', ['서비스' => $서비스, 'error' => $e->getMessage()]);
        }

        return $새것;
    }

    /** 담겨 있는 열쇠 — 없으면 null. 짓지 않는다 */
    public static function 담긴열쇠(string $서비스): ?string
    {
        $값 = Setting::where('group', $서비스)->where('key', self::열쇠칸)->first()?->plainValue();

        return ($값 !== null && $값 !== '') ? $값 : null;
    }

    /** 열쇠를 새로 짓는다 — 새어 나갔다고 볼 때 */
    public static function 새로짓기(string $서비스): string
    {
        Setting::where('group', $서비스)->where('key', self::열쇠칸)->delete();

        return self::열쇠($서비스);
    }

    /** 열쇠를 보고 막는가 */
    public static function 확인하나(string $서비스): bool
    {
        $줄 = Setting::where('group', $서비스)->where('key', self::확인칸)->first();

        return (string) ($줄?->plainValue() ?? '') === '1';
    }

    /**
     * 이 요청을 받아도 되는가.
     *
     * 길에 실린 것을 먼저 보고, 없으면 머리말ㆍ물음표 뒤를 본다 — 보내는 쪽이 주소
     * 꼴을 가릴 때가 있어 셋 다 열어 둔다.
     *
     * 「열쇠 확인」이 꺼져 있으면 그냥 통과시킨다. 켜기 전에 주소를 바꿀 틈을 주는
     * 것이 이 칸의 쓰임이다.
     */
    public static function 맞나(string $서비스, Request $요청, ?string $길에든것 = null): bool
    {
        if (! self::확인하나($서비스)) {
            return true;
        }

        $받은것 = trim((string) ($길에든것
            ?: ($요청->header(self::머리말) ?: $요청->query('key', ''))));

        /* 여기서는 짓지 않는다 — 막는 중에 새 열쇠를 지으면 등록해 둔 것과 어긋나
           까닭 모를 401 이 이어진다. 담긴 것이 없으면 그대로 막는다. */
        $담긴것 = self::담긴열쇠($서비스);

        if ($받은것 === '' || $담긴것 === null) {
            return false;
        }

        return hash_equals($담긴것, $받은것);
    }

    /** 이 서비스에 등록할 주소들 — 설정 화면이 그대로 띄운다 */
    public static function 주소들(string $서비스): string
    {
        $열쇠 = self::열쇠($서비스);
        $바탕 = rtrim((string) config('app.url'), '/');
        $줄   = [];

        foreach (self::자리[$서비스] ?? [] as $갈래 => $이름) {
            $줄[] = $이름 . ' · ' . $바탕 . self::길($서비스, $열쇠, $갈래);
        }

        return implode("\n", $줄);
    }

    private static function 길(string $서비스, string $열쇠, string $갈래): string
    {
        return match ($서비스) {
            'popbill' => "/popbill/webhook/{$열쇠}/{$갈래}",
            'toss'    => "/toss/webhook/{$열쇠}",
            default   => "/{$서비스}/webhook/{$열쇠}",
        };
    }
}
