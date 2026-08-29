<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「환자 문의 리스트」 시안에 맞춰 문의 표를 넓힌다.
 *
 * 지금까지 문의는 사내 사용자끼리 주고받는 것이었다. 시안은 환자가 주인이다 —
 * 목록에 환자 이름과 연락처가 서고, 회신을 앱으로 할지 문자로 할지 전화로 할지를
 * 접수하며 고른다. 그리고 환자에게 보이는 「답변」과 안에서만 보는 「조치사항」이
 * 갈린다. 한 칸에 적으면 내부 메모가 환자 앱에 그대로 나간다.
 *
 * 상태값은 그대로 둔다(pending · processing · answered). 이미 나간 앱이
 * status === 'answered' 로 「답변완료」를 그리고 있어, 값을 갈아 끼우면 그 앱에서
 * 모든 문의가 「답변대기」로 보인다. 보이는 이름만 접수 · 처리중 · 완료로 바꾼다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // 문의자는 환자다 — 앱 계정(user_id)은 어느 계정으로 올렸는지일 뿐이다
            $table->foreignId('patient_id')->nullable()->after('user_id')
                ->constrained('patients')->nullOnDelete();

            // 앱 · 문자 · 전화. 접수하며 담당자가 고른다.
            $table->string('reply_channel', 10)->nullable()->after('category');

            // 회신할 곳. 환자 연락처가 바뀌어도 「그때 어디로 회신했는지」가 남아야 한다.
            $table->string('contact', 30)->nullable()->after('reply_channel');

            /* 조치사항 — 담당자가 무엇을 했는지. 답변과 갈라 둔다.
               답변은 환자 앱·웹에 나가고 조치사항은 안에서만 본다. */
            $table->text('action_note')->nullable()->after('answer');
        });

        /* 시험으로 넣어 둔 두 건을 지운다(2026-08-29 결정). 새 분류·회신방식이 비어 있어
           목록에서 빈 줄로만 보이고, 실제 환자 문의도 아니다. */
        DB::table('inquiry_messages')
            ->whereIn('inquiry_id', DB::table('inquiries')->pluck('id'))
            ->delete();
        DB::table('inquiries')->delete();
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropColumn(['reply_channel', 'contact', 'action_note']);
        });
    }
};
