<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->json('attachment_ids')->nullable()->after('documents')
                ->comment('팩스에 포함된 처방전 첨부 문서 ID 목록');
        });
    }

    public function down(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->dropColumn('attachment_ids');
        });
    }
};
