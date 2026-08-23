<?php
// 거래처를 IC(카테터) · OC(장루) 로 가른다.
//
// 두 사업부는 다루는 물건도, 붙는 서류도 다르다. 지금까지는 이 구분이 개인정보 동의서
// (privacy_consents.type = catheter | stoma)에만 있고 거래처 자체에는 없었다 —
// 그래서 「IC 거래처만 보기」 같은 것을 물을 수 없었다.
//
// 값은 두 글자로 둔다(IC · OC). 위드웍스ㆍ현장에서 그렇게 부른다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'care_type')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->char('care_type', 2)->nullable()->after('name');
            $table->index('care_type');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('patients', 'care_type')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['care_type']);
            $table->dropColumn('care_type');
        });
    }
};
