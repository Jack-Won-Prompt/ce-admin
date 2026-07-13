<?php
// database/migrations/2026_06_30_000001_create_privacy_consents_table.php
// mcoloplast 개인정보 수집·이용 동의서 (카테터/장루) 제출 저장

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();           // catheter | stoma

            // 공통 인적사항
            $table->string('name', 100);                   // 성명
            $table->string('phone', 30)->nullable();       // 연락처 (장루=연락처1)
            $table->string('phone2', 30)->nullable();      // 연락처2 (장루 선택)
            $table->string('email', 150)->nullable();       // 이메일
            $table->string('zip', 10)->nullable();          // 우편번호
            $table->string('addr1', 200)->nullable();       // 기본주소
            $table->string('addr2', 200)->nullable();       // 상세주소

            // 카테터 전용
            $table->string('insurance', 30)->nullable();        // 보험: 일반/보훈/산업재해
            $table->string('support_qualify', 40)->nullable();  // 지원자격: 일반/차상위경감대상자/기초생활수급자

            // 장루 전용
            $table->string('birth', 20)->nullable();        // 생년월일
            $table->string('product', 40)->nullable();      // 사용제품: 미오/센슈라/기타/모름
            $table->string('hospital', 100)->nullable();    // 수술 병원
            $table->string('surgery_date', 20)->nullable(); // 수술일자
            $table->string('stoma_type', 20)->nullable();   // 장루타입: 영구/임시/모름
            $table->string('stoma_kind', 20)->nullable();   // 결장루/회장루/요루

            // 동의 항목 (동의함 / 동의하지 않음)
            $table->string('agree_general', 12)->nullable();            // 일반정보 수집·이용 (필수)
            $table->string('agree_sensitive', 12)->nullable();          // 민감정보 수집·이용 (필수, 장루)
            $table->string('agree_third_party', 12)->nullable();        // 제3자 제공
            $table->string('agree_marketing', 12)->nullable();          // 마케팅 수집·이용
            $table->string('agree_marketing_sensitive', 12)->nullable(); // 민감정보 마케팅 (장루)
            $table->string('agree_third_sensitive', 12)->nullable();    // 민감정보 제3자 (장루)

            $table->json('extra')->nullable();              // 원본 전체 필드 백업
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_consents');
    }
};
