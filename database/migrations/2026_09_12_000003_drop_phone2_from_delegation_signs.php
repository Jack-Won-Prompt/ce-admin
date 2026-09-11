<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 전화번호는 하나다 (2026-09-11 지시).
 *
 * 처음에는 둘을 두고 보낼 때 고르게 했는데, 받은 명단에는 번호 칸이 하나뿐이라
 * 2,896건 모두 둘째 칸이 비어 있었다. 고를 것이 없는 고르개는 누를 자리만 차지한다.
 *
 * phone1 → phone 으로 이름도 바로잡는다. 둘이 없는데 1 이 붙어 있으면 다음 사람이
 * 「그럼 2 는 어디 있나」를 찾는다. 어느 번호로 보냈는지 고르던 sent_which 도 함께
 * 걷는다 — 보낼 곳이 하나뿐이니 적어 둘 것이 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->dropColumn(['phone2', 'sent_which']);
            $table->renameColumn('phone1', 'phone');
        });
    }

    public function down(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->renameColumn('phone', 'phone1');
            $table->string('phone2', 20)->nullable()->after('phone1');
            $table->enum('sent_which', ['phone1', 'phone2'])->nullable()->after('sent_to');
        });
    }
};
