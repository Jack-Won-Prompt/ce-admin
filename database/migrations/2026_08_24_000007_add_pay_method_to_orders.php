<?php
// 이 주문을 어떤 방식으로 받는가 — 가상계좌ㆍ카드결제ㆍ무통장입금.
//
// 지금까지는 보낸 결제 링크에서 되짚어 알아냈다. 그런데 링크를 보내기 전에도 방식은
// 정해져 있고(전화로 「카드로 낼게요」), 링크 없이 계좌로 받는 건도 있다.
// 되짚어 아는 것과 정해 두는 것은 다르다 — 정해 둔 것이 있으면 그것을 따른다.
//
// 비어 있으면 예전처럼 링크에서 되짚는다. 이미 쌓인 건을 건드리지 않는다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders') || Schema::hasColumn('orders', 'pay_method')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('pay_method', 20)->nullable()->after('deposit_note');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'pay_method')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pay_method');
        });
    }
};
