<?php

namespace App\Support;

use App\Models\OrderAmendment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * 정정한 주문을 목록에서 세 줄로 펴 준다 (2026-09-27 지시 · 확인사항 20).
 *
 * 정정은 주문을 **제자리에서** 고친다. 그래서 목록에는 마지막 내용 한 줄만 서고,
 * 「얼마가 오갔는가」를 그 화면에서 셀 수 없다. 재무가 보는 Finance 는 진작
 * 세 줄로 펴고 있었는데(`FinanceController::amendRows`) 청구 관리ㆍ정산/회계는
 * 한 줄뿐이었다 — 같은 주문을 보는데 화면마다 말이 달랐다.
 *
 * 펴는 셈은 한 곳에 둔다. 화면마다 칸이 다르므로 **줄을 만드는 일은 화면이 하고**,
 * 여기서는 무엇을 몇 줄로 펼지와 그 이름만 정한다.
 *
 * 정정 전 값은 `order_amendments` 에 통째로 남는다 — 수량ㆍ단가ㆍ본인부담ㆍ기관부담ㆍ
 * 그때의 판매번호ㆍ그때 낸 세금계산서와 현금영수증 번호까지. 그 표가 없으면
 * 「원 주문 / 취소 / 정정 후」 세 줄이 설 수 없다.
 */
final class OrderAmendLines
{
    /**
     * 주문 묶음에 달린 정정 이력 — 주문 id 로 묶어 돌려준다.
     *
     * 차례는 나중 것이 먼저다(seq 내림차순). 목록이 최신 먼저라, 지금 값 아래로
     * 물러난 것들이 가까운 것부터 따라오는 것이 눈에 맞다.
     *
     * @param  Collection  $orders  Order 묶음
     * @return Collection<int, Collection<int, OrderAmendment>>
     */
    public static function 모으기(Collection $orders): Collection
    {
        if ($orders->isEmpty() || ! Schema::hasTable('order_amendments')) {
            return collect();
        }

        return OrderAmendment::whereIn('order_id', $orders->pluck('id'))
            ->orderByDesc('seq')
            ->get()
            ->groupBy('order_id');
    }

    /** 정정으로 물러난 그때의 주문 줄 */
    public static function 원주문말(OrderAmendment $a): string
    {
        return "정정 {$a->seq}차 · 원 주문";
    }

    /** 그 줄을 무른 줄 — 금액ㆍ수량을 음수로 세워 합이 맞는다 */
    public static function 취소말(OrderAmendment $a): string
    {
        return "정정 {$a->seq}차 · 취소";
    }
}
