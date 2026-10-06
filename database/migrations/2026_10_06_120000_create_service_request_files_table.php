<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SR 에 붙이는 파일 (2026-10-06 · SR #82ㆍ#86).
 *
 * 여태 그림을 본문에 그대로 집어넣었다. Quill 은 붙인 그림을 base64 글자로 바꿔
 * 넣는데, `content` 는 TEXT(65,535바이트)이고 검사도 60,000자에서 끊는다. 갈무리
 * 한 장이 보통 그 몇 배라, 담당자가 화면을 붙이려 하면 「글자 초과」로 막혔다
 * (SR #86). 그래서 #87 에는 「화면을 첨부하고 싶지만 600byte 가 넘어서 올릴 수가
 * 없네요」라고 적혀 있다 — 말로만 적힌 SR 이 쌓인 까닭이다.
 *
 * 파일은 따로 담고 본문에는 주소만 둔다. 붙임 파일도 같은 표를 쓴다(SR #82).
 *
 * **아직 어느 SR 것인지 모르는 줄이 있다.** 새 SR 을 적는 중에 올린 것이다 —
 * 그때는 SR 이 아직 없다. 올린 사람만 적어 두었다가 저장할 때 그 SR 에 건다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_files', function (Blueprint $table) {
            $table->id();

            // 적는 중에 올린 것은 비어 있다 — 저장할 때 채운다
            $table->foreignId('service_request_id')->nullable()
                  ->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('original_name', 255);
            $table->string('path', 300);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            /* 본문에 끼운 그림인가, 따로 붙인 파일인가 — 화면에서 목록에 세울 것만
               가린다. 본문 그림까지 목록에 세우면 같은 것이 두 번 보인다. */
            $table->boolean('inline')->default(false);

            $table->timestamps();

            $table->index(['service_request_id', 'id']);
            $table->index(['user_id', 'service_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_files');
    }
};
