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
 *   · 가상계좌  → **예금주**. 환자가 은행 앱에서 보는 「받는 사람」이라 상호여야 한다.
 *   · 카드ㆍ간편결제 → **구매자명**. 토스 「신용ㆍ체크카드 조회」에 찍히는 이름이라
 *     산 사람, 곧 환자 이름이어야 한다.
 *
 * 2026-10-12 에 가상계좌 예금주를 상호로 바꾸면서(db3eda42) 결제 화면이 한 값을 모든
 * 수단에 그대로 실어, 카드ㆍ간편결제 23건의 구매자명이 「콜로플라스트 코리아 주식회사」로
 * 찍혔다. 짓는 자리가 화면 안에만 있어 다른 자리에서 같은 값을 쓸 수 없었던 탓이다.
 *
 * 결제 화면과, 보낸 값을 적어 두는 자리가 **이 한 곳**을 함께 쓴다.
 */
class PayNames
{
    /** 가상계좌 예금주 — 상호 */
    public static function 예금주(): string
    {
        return VirtualAccountService::예금주();
    }

    /**
     * 카드ㆍ간편결제의 구매자명 — 산 사람의 이름.
     *
     * 동명이인 꼬리(김옥자**C**)는 우리가 붙인 표시이지 사람 이름이 아니라 밖으로
     * 내보내지 않는다 (2026-10-06 · SR #90ㆍ#102).
     *
     * 거래처가 없으면 받는 사람 이름을, 그것도 없으면 상호를 쓴다 — 빈 이름으로
     * 결제창을 열면 토스가 되돌린다.
     */
    public static function 구매자(?Order $order): string
    {
        foreach ([$order?->patient?->name, $order?->shipping_recipient] as $후보) {
            $이름 = Patient::실명($후보);

            if ($이름 !== '') {
                return $이름;
            }
        }

        return self::예금주();
    }

    /** 고른 결제수단에 맞는 이름 — 결제창에 실어 보내는 그 값이다 */
    public static function 토스에보낼이름(?Order $order, ?string $수단): string
    {
        return self::가상계좌인가($수단) ? self::예금주() : self::구매자($order);
    }

    /** 토스가 쓰는 말과 우리 화면이 쓰는 말을 함께 받는다 */
    public static function 가상계좌인가(?string $수단): bool
    {
        return in_array(trim((string) $수단), ['VIRTUAL_ACCOUNT', '가상계좌'], true);
    }
}
