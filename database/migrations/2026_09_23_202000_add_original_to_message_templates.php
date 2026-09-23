<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 코드에 적힌 **원문**을 함께 담는다 (2026-09-23 지시).
 *
 * 화면의 토스트ㆍ팝업 글은 코드에 박혀 있다. 담당자가 고친 글이 실제로 뜨게 하려면
 * 「코드에 뭐라고 적혀 있었나」를 알아야 그것을 열쇠로 바꿔치기할 수 있다.
 *
 * body 는 담당자가 고치는 글이고, original 은 코드가 들고 있는 글이라 둘을 나눈다.
 * 등록할 때는 같지만, 고치고 나면 갈린다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->text('original')->nullable()->after('body')
                  ->comment('코드에 적힌 원문 — 화면 글을 바꿔치기할 때의 열쇠');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn('original');
        });
    }
};
