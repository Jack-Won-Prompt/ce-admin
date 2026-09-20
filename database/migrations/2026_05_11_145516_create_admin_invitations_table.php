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
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 200)->index();
            $table->string('role', 20)->default('manager');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
              // NOT NULL 인 timestamp 는 기본값을 적어 둔다 (2026-09-20).
            // 적지 않으면 explicit_defaults_for_timestamp 가 켜진 서버에서
            // 「Invalid default value」로 표를 만들지 못한다 — 값은 코드가 늘 채운다.
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
