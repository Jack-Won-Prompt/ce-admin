<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * 배송비를 걷는다(2026-09-03 확정).
 *
 * 하루 전 유형표를 보고 「배송비는 우리가 낸다」로 고쳤는데, 그다음 날 배송비 자체가
 * 없는 것으로 정해졌다. 누가 무는지를 물을 까닭이 없어졌다.
 *
 * 사유표의 부담 칸을 지운다. 접수 화면ㆍ목록ㆍ상세에서 그 칸은 이미 걷었다.
 *
 * 주문의 shipping_fee 칸은 지우지 않는다. 스물여섯 건에 3,000원이 적혀 있고 그 가운데
 * 열한 건은 그 금액으로 증빙까지 나갔다 — 그때 실제로 받은 돈이라 자취를 지우면
 * 국세청 자료와 맞춰 볼 길이 없다. 쓰지 않을 뿐이다.
 *
 * order_returns.shipping_burden 도 같은 까닭으로 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('return_reasons', 'burden')) {
            Schema::table('return_reasons', function (Blueprint $table) {
                $table->dropColumn('burden');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('return_reasons', 'burden')) {
            Schema::table('return_reasons', function (Blueprint $table) {
                $table->string('burden', 20)->nullable()->after('label');
            });

            /* 되돌릴 때는 2026-09-02 이전 값으로 — 그때 표에 적혀 있던 것이다 */
            foreach ([
                'change_mind' => 'customer', 'size_exchange' => 'customer',
                'defect' => 'company', 'wrong_item' => 'company', 'delay' => 'company',
            ] as $code => $burden) {
                DB::table('return_reasons')->where('code', $code)->update(['burden' => $burden]);
            }
        }
    }
};
