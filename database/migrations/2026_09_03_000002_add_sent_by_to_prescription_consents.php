<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위임동의를 누가 보냈는지 적어 둔다(요청서 2026-09-02).
 *
 * 환자가 받는 것은 모르는 번호에서 온 링크 하나다. 서명 화면에 콜로플라스트 이름만
 * 있어 「누가 보낸 것이냐」는 되묻는 전화가 온다 — 보낸 담당자를 화면에 세우려면
 * 누가 보냈는지 남아 있어야 하는데, 지금은 그 자취가 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->unsignedBigInteger('sent_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_consents', function (Blueprint $table) {
            $table->dropColumn('sent_by');
        });
    }
};
