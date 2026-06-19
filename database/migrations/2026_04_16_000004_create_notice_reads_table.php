<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
    }
};
