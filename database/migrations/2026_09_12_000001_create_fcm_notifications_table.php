<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 앱으로 보낸 알림을 남긴다.
 *
 * 지금까지는 보낸 자취가 없었다. 푸시 알림은 폰의 알림창에만 떠서, 그것을 지우거나
 * 못 보고 넘기면 무엇이 왔는지 다시 볼 길이 없었다. 담당자가 「다시 올려 달라」고
 * 보낸 알림도 그렇게 사라진다.
 *
 * payload 를 그대로 담는 까닭은 화면을 잇기 위해서다. 갈래(type)와 키값을 보고
 * 앱이 해당 화면으로 간다 — chat 이면 그 대화방, rx_reupload 면 그 처방전.
 * 앱이 아직 모르는 갈래가 와도 글은 읽히도록 title·body 를 따로 담는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fcm_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();

            /** 갈래 — chat · rx_reupload 등. payload 의 type 을 꺼내 둔 것이다. */
            $table->string('type', 40)->nullable()->index();

            /** 보낸 값 전체. 화면을 이을 키가 여기 있다. */
            $table->json('payload')->nullable();

            /** 구글에 실제로 넘겼는가. 실패한 것도 이력에는 남긴다. */
            $table->boolean('sent')->default(true);

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // 내 알림을 새것부터 훑는다
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_notifications');
    }
};
