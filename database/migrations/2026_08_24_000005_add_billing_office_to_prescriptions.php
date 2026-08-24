<?php
// 이 건을 어디로 청구ㆍ발송하는지 — 고른 청구처를 건에 매어 둔다.
//
// 주소에서 읍ㆍ면ㆍ동을 뽑아 찾으면 대개 여럿이 나온다(같은 지사라도 담당업무가 갈리면
// 번호가 다르다). 그 가운데 무엇을 골랐는지는 사람만 아는 일이라 적어 두어야 한다.
// 적어 두지 않으면 다음에 열 때마다 다시 고르게 된다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prescriptions')
            || Schema::hasColumn('prescriptions', 'billing_office_id')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('billing_office_id')->nullable()->after('claim_agency');
            $table->index('billing_office_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('prescriptions', 'billing_office_id')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_office_id']);
            $table->dropColumn('billing_office_id');
        });
    }
};
