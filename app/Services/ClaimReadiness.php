<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrescriptionDocument;
use App\Support\ClaimAgency;

/**
 * 청구할 자료가 갖춰졌는지 본다.
 *
 * 공단 청구에는 처방전·현금영수증(또는 카드매출전표)·세금계산서가 있어야 하고, 물건이
 * 고객에게 간 뒤여야 한다. 하나라도 빠지면 청구가 반려된다.
 *
 * 판정은 청구 창을 열어도 할 수 있지만, 그러면 목록에서는 전부 똑같아 보여 담당자가 하나씩
 * 열어 보게 된다. 그래서 미리 계산해 주문에 남긴다 — 목록에서 바로 골라내라고.
 *
 * 무엇이 빠졌는지도 함께 남긴다. 「안 됨」만 알면 다시 열어 봐야 하기 때문이다.
 */
class ClaimReadiness
{
    /**
     * 지금 상태를 따진다. 저장하지는 않는다.
     *
     * @return array{ready:bool, missing:string[], applicable:bool}
     */
    public function evaluate(Order $order): array
    {
        $prescription = $order->prescription;

        // 공단 청구 건이 아니면 이 판정 자체가 의미 없다. 지자체는 서류도 보내는 법도 다르다.
        $agency = $prescription?->claim_agency;
        if ($agency !== null && $agency !== ClaimAgency::NHIS) {
            // 「자료가 모자라다」와 「공단에 낼 건이 아니다」는 다르다. 목록에서 구분돼야 한다.
            return [
                'ready'      => false,
                'missing'    => [ClaimAgency::LABELS[$agency] ?? $agency . ' 청구 건'],
                'applicable' => false,
            ];
        }

        $missing = [];

        /* 물건이 나가기 전에는 청구하지 않는다.
           기준은 배송완료가 아니라 출고완료다. 위드웍스가 배송 상태를 관리하지 않아
           (택배사 조회 연동이 없고 trackings 에 배송상태 컬럼도 없다) 배송완료라는 사건이
           우리에게 오지 않는다. 그것을 기다리면 어떤 주문도 청구에 이르지 못한다.
           손으로 배송완료까지 올린 건도 당연히 통과한다. */
        if (!in_array($order->status, \App\Models\Order::AFTER_SHIP, true)) {
            $missing[] = '출고 전';
        }

        if (!$prescription) {
            $missing[] = '처방전 연결';
        } elseif (!$prescription->image_path) {
            $missing[] = '처방전 이미지';
        }

        // 위임 등록이 없으면 공단이 청구를 받지 않는다
        if (!$prescription?->patient?->nhis_agree_start) {
            $missing[] = '공단 위임 등록';
        }

        if (!$order->tax_invoice_no || $order->tax_invoice_status === 'cancelled') {
            $missing[] = '세금계산서';
        }

        if (!$order->cash_receipt_no || $order->cash_receipt_status === 'cancelled') {
            $missing[] = '현금영수증';
        }

        // 발행은 했는데 첨부할 서류가 없으면 업로드할 것이 없다
        if ($prescription) {
            $types = PrescriptionDocument::where('prescription_id', $prescription->id)
                ->pluck('type')->unique();

            if ($order->tax_invoice_no && !$types->contains('tax_invoice')) {
                $missing[] = '세금계산서 서류';
            }
            if ($order->cash_receipt_no && !$types->contains('cash_receipt')) {
                $missing[] = '현금영수증 서류';
            }
        }

        return ['ready' => $missing === [], 'missing' => $missing, 'applicable' => true];
    }

    /** 따진 결과를 주문에 남긴다 */
    public function refresh(Order $order): array
    {
        $r = $this->evaluate($order);

        $order->forceFill([
            'claim_ready'      => $r['ready'],
            // 목록 한 칸에 들어갈 만큼만. 자세한 것은 청구 창이 보여 준다.
            'claim_missing'    => $r['missing'] ? mb_substr(implode(' · ', $r['missing']), 0, 255) : null,
            'claim_checked_at' => now(),
        ])->saveQuietly();

        return $r;
    }

    /**
     * 아직 청구하지 않은 주문을 훑는다.
     *
     * 발행·배송 같은 사건마다 그 자리에서 다시 따지지만, 놓치는 경로가 있다. 위임 등록일이
     * 환자 쪽에서 바뀌는 것처럼 주문을 건드리지 않는 변화도 있어서 주기적으로 다시 본다.
     */
    public function sweep(int $limit = 500): array
    {
        $orders = Order::with(['prescription.patient'])
            ->whereIn('nhis_claim_status', ['pending', 'rejected'])
            ->orderBy('claim_checked_at')             // 오래 안 본 것부터
            ->limit($limit)
            ->get();

        $ready = 0;
        foreach ($orders as $order) {
            if ($this->refresh($order)['ready']) {
                $ready++;
            }
        }

        return ['checked' => $orders->count(), 'ready' => $ready];
    }
}
