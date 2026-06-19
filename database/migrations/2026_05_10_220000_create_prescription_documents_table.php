<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);          // consent | fax | cash_receipt
            $table->string('file_path', 512);
            $table->string('original_filename', 255);
            $table->timestamps();

            $table->index(['prescription_id', 'type']);
            $table->index('patient_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_documents');
    }
};
