<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 메시지 유형에 「어느 화면의 어느 걸음인가」를 적어 둔다 (2026-09-23 지시).
 *
 * 여태 이 표에는 코드와 본문만 있었다. 그래서 목록을 열어도 「rx_received 가 무엇을
 * 보내는 것인지」는 코드를 뒤져야 알았고, 그 문구가 어느 화면에서 어느 단추를 눌렀을
 * 때 나가는지는 어디에도 없었다.
 *
 *   screen     어느 화면인가        (예: 주문 등록, 처방전 목록)
 *   step       어느 걸음에 나가는가 (예: 주문 연계 직후, 결제 링크 발송)
 *   variables  이 문구가 쓰는 변수  (예: #{고객명}, #{주문번호})
 *
 * 채널도 넓힌다 — 문자ㆍ알림톡에 **팝업ㆍ토스트**를 더한다. 화면에 뜨는 말도 담당자가
 * 고칠 수 있어야 한다는 것이 이번 지시다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('screen', 60)->nullable()->after('label')
                  ->comment('어느 화면에서 나가는가');
            $table->string('step', 120)->nullable()->after('screen')
                  ->comment('어느 걸음에 나가는가');
            $table->text('variables')->nullable()->after('body')
                  ->comment('이 문구가 쓰는 변수 — 쉼표로 나눈다');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['screen', 'step', 'variables']);
        });
    }
};
