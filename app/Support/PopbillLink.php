<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * 팝빌에 발행한 건이 우리 어느 주문의 것인가 (요청서 6쪽, 2026-08-31).
 *
 * 현금영수증ㆍ전자세금계산서 화면은 팝빌 목록을 그대로 비춘다. 거기에 주문ㆍ처방 칸을
 * 세우려면 주문과 이어야 하는데, 잇는 열쇠가 둘로 갈린다.
 *
 *   현금영수증  팝빌이 orderNumber 를 그대로 돌려준다 — 발행할 때 실어 보낸 값이다
 *   세금계산서  그런 칸이 없다. 관리번호에 주문 id 를 심어 두었으니 그것을 읽는다
 *
 * 관리번호 규칙 — 접두사 + 발행일(Ymd) + 주문 id 여섯 자리.
 *
 *   TI20260813000010    세금계산서 · 주문 #10
 *   CR20260825000034    현금영수증 · 주문 #34
 *   CRC20260825000034   현금영수증 취소
 *   TI20260508000025D   뒤에 글자가 붙은 것도 있다(같은 날 같은 주문을 다시 발행)
 *   T1778230489         옛 형식 — 규칙 밖이라 잇지 못한다
 *
 * 규칙에서 벗어난 것과 팝빌에서 직접 발행한 것은 이을 주문이 없다. 짐작으로 붙이지
 * 않는다 — 남의 주문에 붙은 계산서는 정산을 통째로 어긋나게 한다.
 */
class PopbillLink
{
    /** 접두사 + 발행일 여덟 자리 + 주문 id 여섯 자리. 뒤에 무엇이 붙어도 좋다. */
    private const MGT_KEY = '/^(?:TI|CRC|CR)(\d{8})(\d{6})/';

    /**
     * 이 발행 건의 주문 id — 못 찾으면 null.
     *
     * 주문번호가 있으면 그것을 먼저 본다. 팝빌이 돌려준 값이라 파싱보다 확실하다.
     */
    public static function orderIdFor(?string $mgtKey, ?string $orderNumber = null): ?int
    {
        if ($orderNumber !== null && trim($orderNumber) !== '') {
            $id = Order::where('order_number', trim($orderNumber))->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return self::fromMgtKey($mgtKey);
    }

    /**
     * 관리번호에 심어 둔 주문 id.
     *
     * 있는 주문인지 확인하고 돌려준다 — 여섯 자리가 우연히 숫자로 읽히는 옛 번호가
     * 섞여 있어, 읽히는 대로 믿으면 없는 주문을 가리킨다.
     *
     * **있는 것만으로는 모자라다** (2026-10-15).
     *
     * 주문 id 는 다시 쓰인다. 시험 자료를 비우는 명령(ResetBusinessData 들)이 `orders`
     * 를 비우면 자동 번호가 처음부터 매겨지고, 그러면 8월에 발행한 관리번호
     * `TI20260824000034` 가 **10월에 선 남의 주문 #34** 를 가리킨다. 실제로 그렇게 세
     * 줄이 엉뚱한 환자에게 붙었고, 그 주문들은 삭제까지 막혀 있었다.
     *
     * 그래서 **발행일에 그 주문이 이미 있었는지**까지 본다. 주문보다 먼저 발행된 것은
     * 그 주문의 것이 아니다.
     *
     * 지워진 주문도 함께 본다(withTrashed). 발행 기록은 주문을 지워도 남아야 하므로,
     * 지운 주문을 없는 것으로 보면 멀쩡히 이어져 있던 건이 제 자리를 잃는다.
     */
    public static function fromMgtKey(?string $mgtKey): ?int
    {
        if (!$mgtKey || !preg_match(self::MGT_KEY, trim($mgtKey), $m)) {
            return null;
        }

        $발행일 = $m[1];
        $id     = (int) $m[2];

        if ($id <= 0) {
            return null;
        }

        $생긴때 = Order::withTrashed()->whereKey($id)->value('created_at');

        if (!$생긴때) {
            return null;
        }

        return Carbon::parse($생긴때)->format('Ymd') <= $발행일 ? $id : null;
    }
}
