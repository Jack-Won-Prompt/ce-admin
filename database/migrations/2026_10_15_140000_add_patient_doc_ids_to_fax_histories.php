<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 팩스에 실어 보낸 **거래처 서류함의 종이**를 자취에 남긴다 (2026-10-15).
 *
 * 보내는 자리는 `patient_doc_ids` 를 받아 파일을 싣고, 기록을 남길 때도 그 값을
 * 함께 넘겼다. 그런데 표에 칸이 없고 모델의 fillable 에도 없어 **조용히 버려졌다** —
 * 무엇을 보냈는지 되짚을 수 없었다. 첨부(`attachment_ids`)는 남는데 이것만 빠졌다.
 *
 * 공단에 낸 서류가 무엇이었는지는 되짚을 수 있어야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->json('patient_doc_ids')->nullable()->after('attachment_ids');
        });
    }

    public function down(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->dropColumn('patient_doc_ids');
        });
    }
};
