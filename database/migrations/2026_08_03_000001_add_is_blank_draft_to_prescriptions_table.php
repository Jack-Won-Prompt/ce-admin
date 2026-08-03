<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * '신규 등록' 으로 만들어졌지만 아직 아무것도 저장하지 않은 껍데기 처방전 표식.
 *
 * 처음에는 updated_at 과 created_at 을 비교해 판별했는데, MySQL timestamp 가
 * 초 단위라 만든 뒤 같은 초에 저장하면 두 값이 같아 구분되지 않았다.
 * 추측하지 않고 컬럼 하나로 명시한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->boolean('is_blank_draft')->default(false)->after('status')
                  ->comment('신규 등록 직후의 빈 초안 — 저장되면 해제되고 목록에 나타난다');
            $table->index(['is_blank_draft', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['is_blank_draft', 'created_by']);
            $table->dropColumn('is_blank_draft');
        });
    }
};
