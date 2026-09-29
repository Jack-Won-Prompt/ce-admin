<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 첨부의 서류 유형을 **누가 언제 확인했는가** (2026-09-29 지시).
 *
 * 위드웍스에서 옮겨 온 첨부는 유형을 알 수 없다 — 저쪽 표의 `udf1~udf10` 은 열 칸 모두
 * 비어 있고, 저쪽 업로드 화면에도 유형을 고르는 자리가 없다. 파일 이름으로 가려 보면
 * 열에 아홉이 카카오톡 자동 이름이거나 뜻 없는 이름이다.
 *
 * 그래서 옮겨 온 것은 모두 「처방전」으로 담아 두었다. 그런데 그 상태로는 공단 팩스가
 * 찾는 네 유형(등록신청서ㆍ결과지ㆍ요양비위임장ㆍ신분증)이 「없다」로 읽혀 막힌다.
 *
 * 없는 정보를 짐작으로 채우지 않는다. 담당자가 미리보기로 보고 찍은 것만 쌓는다 —
 * 이 두 칸이 그 자취다. 둘 다 비어 있으면 「유형 미확인」이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescription_attachments')) {
            return;
        }

        Schema::table('prescription_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_attachments', 'doc_type_by')) {
                $table->unsignedBigInteger('doc_type_by')->nullable()
                      ->comment('서류 유형을 확인해 찍은 사람');
            }

            if (! Schema::hasColumn('prescription_attachments', 'doc_type_at')) {
                $table->timestamp('doc_type_at')->nullable()
                      ->comment('서류 유형을 찍은 때');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescription_attachments')) {
            return;
        }

        Schema::table('prescription_attachments', function (Blueprint $table) {
            $table->dropColumn(['doc_type_by', 'doc_type_at']);
        });
    }
};
