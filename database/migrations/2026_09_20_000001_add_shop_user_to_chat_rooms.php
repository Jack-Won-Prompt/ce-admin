<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CE샵에서 온 대화방에 손님 정보를 적는다 (2026-09-20 확인).
 *
 * 운영에는 이 세 칸이 이미 서 있는데 **만드는 장이 없다.** 손으로 더해졌거나 덤프에만
 * 있던 칸이라, 빈 DB 에 migrate 를 돌리면 이 칸들이 빠진 채로 선다 — CE샵 대화방을
 * 읽는 코드가 그 자리에서 깨진다.
 *
 * 운영에는 이미 있으므로 이 장은 아무 일도 하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_rooms')) {
            return;
        }

        Schema::table('chat_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_rooms', 'shop_user_name')) {
                $table->string('shop_user_name', 100)->nullable();
            }
            if (! Schema::hasColumn('chat_rooms', 'shop_user_phone')) {
                $table->string('shop_user_phone', 30)->nullable();
            }
            if (! Schema::hasColumn('chat_rooms', 'shop_user_email')) {
                $table->string('shop_user_email', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        /* 되돌리지 않는다 — 운영에 이미 값이 들어 있는 칸이다. */
    }
};
