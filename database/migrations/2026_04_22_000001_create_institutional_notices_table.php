<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_notices', function (Blueprint $table) {
            $table->id();
            $table->string('source_org', 10);        // MOHW | HIRA | NHIS
            $table->string('notice_type', 50)->nullable();
            $table->string('title');
            $table->date('notice_date')->nullable();
            $table->longText('content')->nullable();
            $table->string('url')->unique();
            $table->string('content_hash', 64)->nullable()->index();
            $table->json('attachments')->nullable();
            $table->enum('policy_impact', ['HIGH', 'MEDIUM', 'LOW'])->default('LOW');
            $table->boolean('fee_impact')->default(false);
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index(['source_org', 'notice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_notices');
    }
};
