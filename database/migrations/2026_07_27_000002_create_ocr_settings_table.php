<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 처방전 OCR 공급자 설정(단일 행). 관리자 설정 화면에서 provider 를 고른다.
 * 값이 없으면 config('ocr.default_provider')(기본 textract)로 시드.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('textract');  // textract | ai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_settings');
    }
};
