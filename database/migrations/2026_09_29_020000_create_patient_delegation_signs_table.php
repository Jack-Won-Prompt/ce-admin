<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 거래처 › 위임장 서명 — 운영 데이터에서 옮겨 담는 자리 (2026-09-29 지시).
 *
 * 운영 데이터 › 위임장 서명에서 **서명까지 받은** 줄을 거래처 관리로 옮긴다.
 * 서명 그림도 함께 옮긴다 — 그림 없이 「서명했다」만 옮기면 공단에 낼 서류를
 * 만들 수 없고, 화면에서 무엇을 받았는지도 볼 수 없다.
 *
 * **왜 원본 표에 patient_id 를 적지 않고 옮겨 담는가.**
 * delegation_signs 는 운영 데이터 메뉴의 표다. 지우는 것도 고치는 것도 닿아서는
 * 안 된다(지시). 칸 하나를 더해 거래처 번호를 적어 넣는 것도 그 표를 고치는 일이다.
 * 그래서 우리 쪽에 제 표를 두고 읽어다 담는다 — 원본은 손대지 않는다.
 *
 * **다시 옮겨도 덧쓰기다.** delegation_sign_id 에 유일 색인이 있어 한 서명이 두 줄로
 * 서지 않는다. 시험을 다시 하려면 test-data:purge 로 묶음째 지우고 다시 옮기면 된다.
 *
 * **주민등록번호는 가린 값만 옮긴다.** 암호문을 한 벌 더 두면 지켜야 할 자리가
 * 하나 더 늘어난다. 평문이 필요한 일은 거래처 줄(patients.resident_no_enc)이 이미
 * 들고 있고, 이 표가 그것으로 무엇을 하지도 않는다. 나라가 내준 고유 식별값
 * (NICE 의 CI·DI)도 옮기지 않는다 — 화면이 쓰지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_delegation_signs')) {
            return;
        }

        Schema::create('patient_delegation_signs', function (Blueprint $table) {
            $table->id();

            /* ── 누구의 서명인가 ─────────────────────────────── */
            $table->unsignedBigInteger('patient_id')->nullable()->index();      // 거래처
            $table->unsignedBigInteger('ww_account_id')->nullable()->index();   // 운영 고객
            $table->unsignedBigInteger('delegation_sign_id')->unique();         // 원본 줄

            /* 무엇으로 이었는가 — 사람이 의심할 근거다 (2026-09-29 지시).
               name_birth  이름과 생년월일이 모두 맞다. 193줄 가운데 190줄.
               name        이름만 맞다. 원본에 주민등록번호가 없어 견줄 수 없었다.
               화면에서 이 값을 보여 주어야 「이름만으로 이은 줄」을 사람이 골라 볼 수 있다. */
            $table->string('matched_by', 12)->nullable()->index();

            /* ── 서명한 사람 — 원본이 적어 둔 그대로 ─────────── */
            $table->string('customer_name', 100);
            $table->string('dealer_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('main_contact', 10)->nullable();
            $table->string('resident_no_masked', 20)->nullable();   // 가린 값만
            $table->date('birth_date')->nullable();

            /* ── 보호자가 대신 한 위임 ───────────────────────── */
            $table->string('guardian_name', 50)->nullable();
            $table->string('guardian_relation', 20)->nullable();
            $table->date('guardian_birth_date')->nullable();

            /* ── 받은 동의 ───────────────────────────────────── */
            $table->boolean('agree_delegation')->default(false);
            $table->boolean('agree_privacy')->default(false);
            $table->boolean('agree_marketing')->default(false);

            /* ── 서명 실물 — 파일ㆍ파일명ㆍ그림 자체 ──────────
               원본과 같이 세 벌로 둔다. 파일은 storage/app/private/delegation-signs
               아래에 있고, 그 폴더가 없는 서버에서도 base64 만으로 그림이 선다. */
            $table->timestamp('signed_at')->nullable()->index();
            $table->string('sign_path', 255)->nullable();
            $table->string('sign_filename', 120)->nullable();
            $table->longText('sign_base64')->nullable();

            $table->longText('guardian_signature_data')->nullable();
            $table->string('guardian_sign_path', 255)->nullable();
            $table->string('guardian_id_path', 255)->nullable();
            $table->string('guardian_id_mime', 50)->nullable();

            /* ── 본인확인 (NICE) — 끝난 시각과 이름까지만 ───── */
            $table->timestamp('nice_verified_at')->nullable();
            $table->string('nice_name', 60)->nullable();
            $table->string('nice_birthdate', 8)->nullable();
            $table->string('nice_gender', 1)->nullable();
            $table->string('nice_mobile', 20)->nullable();

            /* ── 보낸 자취ㆍ서명한 자리 ──────────────────────── */
            $table->string('source', 10)->nullable();          // list | direct
            $table->string('sent_by_name', 50)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            /* ── 딱지 — 묶음째 지울 수 있게 ──────────────────── */
            $table->string('data_origin', 12)->nullable()->index();
            $table->string('data_batch', 40)->nullable()->index();

            $table->timestamps();

            $table->index('customer_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_delegation_signs');
    }
};
