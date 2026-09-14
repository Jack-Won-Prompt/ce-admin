<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위임장 서명 링크에도 보호자 서명을 받는다 (2026-09-15 지시).
 *
 * 만 19세 미만의 위임은 법정대리인이 한다. 주문 등록의 서명 링크는 진작 그렇게
 * 받고 있었는데(consent/sign), 운영 데이터의 위임장 서명 링크에는 그 자리가 없어
 * **본인 서명란 하나**만 서 있었다 — 미성년에게 그렇게 받은 서명은 위임장으로
 * 쓸 수 없다.
 *
 * 담는 칸은 주문 등록 쪽(prescription_consents)과 같은 이름으로 둔다. 두 곳이
 * 다른 이름을 쓰면 나중에 옮겨 붙일 때 짝이 어긋난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delegation_signs')) {
            return;
        }

        Schema::table('delegation_signs', function (Blueprint $table) {
            if (! Schema::hasColumn('delegation_signs', 'guardian_signature_data')) {
                // 보호자 서명 그림 — 표에 함께 담는다(폴더가 어긋나도 되살아나게)
                $table->longText('guardian_signature_data')->nullable()->after('guardian_birth_date');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_sign_path')) {
                $table->string('guardian_sign_path', 255)->nullable()->after('guardian_signature_data');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_id_path')) {
                // 보호자 신분증 — 생년월일을 확인할 수 있는 것만 받는다
                $table->string('guardian_id_path', 255)->nullable()->after('guardian_sign_path');
            }
            if (! Schema::hasColumn('delegation_signs', 'guardian_id_mime')) {
                $table->string('guardian_id_mime', 50)->nullable()->after('guardian_id_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delegation_signs')) {
            return;
        }

        Schema::table('delegation_signs', function (Blueprint $table) {
            foreach (['guardian_id_mime', 'guardian_id_path', 'guardian_sign_path',
                      'guardian_signature_data'] as $c) {
                if (Schema::hasColumn('delegation_signs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
