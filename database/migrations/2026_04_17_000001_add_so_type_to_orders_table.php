<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('so_type', 10)->nullable()->after('status')
                  ->comment('Withworks 판매 유형 코드 (1013=CE판매, 1016=개인판매, 1022=샘플판매)');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('so_type');
        });
    }
};
