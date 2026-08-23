<?php
// 청구전략을 건에 적어 둔다.
//
// 전략은 유형 × 자격이 정하므로 두 칸에서 언제든 다시 셈할 수 있다. 그래도 적어 두는
// 까닭은, 표가 바뀔 때(산재ㆍ자동차보험이 확정되면) 예전 건이 「그때 무엇으로 청구하기로
// 했는지」를 잃지 않게 하기 위해서다.
//
// 담는 값은 화면ㆍ표와 같은 열쇠다 — 「유형|자격」(예: 30|일반, 10|기초, 20|).
// 이름ㆍ비율은 담지 않는다. 그것은 App\Support\BillingStrategy 가 이 열쇠로 알려 준다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prescriptions') || Schema::hasColumn('prescriptions', 'billing_strategy')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('billing_strategy', 40)->nullable()->after('benefit_class');
            $table->index('billing_strategy');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('prescriptions', 'billing_strategy')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_strategy']);
            $table->dropColumn('billing_strategy');
        });
    }
};
