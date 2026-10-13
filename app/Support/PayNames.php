<?php
// app/Support/PayNames.php
// 토스 결제창에 실어 보내는 이름 — 결제수단에 따라 뜻이 다르다.

namespace App\Support;

use App\Models\Order;
use App\Models\Patient;
use App\Services\TossPayments\VirtualAccountService;

/**
 * 토스에 보내는 `customerName` 을 짓는 한 곳.
 *
 * 같은 칸인데 고른 결제수단에 따라 **뜻이 다르다**.
 *
 *   · 가상계좌  → **예금주**. 환자가 은행 앱에서 보는 「받는 사람」.
 *   · 카드ㆍ간편결제 → **구매자명**. 토스 「신용ㆍ체크카드 조회」에 찍히는 이름.
 *
 * **둘 다 환자 이름이다** (2026-10-13 지시 「가상계좌는 예금주가 환자명이 되어야 한다」).
 *
 * 2026-10-12 에는 가상계좌 예금주를 **상호**로 바꿨었다(db3eda42). 환자 이름을 보내면
 * 「내가 나에게 보내나」로 읽혀 입금을 망설인다는 까닭이었다. 그 지시를 2026-10-13 에
 * 되돌린다 — 예금주는 환자명이다.
 *
 * 그때 고치려던 것은 사실 이름에 붙은 **동명이인 꼬리**였다(홍영철**A**). 그 꼬리는
 * Patient::실명 이 떼므로, 상호로 바꾸지 않아도 「홍영철」로 나간다.
 *
 * 그 변경은 한 값을 모든 수단에 실어 보내게 되어 있어 카드ㆍ간편결제 23건의 구매자명까지
 * 「콜로플라스트 코리아 주식회사」로 찍혔다. 짓는 자리가 화면 안에만 있어 다른 자리에서
 * 같은 값을 쓸 수 없었던 탓이다.
 *
 * 결제 화면과, 가상계좌를 발급하는 자리와, 보낸 값을 적어 두는 자리가 **이 한 곳**을
 * 함께 쓴다.
 */
class PayNames
{
    /** 마지막 기댈 곳 — 상호. 환자 이름을 하나도 못 찾았을 때만 쓴다. */
    public static function 상호(): string
    {
        return VirtualAccountService::예금주();
    }

    /**
     * 토스에 보내는 이름 — 가상계좌면 예금주, 그 밖이면 구매자명이다.
     *
     * 둘 다 **환자 이름**이다 (2026-10-13 지시).
     *
     * 동명이인 꼬리(김옥자**C**)는 우리가 붙인 표시이지 사람 이름이 아니라 밖으로
     * 내보내지 않는다 (2026-10-06 · SR #90ㆍ#102).
     *
     * 거래처가 없으면 받는 사람 이름을, 그것도 없으면 상호를 쓴다 — 빈 이름으로
     * 결제창을 열면 토스가 되돌리고, 가상계좌는 예금주 없이 발급되지 않는다.
     */
    public static function 환자이름(?Order $order): string
    {
        foreach ([$order?->patient?->name, $order?->shipping_recipient] as $후보) {
            $이름 = Patient::실명($후보);

            if ($이름 !== '') {
                return $이름;
            }
        }

        return self::상호();
    }

    /**
     * 결제창과 가상계좌 발급에 실어 보내는 그 값.
     *
     * 수단을 받아 두는 것은 **뜻이 다르기 때문**이다 — 가상계좌에서는 예금주가 되고
     * 카드에서는 구매자명이 된다. 지금은 둘 다 환자 이름이라 값이 같지만, 한쪽만
     * 바뀌는 날 고칠 자리가 여기 하나로 남는다.
     */
    public static function 토스에보낼이름(?Order $order, ?string $수단 = null): string
    {
        return self::환자이름($order);
    }

    /** 토스가 쓰는 말과 우리 화면이 쓰는 말을 함께 받는다 */
    public static function 가상계좌인가(?string $수단): bool
    {
        return in_array(trim((string) $수단), ['VIRTUAL_ACCOUNT', '가상계좌'], true);
    }
}
