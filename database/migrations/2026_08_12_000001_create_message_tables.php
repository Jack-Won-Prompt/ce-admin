<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 메시지 유형과 발송 이력.
 *
 * 지금까지 메시지 유형은 컨트롤러와 서비스 안에 배열로 박혀 있어 문구 한 줄을 고치려면
 * 배포를 해야 했다. 발송 이력은 SMS·알림톡 둘 다 남지 않아, 누구에게 무엇을 보냈는지
 * 처방전에 찍힌 시각 하나로만 짐작해야 했다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['sms', 'alimtalk'])->comment('sms=문자, alimtalk=카카오 알림톡');
            // 알림톡은 카카오에 등록한 템플릿코드가 그대로 들어간다. 문자는 우리끼리 쓰는 이름이다.
            $table->string('code', 60)->comment('유형 코드');
            $table->string('label', 100)->comment('화면에 보이는 이름');
            $table->string('description', 200)->nullable()->comment('언제 쓰는지');
            $table->text('body')->nullable()->comment('본문. #{고객명} 같은 자리표시자를 쓴다');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // 코드로 찾아 쓰므로 채널 안에서 겹치면 안 된다
            $table->unique(['channel', 'code']);
            $table->timestamps();
        });

        Schema::create('message_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['sms', 'alimtalk']);
            $table->string('template_code', 60)->nullable();
            $table->string('template_label', 100)->nullable()->comment('당시 이름. 유형이 바뀌어도 이력은 그대로 읽힌다');
            $table->text('content')->nullable()->comment('실제로 나간 본문');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);
            $table->json('receivers')->nullable()->comment('수신자 [{rcv,rcvnm,patient_id}]');
            $table->json('receipt_nums')->nullable()->comment('업체 접수번호 목록 (묶음마다 하나)');
            $table->text('error')->nullable();
            $table->string('source', 30)->default('messages')->comment('어느 화면에서 보냈나');
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_histories');
        Schema::dropIfExists('message_templates');
    }
};
