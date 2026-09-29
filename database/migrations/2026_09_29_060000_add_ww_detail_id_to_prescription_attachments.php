<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 받아 온 첨부파일이 원천의 어느 줄에서 왔는지 적어 둔다 (2026-09-29 지시).
 *
 * 첨부파일은 열 만 장이 넘고 한 장씩 남의 웹서버에서 받아 온다. 받는 중에 끊기는 일이
 * 잦으므로 **이어받기**가 되어야 한다 — 이미 받은 줄을 이 번호로 가린다.
 *
 * 유일 색인을 건다. 다시 돌렸을 때 같은 사진이 두 장 쌓이면 화면에서 어느 것이 원본인지
 * 알 수 없고, S3 값도 두 배로 든다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescription_attachments')) {
            return;
        }

        Schema::table('prescription_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_attachments', 'ww_detail_id')) {
                $table->unsignedBigInteger('ww_detail_id')->nullable()->unique()
                      ->comment('위드웍스 account_add_information_details.id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescription_attachments')) {
            return;
        }

        Schema::table('prescription_attachments', function (Blueprint $table) {
            $table->dropUnique(['ww_detail_id']);
            $table->dropColumn('ww_detail_id');
        });
    }
};
