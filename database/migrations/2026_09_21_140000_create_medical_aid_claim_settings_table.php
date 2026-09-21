<?php
// database/migrations/2026_09_21_140000_create_medical_aid_claim_settings_table.php
// 요양비 지급청구서[별지 제12호] — 글자 자리를 화면에서 고쳐 담는다(2026-09-21 지시).
//
// 위임장 설정(delegation_settings)과 같은 방식이다. 바꾼 칸만 JSON 으로 담고,
// 기본값은 config/medical_aid_claim.php 가 쥔다 — 코드에 칸이 늘어도 표를 손대지
// 않아도 되고, 담아 둔 값을 지우면 기본 자리로 돌아간다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_aid_claim_settings', function (Blueprint $table) {
            $table->id();

            /* 바꾼 칸만 담는다 — {"patient_name":{"x":63,"y":52,"size":8}, ...} */
            $table->json('field_positions')->nullable();

            /* 서명 이미지 자리 (mm). 이 서식은 **가로 A4** 라 x 가 297 까지다. */
            $table->decimal('sig_x', 6, 2)->default(168);
            $table->decimal('sig_y', 6, 2)->default(164.5);
            $table->decimal('sig_w', 6, 2)->default(20);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_aid_claim_settings');
    }
};
