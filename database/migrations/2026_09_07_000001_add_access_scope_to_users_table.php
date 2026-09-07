<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사용자마다 어디로 들어올 수 있는지 정한다.
 *
 *   both  웹과 앱 둘 다 (기존 사용자는 모두 이 값 — 지금 쓰던 대로 이어진다)
 *   web   관리자 화면만
 *   app   모바일 앱만
 *
 * 현장에서 서류만 올리는 사람에게 관리자 화면까지 열어 둘 까닭이 없고,
 * 사무실에서만 일하는 사람이 앱에 들어갈 까닭도 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_scope', 8)->default('both')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_scope');
        });
    }
};
