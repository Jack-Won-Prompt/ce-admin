<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 자료 다시 올리기 요청 (2026-09-12 지시).
 *
 * 검수하다 보면 그림이 흐려 글씨가 안 읽히거나, 처방전 자리에 다른 서류가 올라와
 * 있는 일이 있다. 여태는 전화를 걸거나 검수 메모에 적어 두는 수밖에 없었고, 올린
 * 사람은 무엇을 다시 올려야 하는지 알 길이 없었다.
 *
 * 파일 한 장을 짚어 「이것을, 이런 까닭으로 다시」를 남기고 앱으로 알린다. 남긴
 * 것은 지우지 않는다 — 몇 번을 되물었는지가 그대로 이력이 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_reupload_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id');

            /* 어느 파일인가. 처방전 본 그림은 첨부가 아니어서 담을 id 가 없다 —
               그때는 비워 두고, 그것이 곧 「본 그림」을 뜻한다. */
            $table->unsignedBigInteger('attachment_id')->nullable();

            /* 그때의 서류 이름을 그대로 적어 둔다. 첨부가 지워지고 새것이 올라오면
               id 는 남의 것을 가리키게 되는데, 이름은 그때 무엇을 물었는지 말해 준다. */
            $table->string('doc_label', 60)->nullable();

            // unreadable(잘 안 보임) · wrong_type(서류 유형이 다름) · etc(직접 씀)
            $table->string('reason', 20);
            $table->string('memo', 500)->nullable();

            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name', 50)->nullable();
            $table->timestamp('requested_at');

            /* 받는 사람 — 그 파일을 올린 사람. 이름도 함께 적는다. 사람이 나가도
               이력에서 「누구에게 물었나」가 사라지지 않게. */
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('target_user_name', 50)->nullable();

            $table->boolean('fcm_sent')->default(false);
            $table->string('fcm_error', 255)->nullable();

            /* 새 자료가 올라오면 닫힌다 */
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_attachment_id')->nullable();

            $table->timestamps();

            // 목록에서 줄마다 「아직 안 닫힌 요청이 있나」를 센다
            $table->index(['prescription_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_reupload_requests');
    }
};
