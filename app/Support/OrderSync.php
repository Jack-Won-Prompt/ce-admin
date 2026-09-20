<?php
// app/Support/OrderSync.php
// 처방전에 딸린 주문 줄을 처방전과 나란히 둔다.

namespace App\Support;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;

/**
 * 저장하면 주문 관리에도 선다.
 *
 * 처방전 그림이 없어도, 제품을 아직 안 골랐어도 그렇다 — 주문 등록에서 저장한 건은
 * 곧 하나의 거래이고, 그것을 보는 자리가 주문 관리다. 예전에는 「주문 생성 및 연계」를
 * 눌러야만 줄이 생겨, 손대던 건이 어느 목록에도 없이 떠 있었다.
 *
 * 다만 **상담만 적어 둔 건은 아니다** (2026-09-14 지시). 한때는 그것도 여기 세웠는데,
 * 통화 한 번이 곧 한 거래는 아니다 — 상담을 두 번 적으면 주문이 둘로 갈라졌다.
 * 상담은 거래처 상세의 상담 이력에서 본다.
 *
 * 우리 쪽 줄만 세운다. 위드웍스로 보내는 것은 그 단추가 할 일이다 — 저장할 때마다
 * 창고로 주문이 날아가서는 안 된다.
 *
 * 주문 등록의 저장과 위임동의 서명이 함께 쓴다.
 */
class OrderSync
{
    /**
     * 줄이 없으면 세우기만 한다 — 있으면 손대지 않는다.
     *
     * 처방전이 담길 때마다 부른다(Prescription 모델의 saved). 그래서 값을 다시 맞추지는
     * 않는다 — 품목 줄을 지웠다 다시 쓰는 일을 저장마다 하면 헛일이 쌓인다. 값을 맞추는
     * 것은 주문 등록의 저장(updateOcr)처럼 그 일을 하려고 부른 자리의 몫이다.
     *
     * 빈 초안은 세우지 않는다. 아직 이름 한 자 없는 자리라 「손댈 차례」에 세울 것이 없다.
     *
     * **상담만 적어 둔 건도 세우지 않는다** (2026-09-14 지시).
     *
     * 위 글은 「상담만 받아 적어 둔 건이 어느 목록에도 없이 떠 있었다」는 까닭으로
     * 상담 건에도 주문 줄을 세웠다. 그런데 통화 한 번이 곧 한 거래는 아니다 —
     * 같은 사람에게 상담을 두 번 적으면 주문이 둘로 갈라져, 한 건을 올렸을 뿐인데
     * 주문 목록에 두 줄이 섰다. 상담은 상담 이력에서 보는 것이 제자리다.
     *
     * 잃는 것은 없다. 이 hook 은 저장마다 다시 오므로, 자료를 올리거나 병원ㆍ처방을
     * 적어 그 줄이 상담뿐이기를 그치는 순간 그때 주문이 선다.
     */
    public static function seed(Prescription $prescription): ?Order
    {
        if ($prescription->is_blank_draft
            || $prescription->상담만인가()
            || $prescription->order()->exists()) {
            return null;
        }

        /* 거래처가 붙지 않았으면 주문을 세우지 않는다 (2026-09-20 지시).

           여태 처방전이 저장되는 모든 길에서 이 자리가 주문을 세웠는데, 거래처가
           이어졌는지는 보지 않았다. 그래서 이름을 고르기 전에 한 번 저장만 해도
           주문번호가 발급되고 주문 관리에 **이름 없는 줄**로 남았다 — 2026-09-20
           기준 열세 건 가운데 여섯이 그런 껍데기였다.

           주문번호는 위드웍스ㆍ토스ㆍ팝빌ㆍ공단으로 나가는 대외 식별자다. 누구
           것인지 모르는 채 먼저 태울 번호가 아니다. 거래처가 붙은 뒤에 세운다 —
           그때 다시 저장하면 이 자리가 만든다. */
        if (! $prescription->patient_id) {
            return null;
        }

        return self::ensure($prescription);
    }

