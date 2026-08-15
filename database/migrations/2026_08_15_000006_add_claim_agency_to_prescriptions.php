<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 청구처와 관할 지자체를 처방전에 둔다.
 *
 * 요양비를 공단에 내느냐 지자체에 내느냐에 따라 이후 절차가 통째로 갈린다. 공단은 위임
 * 등록이 선행 조건이고 사이트에 입력·업로드하지만, 지자체는 위임 절차가 없고 서류도 다르며
 * 등기로 보낸다. 지금까지는 이 구분이 어디에도 없어 청구 단계에 가서야 알았다.
 *
 * 급여구분과 주소로 짐작은 하되 값은 따로 저장한다. 급여구분이 나중에 바뀌어도 이미 낸
 * 청구의 청구처가 따라 바뀌면 안 되고, 짐작이 틀렸을 때 담당자가 고쳐 둘 자리도 필요하다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('prescriptions', 'claim_agency')) {
                $table->string('claim_agency', 20)->nullable()->after('benefit_class');
            }
            if (!Schema::hasColumn('prescriptions', 'local_gov')) {
                $table->string('local_gov', 60)->nullable()->after('claim_agency');
            }
        });

        // 청구 대상을 청구처로 추려 보는 일이 잦다
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index('claim_agency', 'rx_claim_agency_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('rx_claim_agency_idx');
            $table->dropColumn(['claim_agency', 'local_gov']);
        });
    }
};
