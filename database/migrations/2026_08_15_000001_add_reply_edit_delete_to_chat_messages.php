<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅 — 답글 · 수정 · 삭제.
 *
 * 정렬 규칙이 이 표의 모양을 정한다. 답글이 달리면 그 대화 묶음이 통째로 맨 아래로 내려가야 하고,
 * 묶음 안에서는 원본이 먼저, 답글이 시간순으로 와야 한다. 매번 조인·집계로 계산하면 페이징이
 * 지저분해지므로 두 값을 미리 적어 둔다.
 *
 *   thread_root_id  묶음의 대표(원본) 메시지 id — 원본 자신은 자기 id
 *   thread_at       그 묶음의 마지막 활동 시각 — 묶음 전원이 같은 값을 갖는다
 *
 * 그러면 정렬이 `ORDER BY thread_at, thread_root_id, id` 한 줄로 끝난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // 답글 대상. 원본이 지워져도 답글은 남아야 하므로 nullOnDelete.
            $table->foreignId('reply_to_id')->nullable()->after('user_id')
                  ->constrained('chat_messages')->nullOnDelete();

            $table->unsignedBigInteger('thread_root_id')->nullable()->after('reply_to_id');
            $table->timestamp('thread_at')->nullable()->after('thread_root_id');

            $table->timestamp('edited_at')->nullable()->after('attachment_size');
            // 지운 메시지도 자리는 남긴다 — 답글이 매달려 있으면 대화가 끊긴다.
            $table->softDeletes();

            $table->index(['chat_room_id', 'thread_at', 'thread_root_id', 'id'], 'chat_msg_thread_idx');
        });

        // 기존 메시지는 각자가 하나의 묶음이다.
        DB::statement('UPDATE chat_messages SET thread_root_id = id, thread_at = created_at');
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_msg_thread_idx');
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'thread_root_id', 'thread_at', 'edited_at', 'deleted_at']);
        });
    }
};
