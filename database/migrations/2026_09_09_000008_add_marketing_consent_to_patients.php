<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 마케팅 동의 — 상담사가 통화로 받아 고쳐 적는 자리 (2026-09-08 확인요청 4쪽).
 *
 * 마케팅 동의는 개인정보동의서에서 받는다. 그런데 「동의 안 함」으로 낸 사람이 나중에
 * 통화에서 동의하는 일이 있다. 그때 동의서를 고칠 수는 없다 — 본인이 서명해 낸 것이고,
 * 무엇에 동의했는지가 그대로 남아 있어야 한다.
 *
 * 그래서 거래처에 **덧쓰는 자리**를 둔다. 비어 있으면 동의서의 값이 곧 답이고, 적혀
 * 있으면 이쪽이 답이다. 누가 언제 고쳤는지 함께 남긴다 — 마케팅 동의는 뒤에 따져 물을
 * 수 있는 값이라 「누구에게 들었나」가 없으면 쓸 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'marketing_consent')) {
            return;
        }

        Schema::table('patients', function (Blueprint $t) {
            $t->string('marketing_consent', 10)->nullable()->after('main_contact');
            $t->unsignedBigInteger('marketing_consent_by')->nullable()->after('marketing_consent');
            $t->timestamp('marketing_consent_at')->nullable()->after('marketing_consent_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'marketing_consent')) {
            return;
        }

        Schema::table('patients', function (Blueprint $t) {
            $t->dropColumn(['marketing_consent', 'marketing_consent_by', 'marketing_consent_at']);
        });
    }
};
