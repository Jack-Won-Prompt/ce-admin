<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NICE 본인확인 설정(단일 행). 관리자 설정 화면에서 자격증명을 입력·수정한다.
 * 행이 없으면 config/nice.php(.env) 값으로 시드되므로 기존 .env 운영은 그대로 유지된다.
 * client_secret 은 모델에서 encrypted 캐스트로 암호화 저장.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nice_settings', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 190)->default('');
            $table->text('client_secret')->nullable();      // encrypted 캐스트
            $table->string('product_id', 100)->default('');
            $table->boolean('enforce')->default(true);      // 서명 전 본인확인 강제
            $table->boolean('match_name')->default(true);   // 이름 일치 필수
            $table->boolean('match_birth')->default(true);  // 생년월일 일치 필수
            $table->string('api_base', 190)->default('');
            $table->string('standard_url', 190)->default('');
            $table->timestamp('tested_at')->nullable();     // 마지막 연결 테스트 성공 시각
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nice_settings');
    }
};
