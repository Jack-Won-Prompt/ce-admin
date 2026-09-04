<?php

namespace App\Support;

use App\Models\Order;

/**
 * 세무 발행에 실을 품목 — 세금계산서의 줄과 현금영수증의 품명(2026-09-03 확정).
 *
 * 여태 둘 다 주문의 대표 제품 하나만 실었다(product_name · 수량 1). 주문에 제품이
 * 여러 줄이면 첫 줄 이름만 나가고 나머지는 금액에만 섞여, 받은 쪽에서는 무엇을 산
 * 것인지 알 수 없었다.
 *
 * 장비코드도 함께 싣는다. 공단 요양기관정보마당은 우리 품번이 아니라 그 번호로
 * 조회하는데, 세무 자료에 없으니 대조할 때마다 다른 표를 열어야 했다.
 *
 *   세금계산서  품목마다 한 줄. 규격 칸에 장비코드를 적는다.
 *   현금영수증  품목 줄이 없다 — 품명 한 칸뿐이라 「제품명 (장비코드) 외 2건」으로 적는다.
 */
final class IssueLines
{
    /** 팝빌 품명 칸의 길이 — 넘기면 저쪽에서 잘린다 */
    private const NAME_MAX = 100;

    /**
     * 세금계산서 품목 줄 — 팝빌에 보낼 꼴로.
     *
     * @param  object  $svc  TaxinvoiceService — newDetail() 을 부른다
     * @return array<int, object>
     */
    public static function taxDetails(Order $order, int $supply, int $vat, object $svc): array
    {
        $out = [];

        foreach (self::split($order, $supply, $vat) as $i => $r) {
            $d = $svc->newDetail();
            $d->serialNum  = $i + 1;
            $d->itemName   = $r['name'];
            $d->spec       = $r['device'];
            $d->qty        = (string) $r['qty'];
            $d->unitCost   = (string) $r['unit'];
            $d->supplyCost = (string) $r['supply'];
            $d->tax        = (string) $r['vat'];

            $out[] = $d;
        }

        return $out;
    }

    /**
     * 발행할 품목 줄에 금액을 나눠 담는다 — 신고와 종이가 같은 값을 쓰게 하는 자리.
     *
     * 담당자가 적은 공급가ㆍ세액을 품목의 값에 따라 나눈다. 품목의 금액을 그대로
     * 쓰면 안 된다 — 청구전략이 정한 몫(이를테면 90%)만 발행하는 건이 있어 합이
     * 어긋난다. 나머지는 마지막 줄이 떠안아 합이 정확히 맞는다.
     *
     * 단가는 그 줄의 공급가를 수량으로 나눈 값이다. 예전에는 줄이 하나면 수량을
     * 그대로 두고 단가 자리에 공급가를 통째로 적었다 — 「수량 180 · 단가 331,364」
     * 처럼 곱이 맞지 않는 줄이 국세청에 그대로 신고되었다.
     *
     * @return array<int, array{name:string, device:string, qty:int, unit:int, supply:int, vat:int, amount:int}>
     */
    public static function split(Order $order, int $supply, int $vat): array
    {
        $rows = self::rows($order);

        if (! $rows) {
            $rows = [[
                'name'   => (string) ($order->product_name ?: '처방약'),
                'device' => '',
                'qty'    => 1,
                'amount' => 0,
            ]];
        }

        $weights = array_map(fn ($r) => max(0, $r['amount']), $rows);
        $sum     = array_sum($weights);

        /* 값이 하나도 없으면(단가가 안 적힌 옛 건) 고르게 나눈다 — 0 으로 나누지 않는다 */
        if ($sum <= 0) {
            $weights = array_fill(0, count($rows), 1);
            $sum     = count($rows);
        }

        $out        = [];
        $leftSupply = $supply;
        $leftVat    = $vat;
        $last       = count($rows) - 1;

        foreach ($rows as $i => $r) {
            $s = ($i === $last) ? $leftSupply : (int) round($supply * $weights[$i] / $sum);
            $t = ($i === $last) ? $leftVat    : (int) round($vat    * $weights[$i] / $sum);

            $leftSupply -= $s;
            $leftVat    -= $t;

            $qty = max(1, (int) $r['qty']);

            $out[] = [
                'name'   => $r['name'],
                'device' => $r['device'],
                'qty'    => $qty,
                'unit'   => (int) round($s / $qty),
                'supply' => $s,
                'vat'    => $t,
                'amount' => $r['amount'],
            ];
        }

        return $out;
    }

    /**
     * 현금영수증 품명 — 「제품명 (장비코드) 외 2건」.
     *
     * 팝빌 현금영수증에는 품목 줄이 없다. 한 칸에 담아야 하므로 첫 제품을 적고
     * 나머지는 개수로 알린다.
     */
    public static function cashItemName(Order $order): string
    {
        $rows = self::rows($order);

        if (! $rows) {
            return $order->product_name ?: '처방약';
        }

        $name = $rows[0]['name'];
        if ($rows[0]['device'] !== '') {
            $name .= ' (' . $rows[0]['device'] . ')';
        }

        $more = count($rows) - 1;
        if ($more > 0) {
            $name .= " 외 {$more}건";
        }

        return mb_substr($name, 0, self::NAME_MAX);
    }

    /**
     * 발행에 실을 품목 줄 — 화면이 읽어 채운다(전자세금계산서 손 발행 자리).
     *
     * @return array<int, array{name:string, device:string, qty:int, amount:int}>
     */
    public static function rowsFor(Order $order): array
    {
        return self::rows($order);
    }

    /**
     * 발행에 실을 품목 줄.
     *
     * 주문 줄이 정본이다. 줄이 없으면 처방 줄로, 그것도 없으면 주문에 적힌 대표
     * 제품 하나로 대신한다(품목 표가 생기기 전에 만들어진 건).
     *
     * @return array<int, array{name:string, device:string, qty:int, amount:int}>
     */
    private static function rows(Order $order): array
    {
        $order->loadMissing(['items', 'prescription.items']);

        $src = $order->items->isNotEmpty()
            ? $order->items
            : ($order->prescription?->items ?? collect());

        $rows = $src
            ->filter(fn ($i) => trim((string) $i->product_name) !== '')
            ->map(function ($i) {
                $qty  = max(1, (int) ($i->quantity ?? 1));
                $unit = (int) ($i->insurance_price ?: $i->product_price ?: 0);

                return [
                    'name'   => mb_substr(trim((string) $i->product_name), 0, self::NAME_MAX),
                    'device' => (string) (DeviceCode::for($i->product_code) ?? ''),
                    'qty'    => $qty,
                    'amount' => $qty * $unit,
                ];
            })
            ->values()
            ->all();

        if ($rows) {
            return $rows;
        }

        if (trim((string) $order->product_name) === '') {
            return [];
        }

        return [[
            'name'   => mb_substr(trim((string) $order->product_name), 0, self::NAME_MAX),
            'device' => (string) (DeviceCode::for($order->product_code) ?? ''),
            'qty'    => max(1, (int) ($order->quantity ?? 1)),
            'amount' => (int) ($order->quantity ?? 1) * (int) ($order->unit_price ?? 0),
        ]];
    }
}
