<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SR(Service Request) — 화면·기능 요청과 그에 대한 답변을 담는다.
 * 상단 SR 관리 패널과 사이드바 'SR 관리' 화면이 같은 테이블을 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // 등록자
            $table->string('title', 200);
            $table->text('content');
            $table->string('category', 20)->default('improve');   // improve|bug|question|etc
            $table->string('priority', 10)->default('normal');     // low|normal|high|urgent
            $table->string('status', 20)->default('open');         // open|in_progress|answered|closed
            // 등록 당시 보고 있던 화면 — 어느 화면에 대한 요청인지 추적용
            $table->string('page_label', 100)->nullable();
            $table->string('page_url', 300)->nullable();
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
