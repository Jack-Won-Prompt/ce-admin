<?php
// database/migrations/2026_08_28_000002_create_registration_settings_and_fields.php
//
// 자가도뇨 소모성 재료 급여대상자 등록 신청서(별지 제4호서식) 두 가지를 함께 만든다.
//
//   registration_settings   서식 위의 글자ㆍ체크ㆍ서명 자리 (위임장 설정과 같은 얼개)
//   prescriptions 의 새 칸   건마다 다른 값 — 상병구분ㆍ확인사항ㆍSMS 통보ㆍ수진자와의 관계
//
// 나머지(성명ㆍ주민번호ㆍ전화ㆍ진단확인일ㆍ상병ㆍ요류역학검사일ㆍ병원ㆍ의사)는 이미
// 쥐고 있는 값이라 칸을 늘리지 않는다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('registration_settings')) {
            Schema::create('registration_settings', function (Blueprint $table) {
                $table->id();

                // ③ 신청인 서명 오버레이 좌표 (mm)
                $table->decimal('sig_x', 6, 2)->default(151);
                $table->decimal('sig_y', 6, 2)->default(196);
                $table->decimal('sig_w', 6, 2)->default(28);

                /* 글자ㆍ체크 자리는 열쇠마다 x/y(/size)라 칸으로 두지 않는다.
                   항목이 코드에서 늘어도 표를 손대지 않아야 한다(위임장과 같다). */
                $table->json('field_positions')->nullable();
                $table->json('check_positions')->nullable();

                $table->timestamps();
            });
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'reg_dx_type')) {
                // 상병구분 — dx_congenital | dx_sci_over2 | dx_sci_under2 | dx_other
                $table->string('reg_dx_type', 20)->nullable()->after('uro_date');
            }
            if (! Schema::hasColumn('prescriptions', 'reg_confirm_items')) {
                // 확인사항 — 고른 것들의 열쇠 목록
                $table->json('reg_confirm_items')->nullable()->after('reg_dx_type');
            }
            if (! Schema::hasColumn('prescriptions', 'reg_sms_notify')) {
                // 등록결과통보(SMS) — 예/아니오. 고르지 않았으면 null 이고 그때는 아무것도 안 찍는다.
                $table->boolean('reg_sms_notify')->nullable()->after('reg_confirm_items');
            }
            if (! Schema::hasColumn('prescriptions', 'reg_relation')) {
                // 수진자와의 관계 — ③ 신청인이 누구인가
                $table->string('reg_relation', 20)->nullable()->after('reg_sms_notify');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            foreach (['reg_dx_type', 'reg_confirm_items', 'reg_sms_notify', 'reg_relation'] as $col) {
                if (Schema::hasColumn('prescriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('registration_settings');
    }
};
