<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrescriptionDocument;
use App\Support\ClaimAgency;

/**
 * 청구할 자료가 갖춰졌는지 본다.
 *
 * 공단 청구에는 처방전·세금계산서·거래명세서가 있어야 하고, 물건이
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

        /* 현금영수증은 공단에 내지 않는다(2026-09-04 확정). 본인부담 몫의 증빙이라
           환자에게 가는 것이고, 공단이 보는 것은 세금계산서다.
           조건으로 두었더니 본인부담이 0인 건(차상위경감ㆍ기초)은 낼 현금영수증이
           없어 영영 「청구 준비 안 됨」에 머물렀다. */

        // 발행은 했는데 첨부할 서류가 없으면 업로드할 것이 없다
        if ($prescription) {
            $types = PrescriptionDocument::where('prescription_id', $prescription->id)
                ->pluck('type')->unique();

            if ($order->tax_invoice_no && !$types->contains('tax_invoice')) {
                $missing[] = '세금계산서 서류';
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
     * 주문을 건드리지 않는 변화에도 다시 따진다 (2026-09-18 지시).
     *
     * 판정에 드는 여섯 가지 가운데 넷은 주문 바깥에 있다 — 처방전 이미지, 청구 기관,
     * 환자의 공단 위임 등록일, 서류함의 세금계산서. 이것들이 바뀌어도 주문은 그대로라,
     * 사건마다 부르던 자리(발행ㆍ출고ㆍ취소)가 잡아 주지 못했다.
     *
     * 여태는 한 시간마다 훑어서 메웠다. 그 사이에는 목록이 틀린 채로 서 있었고, 실제로
     * **여섯 건이 「준비완료」로 보이는데 자료가 빠져** 있었다 — 그대로 공단에 내면
     * 반려된다. 바뀌는 그 자리에서 다시 따지면 훑을 일이 없다.
     *
     * 이미 청구한 건은 건드리지 않는다.
     *
     * @return int 다시 따진 주문 수
     */
    public function 처방전다시보기(?int $prescriptionId): int
    {
        return $prescriptionId ? $this->다시보기('prescription_id', $prescriptionId) : 0;
    }

    /** 환자 쪽이 바뀌었을 때 — 위임 등록일은 거래처 화면에서 들어온다 */
    public function 환자다시보기(?int $patientId): int
    {
        return $patientId ? $this->다시보기('patient_id', $patientId) : 0;
    }

    private function 다시보기(string $칸, int $값): int
    {
        $orders = Order::with(['prescription.patient'])
            ->where($칸, $값)
            ->whereIn('nhis_claim_status', ['pending', 'rejected'])
            ->get();

        foreach ($orders as $order) {
            $this->refresh($order);
        }

        return $orders->count();
    }

    /**
     * 아직 청구하지 않은 주문을 훑는다 — 손으로 부르는 그물이다.
     *
     * 바뀌는 자리마다 다시 따지므로 평소에는 쓸 일이 없다. 자료를 손으로 고쳤거나
     * 판정 잣대를 바꾼 뒤처럼, 한 번에 맞춰야 할 때 `claim:refresh` 로 부른다.
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
