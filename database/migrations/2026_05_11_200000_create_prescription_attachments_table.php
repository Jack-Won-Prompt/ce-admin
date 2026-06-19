<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('doc_type', ['prescription', 'id_card', 'delegation', 'other'])->default('other');
            $table->string('doc_label', 50)->nullable(); // 사용자 지정 라벨
            $table->text('ocr_raw_text')->nullable();
            $table->tinyInteger('ocr_confidence')->default(0);
            $table->tinyInteger('display_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_attachments');
    }
};
