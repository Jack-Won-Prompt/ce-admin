<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('id')
                ->constrained('prescriptions')->nullOnDelete()
                ->comment('연결 처방전');
            $table->string('fax_no', 20)->nullable()->after('receivers')
                ->comment('수신 팩스번호');
            $table->string('recipient_type', 20)->nullable()->after('fax_no')
                ->comment('수신처 유형 nhis|hira|custom');
            $table->json('documents')->nullable()->after('recipient_type')
                ->comment('전송 서류 목록');
            $table->string('pdf_path', 500)->nullable()->after('documents')
                ->comment('저장된 합본 PDF 경로 (storage/app/public 기준)');
        });
    }

    public function down(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropColumn(['prescription_id', 'fax_no', 'recipient_type', 'documents', 'pdf_path']);
        });
    }
};
