<?php
// 배송지의 우편번호와 상세주소를 둘 자리.
//
// 화면에는 「우편번호 · 도로명 · 상세」 세 칸이 있고 주소 검색도 세 칸을 채우는데,
// 주문에는 shipping_address 한 칸뿐이었다. 그래서 우편번호는 저장되지 않고 사라졌고,
// 도로명과 상세는 한 줄로 붙어 들어가 다시 갈라 놓을 수 없었다.
//
// 위드웍스는 기본과 상세를 따로 받아 스스로 합친다. 우리도 따로 쥐고 있어야 보낼 때
// 같은 모양으로 넘길 수 있다 — 붙여 둔 것을 다시 가르다 「…강남파이낸스센터
// 강남파이낸스센터」처럼 두 번 붙는 일이 실제로 있었다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_postcode')) {
                $table->string('shipping_postcode', 10)->nullable()->after('shipping_recipient');
            }
            if (!Schema::hasColumn('orders', 'shipping_address_detail')) {
                $table->string('shipping_address_detail', 200)->nullable()->after('shipping_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['shipping_postcode', 'shipping_address_detail'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
