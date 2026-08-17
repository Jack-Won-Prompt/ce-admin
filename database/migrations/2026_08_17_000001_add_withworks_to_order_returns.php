<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 교환·반품·취소를 위드웍스와 잇는다.
 *
 * 지금까지 되돌리는 건은 CEAdmin 안에서만 돌았다. 창고는 물건이 돌아온다는 것을
 * 알지 못했고, 우리는 창고가 무엇을 했는지 알 수 없었다.
 *
 * 위드웍스가 되돌림 판매주문을 따로 세운다(취소 5004 · 반품 5005 · 교환 5006).
 * 그 번호와 상태를 여기에 담는다. 원 판매주문 번호는 주문에 이미 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->string('withworks_so_no', 50)->nullable()->after('refunded_at');
            $table->unsignedBigInteger('withworks_so_id')->nullable()->after('withworks_so_no');
            $table->string('withworks_so_type', 10)->nullable()->after('withworks_so_id');
            /* 상태는 그쪽이 정하는 값이라 얼마나 길지 알 수 없다. 예전에 주문 쪽에서
               varchar(10) 이 넘쳐 500 이 났다 — 넉넉히 잡는다. */
            $table->string('withworks_status', 50)->nullable()->after('withworks_so_type');
            $table->string('withworks_status_label', 100)->nullable()->after('withworks_status');
            $table->timestamp('withworks_sent_at')->nullable()->after('withworks_status_label');
            $table->string('withworks_error', 500)->nullable()->after('withworks_sent_at');

            $table->index('withworks_so_no');
        });

        Schema::table('withworks_settings', function (Blueprint $table) {
            // return_so_type 하나로는 셋을 가릴 수 없다. 코드는 그쪽이 정하므로 화면에서 고친다.
            $table->string('cancel_so_type', 10)->nullable()->after('return_so_type');
            $table->string('exchange_so_type', 10)->nullable()->after('cancel_so_type');
        });

        // 이미 있는 설정 행에 기본값을 채운다 — 비워 두면 되돌림을 보낼 수 없다
        \DB::table('withworks_settings')->update([
            'cancel_so_type'   => \DB::raw("COALESCE(cancel_so_type, '5004')"),
            'return_so_type'   => \DB::raw("COALESCE(NULLIF(return_so_type, '5004'), '5005')"),
            'exchange_so_type' => \DB::raw("COALESCE(exchange_so_type, '5006')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropIndex(['withworks_so_no']);
            $table->dropColumn([
                'withworks_so_no', 'withworks_so_id', 'withworks_so_type',
                'withworks_status', 'withworks_status_label',
                'withworks_sent_at', 'withworks_error',
            ]);
        });

        Schema::table('withworks_settings', function (Blueprint $table) {
            $table->dropColumn(['cancel_so_type', 'exchange_so_type']);
        });
    }
};
