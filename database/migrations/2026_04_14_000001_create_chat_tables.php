<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 채팅방
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();          // 그룹방 이름 (1:1은 null)
            $table->enum('type', ['direct', 'group'])->default('direct');
            $table->timestamps();
        });

        // 채팅방 참여자
        Schema::create('chat_room_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['chat_room_id', 'user_id']);
        });

        // 채팅 메시지
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_room_users');
        Schema::dropIfExists('chat_rooms');
    }
};
