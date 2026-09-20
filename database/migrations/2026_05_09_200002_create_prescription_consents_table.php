<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('patient_name', 100);
            $table->string('patient_mobile', 20);
            $table->longText('signature_data')->nullable();
            $table->enum('status', ['pending', 'agreed', 'declined', 'expired'])->default('pending');
              // NOT NULL 인 timestamp 는 기본값을 적어 둔다 (2026-09-20).
            // 적지 않으면 explicit_defaults_for_timestamp 가 켜진 서버에서
            // 「Invalid default value」로 표를 만들지 못한다 — 값은 코드가 늘 채운다.
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'expires_at']);
            $table->index(['prescription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_consents');
    }
};
