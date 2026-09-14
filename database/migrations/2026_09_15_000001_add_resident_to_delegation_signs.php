<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위임장 서명 명단에 주민등록번호와 보호자를 담는다 (2026-09-15 지시).
 *
 * 위임은 만 19세 미만이면 법정대리인이 대신 한다. 그런데 명단에는 이름과 번호뿐이라,
 * 담당자는 보내기 전에 그 사람이 성년인지 알 수 없었다 — 미성년에게 보낸 링크는
 * 보호자 칸을 요구하며 그 자리에서 멈춘다.
 *
 * 명단(위임 필요 리스트)이 주민등록번호와 나이를 들고 온다. 그것을 받아 두면 성년ㆍ
 * 미성년을 화면에서 바로 읽을 수 있고, 보호자를 미리 적어 두면 링크를 연 사람이
 * 다시 치지 않아도 된다.
 *
 * **주민등록번호는 평문으로 두지 않는다.** 처방전이 쓰는 것과 같은 자리(ResidentNo)에
 * 담고, 화면에는 마스킹한 값만 내보낸다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delegation_signs')) {
            return;
        }

        Schema::table('delegation_signs', function (Blueprint $table) {
            if (! Schema::hasColumn('delegation_signs', 'resident_no')) {
                // 암호화해 담는다 — 길이가 늘어나므로 넉넉히 둔다
                $table->text('resident_no')->nullable()->after('main_contact');
            }
            if (! Schema::hasColumn('delegation_signs', 'resident_no_masked')) {
                // 화면과 검색에 쓰는 값 — 900101-1●●●●●●
                $table->string('resident_no_masked', 20)->nullable()->after('resident_no');
            }
            if (! Schema::hasColumn('delegation_signs', 'birth_date')) {
                // 주민등록번호에서 세운다 — 나이와 성년 판정의 바탕이다
                $table->date('birth_date')->nullable()->after('resident_no_masked');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_name')) {
                $table->string('guardian_name', 50)->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_relation')) {
                $table->string('guardian_relation', 20)->nullable()->after('guardian_name');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_birth_date')) {
                $table->date('guardian_birth_date')->nullable()->after('guardian_relation');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delegation_signs')) {
            return;
        }

        Schema::table('delegation_signs', function (Blueprint $table) {
            foreach (['guardian_birth_date', 'guardian_relation', 'guardian_name',
                      'birth_date', 'resident_no_masked', 'resident_no'] as $c) {
                if (Schema::hasColumn('delegation_signs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
