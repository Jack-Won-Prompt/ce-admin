<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 명단에서 온 줄과 손으로 넣은 줄을 가른다 (2026-09-14 지시).
 *
 * 「받는 사람에게 무엇이 가는지」를 보려면 제 번호로 한 번 보내 보는 수밖에 없다.
 * 그런데 링크는 표의 한 줄에 붙으므로, 확인하려고 보낸 것도 줄을 하나 세운다 —
 * 그 줄이 실제 명단 사이에 섞이면 「보낼 사람」을 셀 때마다 걸린다.
 *
 * 판매처가 비는 것으로 짐작할 수도 있지만 짐작이다. 칸으로 적어 둔다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            // list   명단(CSV)에서 올라온 줄
            // direct 담당자가 이름ㆍ번호를 직접 적어 보낸 줄
            $table->string('source', 10)->default('list')->after('src_no')->index();
        });
    }

    public function down(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
