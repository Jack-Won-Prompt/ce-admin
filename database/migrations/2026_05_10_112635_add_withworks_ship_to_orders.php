<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('withworks_ship_no', 50)->nullable()->after('withworks_status_at');
            $table->string('withworks_ship_status', 10)->nullable()->after('withworks_ship_no');
            $table->string('withworks_ship_status_label', 100)->nullable()->after('withworks_ship_status');
            $table->string('withworks_tracking_no', 100)->nullable()->after('withworks_ship_status_label');
            $table->timestamp('withworks_ship_at')->nullable()->after('withworks_tracking_no');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'withworks_ship_no',
                'withworks_ship_status',
                'withworks_ship_status_label',
                'withworks_tracking_no',
                'withworks_ship_at',
            ]);
        });
    }
};
