<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('lcpoint')->table('orders', function (Blueprint $table) {
            $table->string('withworks_status', 10)->nullable()->after('withworks_so_id');
            $table->string('withworks_status_label', 50)->nullable()->after('withworks_status');
            $table->timestamp('withworks_status_at')->nullable()->after('withworks_status_label');
        });
    }

    public function down(): void
    {
        Schema::connection('lcpoint')->table('orders', function (Blueprint $table) {
            $table->dropColumn(['withworks_status', 'withworks_status_label', 'withworks_status_at']);
        });
    }
};
