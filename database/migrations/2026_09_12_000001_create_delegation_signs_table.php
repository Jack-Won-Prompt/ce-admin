<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 운영 데이터 › 위임장 서명 (2026-09-11 지시).
 *
 * 기존 처방ㆍ주문ㆍ거래처와 잇지 않는다. 이 표 하나와 폴더 하나로 닫힌다 —
 * 운영 서버로 옮길 때 가져갈 것이 그 둘뿐이어야 하기 때문이다.
 *
 * 그래서 거래처명ㆍ전화번호를 남의 표에서 읽지 않고 제 칸으로 들고, 보낸 담당자도
 * 이름을 칸에 굳혀 둔다. 옮긴 뒤 users 가 달라도 누가 보냈는지 남는다.
 *
 * 서명은 세 벌로 남긴다 — 파일ㆍ파일명ㆍ그림 자체(base64). 표만 있어도 서명이
 * 되살아나고, 폴더만 있어도 되살아난다. 옮기다 한쪽이 빠져도 잃지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegation_signs', function (Blueprint $table) {
            $table->id();

            /* ── 받는 사람 — 남의 표를 보지 않는다 ───────────── */
            $table->string('customer_name', 100);
            $table->string('phone1', 20)->nullable();
            $table->string('phone2', 20)->nullable();

            /* ── 보낸 자취 ───────────────────────────────────── */
            $table->string('token', 32)->nullable()->unique();
            $table->string('sent_to', 20)->nullable();
            // 전화번호 1ㆍ2 중 어느 것으로 보냈는가
            $table->enum('sent_which', ['phone1', 'phone2'])->nullable();
            $table->unsignedBigInteger('sent_by_id')->nullable();
            $table->string('sent_by_name', 50)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            /* pending  아직 보내지 않음 (엑셀로 올린 직후)
               sent     보냈고 서명을 기다린다
               signed   서명을 받았다
               declined 환자가 동의하지 않았다 */
            $table->enum('status', ['pending', 'sent', 'signed', 'declined'])
                  ->default('pending')->index();

            /* ── 받은 동의 ───────────────────────────────────── */
            $table->boolean('agree_delegation')->default(false);
            $table->boolean('agree_privacy')->default(false);
            $table->boolean('agree_marketing')->default(false);

            /* ── 서명 — 세 벌 ───────────────────────────────── */
            $table->timestamp('signed_at')->nullable();
            $table->string('sign_path', 255)->nullable();
            $table->string('sign_filename', 120)->nullable();
            $table->longText('sign_base64')->nullable();

            /* ── NICE 본인확인 결과 ─────────────────────────── */
            $table->timestamp('nice_verified_at')->nullable();
            $table->string('nice_name', 60)->nullable();
            $table->string('nice_birthdate', 8)->nullable();
            $table->string('nice_gender', 1)->nullable();
            $table->string('nice_mobile', 20)->nullable();
            $table->string('nice_ci', 200)->nullable();
            $table->string('nice_di', 100)->nullable();

            /* ── 서명한 자리 ─────────────────────────────────── */
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('customer_name');
            $table->index('signed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_signs');
    }
};