    public static function ensure(Prescription $prescription): ?Order
    {
        $order = $prescription->order()->first();

        /* 이미 창고로 보낸 주문은 손대지 않는다. 보낸 뒤에 고치는 일은 「주문 수정」이
           창고와 함께 해야 하는 일이라, 여기서 조용히 바꾸면 두 쪽이 어긋난다.

           **돈은 다르다.** 창고가 아는 것은 품목ㆍ수량ㆍ배송지이고 본인부담ㆍ공단부담은
           쓰지 않는다 — 그것은 우리 쪽 값이다. 자격이 바뀌면(일반 → 차상위경감)
           품목은 그대로인 채 누가 얼마를 내는가만 달라지는데, 여기서 통째로 물러서
           주문에는 옛 셈이 남았다. 정산ㆍ청구는 주문을 보므로, 처방전은 본인부담 0
           인데 주문은 40,500 을 받을 돈으로 들고 있었다(3차 4회 13번).

           금액만 따라간다. 품목 줄ㆍ제품ㆍ수량은 그대로 둔다. */
        if ($order && $order->withworks_so_no) {
            $items = $prescription->items;
            $copay = (float) $items->sum('patient_copay');
            $nhis  = (float) $items->sum('nhis_amount');

            if ((float) $order->patient_copay !== $copay || (float) $order->nhis_amount !== $nhis) {
                $order->update([
                    'nhis_amount'   => $nhis,
                    'patient_copay' => $copay,
                    'total_amount'  => $copay,
                ]);

                activity()->causedBy(Auth::user())->performedOn($order)->log(sprintf(
                    '자격이 바뀌어 주문 금액을 다시 맞췄습니다 — 본인부담 %s원 · 공단 %s원',
                    number_format($copay), number_format($nhis)));
            }

            return $order;
        }

        $items = $prescription->items;
        $first = $items->first();
        $copay = (float) $items->sum('patient_copay');
        $qty   = (int) $items->sum('quantity');

        $summary = [
            'patient_id'    => $prescription->patient_id,
            // 제품명은 비울 수 없는 칸이다. 아직 고른 것이 없으면 「-」로 둔다.
            'product_name'  => $first?->product_name ?: ($prescription->product_name ?: '-'),
            'product_code'  => $first?->product_code ?: $prescription->product_code,
            'quantity'      => $qty ?: 1,
            'unit_price'    => (float) ($first?->insurance_price ?? $first?->product_price ?? 0),
            'nhis_amount'   => (float) $items->sum('nhis_amount'),
            'patient_copay' => $copay,
            // 배송비는 없다(2026-09-03 확정) — 받을 돈은 본인부담 그것뿐이다
            'total_amount'  => $copay,
        ];

        /* 추가 주문이 선 뒤에는 원 주문을 처방전으로 덮지 않는다 (2026-09-14 확인요청 4쪽).

           여태 원 주문은 저장할 때마다 처방전을 그대로 베껴 왔다. 주문이 하나뿐일 때는
           그것이 맞았다 — 처방전의 제품이 곧 주문의 제품이었다.

           수량을 나눠 사기 시작하면 달라진다. 처방전은 **총 처방**을 담고, 원 주문은 그
           가운데 **먼저 산 몫**을 담는다. 여기서 베끼면 먼저 산 몫이 총 처방으로 덮여,
           나눠 둔 것이 저장 한 번에 사라진다. 금액도 함께 어긋난다.

           그때부터는 주문마다 제 줄과 제 금액을 스스로 간수한다(주문 제품 탭이 담는다). */
        $나눠산건 = $prescription->orders()->where('order_kind', Order::KIND_EXTRA)->exists();

        if ($order && $나눠산건) {
            return $order;
        }

        if ($order) {
            /* 아직 보내지 않은 줄은 저장할 때마다 처방전을 따라간다 — 제품을 고쳐 놓고
               주문 관리에서는 옛 제품이 보이는 일이 없어야 한다. 배송지ㆍ판매유형은
               건드리지 않는다. 그것은 주문 연계 탭에서 적는 값이다. */
            $order->update($summary);
        } else {
            $order = Order::create($summary + [
                'order_number'    => Order::generateOrderNumber(),
                'prescription_id' => $prescription->id,
                'created_by'      => Auth::id(),
                'status'          => 'pending',
                // 지금 팔 수 있는 유형의 첫째. 「1013」을 박아 두었더니 고를 수 없는 값이
                // 껍데기에 앉아, 주문 연계 탭이 그것을 되살리지 못했다.
                'so_type'         => Order::saleSoTypes()[0] ?? null,
            ]);
            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("주문 {$order->order_number} 자동 생성 (처방전 {$prescription->rx_number} 저장)");
        }

        // 품목 줄도 처방전을 따라간다
        $order->items()->delete();
        foreach ($items->values() as $i => $item) {
            $order->items()->create([
                'product_name'    => $item->product_name,
                'product_code'    => $item->product_code,
                'quantity'        => $item->quantity,
                'product_price'   => $item->product_price,
                'insurance_price' => $item->insurance_price,
                'nhis_amount'     => $item->nhis_amount,
                'patient_copay'   => $item->patient_copay,
                'sort_order'      => $i,
            ]);
        }

        return $order;
    }
}
