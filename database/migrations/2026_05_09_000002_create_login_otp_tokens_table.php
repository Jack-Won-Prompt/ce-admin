<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6);
              // NOT NULL 인 timestamp 는 기본값을 적어 둔다 (2026-09-20).
            // 적지 않으면 explicit_defaults_for_timestamp 가 켜진 서버에서
            // 「Invalid default value」로 표를 만들지 못한다 — 값은 코드가 늘 채운다.
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otp_tokens');
    }
};
