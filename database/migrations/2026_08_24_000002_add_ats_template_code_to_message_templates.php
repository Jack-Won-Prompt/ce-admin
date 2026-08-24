<?php
// 알림톡 유형에 팝빌 템플릿 코드를 적어 둔다.
//
// 알림톡은 카카오가 승인한 템플릿으로만 나간다. 우리 코드(order_confirm 따위)는 화면이
// 쓰는 이름일 뿐이고, 실제로 보낼 때 필요한 것은 팝빌에 등록된 템플릿 코드다.
// 그 둘을 이어 둘 자리가 없어, 유형을 골라도 무엇으로 보낼지 정할 수 없었다.
//
// 문자(SMS)에는 쓰이지 않는다 — 비워 둔다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_templates')
            || Schema::hasColumn('message_templates', 'ats_template_code')) {
            return;
        }

        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('ats_template_code', 60)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('message_templates', 'ats_template_code')) {
            return;
        }

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn('ats_template_code');
        });
    }
};
