<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 보호자 번호와 Main contact (2026-09-14 지시).
 *
 * 처음에는 번호 둘(phone1ㆍphone2)로 세웠다가, 「보낼 곳이 하나뿐이니 적어 둘 것이
 * 없다」며 걷었다(2026_09_12_000003). 운영에서 겪어 보니 그렇지 않았다 — 환자가
 * 받지 못해 보호자에게 보내야 하는 건이 있고, 그때 명단을 고쳐 다시 올릴 수는 없다.
 *
 * 다만 그때와 같은 꼴로 되돌리지는 않는다. phone1ㆍphone2 는 어느 쪽이 누구의
 * 것인지 이름이 말해 주지 않아 화면마다 다르게 읽혔다. 누구의 번호인지 이름에
 * 담고, 어디로 보낼지는 sent_which(보낸 뒤에야 아는 값) 대신 main_contact
 * (보내기 전에 정하는 값)로 든다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->string('guardian_phone', 20)->nullable()->after('phone');

            // patient  환자에게 보낸다 (기본)
            // guardian 보호자에게 보낸다
            $table->string('main_contact', 10)->default('patient')->after('guardian_phone');
        });
    }

    public function down(): void
    {
        Schema::table('delegation_signs', function (Blueprint $table) {
            $table->dropColumn(['guardian_phone', 'main_contact']);
        });
    }
};
