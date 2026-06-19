<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->boolean('is_image')->default(false);
            $table->timestamps();
        });

        // inquiries.content 를 nullable 로 변경 (메시지 테이블로 이관)
        Schema::table('inquiries', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_messages');
        Schema::table('inquiries', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });
    }
};
