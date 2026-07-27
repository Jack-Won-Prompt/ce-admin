<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위임동의 서명 전 NICE 본인확인 결과 저장 컬럼.
 * CI/DI 는 민감식별정보이므로 애플리케이션 레벨에서 암호화(encrypted 캐스트)해 저장한다
 * → 컬럼 타입은 암호문 길이를 수용하도록 text 사용.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->timestamp('nice_verified_at')->nullable()->after('signature_data'); // 본인확인 완료 시각
            $table->string('nice_name', 100)->nullable()->after('nice_verified_at');     // 인증 이름(UTF-8)
            $table->string('nice_birthdate', 8)->nullable()->after('nice_name');         // YYYYMMDD
            $table->string('nice_gender', 4)->nullable()->after('nice_birthdate');       // 0(여)/1(남) 등 코드
            $table->string('nice_nation', 4)->nullable()->after('nice_gender');          // 내/외국인 코드
            $table->string('nice_mobileco', 8)->nullable()->after('nice_nation');        // 통신사 코드
            $table->string('nice_mobile', 20)->nullable()->after('nice_mobileco');       // 인증 휴대폰번호
            $table->string('nice_authtype', 8)->nullable()->after('nice_mobile');        // 인증수단(M:휴대폰 등)
            $table->string('nice_response_no', 64)->nullable()->after('nice_authtype');  // NICE 응답 고유번호(수신데이터 키)
            $table->text('nice_ci')->nullable()->after('nice_response_no');              // 연계정보 CI (암호화)
            $table->text('nice_di')->nullable()->after('nice_ci');                       // 중복가입확인 DI (암호화)
        });
    }

    public function down(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->dropColumn([
                'nice_verified_at', 'nice_name', 'nice_birthdate', 'nice_gender',
                'nice_nation', 'nice_mobileco', 'nice_mobile', 'nice_authtype',
                'nice_response_no', 'nice_ci', 'nice_di',
            ]);
        });
    }
};
