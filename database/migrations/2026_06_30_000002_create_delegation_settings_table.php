<?php
// database/migrations/2026_06_30_000002_create_delegation_settings_table.php
// 요양비 위임장 설정 (준요양기관·수령계좌·위임기간·서명위치) DB 관리

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegation_settings', function (Blueprint $table) {
            $table->id();

            // ② 준요양기관
            $table->string('provider_name', 150)->nullable();
            $table->string('provider_biz_no', 40)->nullable();
            $table->string('provider_ceo', 50)->nullable();
            $table->string('provider_phone', 40)->nullable();

            // ③ 요양비 수령계좌
            $table->string('account_receiver', 100)->nullable();
            $table->string('account_bank', 50)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->string('account_number', 50)->nullable();

            // ⑤ 위임기간
            $table->unsignedTinyInteger('period_years')->default(5);

            // 원본 PDF 서명 오버레이 좌표 (mm)
            $table->decimal('sig_x', 6, 2)->default(164);
            $table->decimal('sig_y', 6, 2)->default(266);
            $table->decimal('sig_w', 6, 2)->default(28);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_settings');
    }
};
