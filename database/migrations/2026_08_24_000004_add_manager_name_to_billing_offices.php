<?php
// 청구처에 담당자 이름을 적을 자리.
//
// 공단 지사찾기에는 부서ㆍ직책ㆍ담당업무ㆍ번호만 나오고 이름은 없다. 그런데 한 번
// 통화하면 이름을 알게 되고, 다음에 다시 걸 때 그 이름이 있느냐 없느냐가 크다.
// 「한 번 찾은 것을 쌓아 둔다」는 이 표의 뜻에 맞는 값이라 자리를 낸다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_offices')
            || Schema::hasColumn('billing_offices', 'manager_name')) {
            return;
        }

        Schema::table('billing_offices', function (Blueprint $table) {
            $table->string('manager_name', 40)->nullable()->after('dept');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('billing_offices', 'manager_name')) {
            return;
        }

        Schema::table('billing_offices', function (Blueprint $table) {
            $table->dropColumn('manager_name');
        });
    }
};
