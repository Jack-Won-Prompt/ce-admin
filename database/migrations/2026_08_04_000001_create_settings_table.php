<?php
// 외부 서비스 키·설정 저장소.
// 항목 목록은 config/settings-schema.php 가 쥐고 있고, 이 테이블은 값만 담는다.
// 그래서 항목이 늘어도 스키마는 그대로다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);            // settings-schema 의 그룹 키
            $table->string('key', 60);              // 그룹 안의 항목 키
            // 암호화한 비밀값은 원문보다 길어진다. 넉넉히 text.
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
