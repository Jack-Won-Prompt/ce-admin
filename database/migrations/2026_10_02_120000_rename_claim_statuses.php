<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 청구 상태를 업무가 쓰는 여섯 자리로 갈아 끼운다 (2026-10-02 지시).
 *
 *   신규 · 서류확인 · 청구등록 · 청구반려 · 청구취소 · 청구완료
 *
 * 예전 일곱 자리(청구 전·청구중·청구완료·승인·반려·보류·취소)에서 옮긴다.
 * 「승인」과 「보류」는 따로 두지 않는다 — 공단이 인정한 것은 청구완료에 담고,
 * 판단을 미룬 것은 아직 청구등록에 머문 것으로 본다.
 *
 * 갈아 끼울 때 담겨 있던 것은 **청구 전 143건뿐**이었다(나머지 여섯은 0건).
 * 그래서 옮기며 잃는 자취가 없다.
 *
 * 옮기는 표는 모델이 한 벌만 갖는다(Order::CLAIM_STATUS_MIGRATION) — 코드와
 * 마이그레이션이 서로 다른 표를 들면 언젠가 어긋난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        foreach (Order::CLAIM_STATUS_MIGRATION as $옛 => $새) {
            if ($옛 === $새) {
                continue;   // rejected·cancelled 는 이름이 그대로다
            }

            DB::table('orders')->where('nhis_claim_status', $옛)
                ->update(['nhis_claim_status' => $새]);
        }

        /* 비어 있던 줄도 「신규」로 세운다 — 처음 선 주문이 어디에 서 있는지가
           비어 있으면 목록에서 걸러지지도, 고쳐지지도 않는다. */
        DB::table('orders')
            ->whereNull('nhis_claim_status')->orWhere('nhis_claim_status', '')
            ->update(['nhis_claim_status' => Order::CLAIM_NEW]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        /* 되돌리면 「승인」과 「보류」는 살아나지 않는다 — 청구완료·청구등록으로
           합쳐졌기 때문이다. 그 둘을 쓰던 건이 0건이라 되돌려도 잃을 것이 없다. */
        foreach ([Order::CLAIM_NEW => 'pending', 'registered' => 'submitting', 'completed' => 'submitted'] as $새 => $옛) {
            DB::table('orders')->where('nhis_claim_status', $새)
                ->update(['nhis_claim_status' => $옛]);
        }
    }
};
