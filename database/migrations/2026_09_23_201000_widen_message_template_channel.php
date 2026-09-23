<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 메시지 유형의 채널을 넓힌다 — 팝업ㆍ토스트를 더한다 (2026-09-23 지시).
 *
 * channel 이 ENUM('sms','alimtalk') 이라 화면 글을 담을 수 없었다. 값을 늘리는
 * 대신 VARCHAR 로 바꾼다 — 채널이 늘 때마다 마이그레이션을 또 쓰지 않으려는 것이고,
 * 무엇이 올 수 있는지는 MessageTemplate::CHANNELS 한 곳이 안다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE message_templates MODIFY channel VARCHAR(20) NOT NULL COMMENT '문자ㆍ알림톡ㆍ팝업ㆍ토스트'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE message_templates MODIFY channel ENUM('sms','alimtalk') NOT NULL");
    }
};
