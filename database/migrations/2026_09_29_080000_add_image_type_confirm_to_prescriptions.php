<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 처방전 그림의 유형을 **누가 언제 확인했는가** (2026-09-29 지시).
 *
 * 첨부는 줄마다 `doc_type_by`ㆍ`doc_type_at` 으로 그 자취를 남긴다. 그런데 첫 장은 첨부
 * 줄이 아니라 처방전 제 칸(`image_path`)이라 적어 둘 자리가 없었다.
 *
 * 옮겨 온 건은 **첫 장이 처방전이라는 보장이 없다** — 저쪽에 유형이 없어 원천 번호가
 * 가장 작은 것을 첫 장으로 놓았을 뿐이다. 담당자가 보고 「처방전이 맞다」고 찍은 것과
 * 아직 아무도 보지 않은 것을 가려야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'image_type_by')) {
                $table->unsignedBigInteger('image_type_by')->nullable()
                      ->comment('처방전 그림이 처방전이 맞다고 찍은 사람');
            }

            if (! Schema::hasColumn('prescriptions', 'image_type_at')) {
                $table->timestamp('image_type_at')->nullable()
                      ->comment('처방전 그림의 유형을 찍은 때');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['image_type_by', 'image_type_at']);
        });
    }
};
