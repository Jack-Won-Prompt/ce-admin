<?php
// 처방전을 마지막으로 고친 사람을 남긴다.
// created_by(등록)·reviewed_by(검수)는 있었지만 수정한 사람은 기록이 없어,
// 검수 화면의 등록자 카드에 '수정' 줄을 채울 수 없었다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'updated_by')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            // 기존 행은 누가 고쳤는지 알 수 없으므로 비워 둔다.
            // 화면은 값이 있을 때만 '수정' 줄을 그린다.
            $table->foreignId('updated_by')->nullable()->after('created_by')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prescriptions', 'updated_by')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
