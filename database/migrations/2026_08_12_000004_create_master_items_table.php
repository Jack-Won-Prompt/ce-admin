<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 마스터 항목 — 병원ㆍ대리점.
 *
 * 지금까지 병원명은 처방전마다, 대리점은 상담 데이터에 글자로 적혔다. 같은 곳이라도
 * 처방전마다 따로 적히고 오타가 나면 다른 곳이 된다. 골라 쓸 목록이 필요하다.
 *
 * 카테고리를 나눠 한 표에 담는다 — 담는 항목이 거의 겹치고, 늘릴 때 표를 더 만들지
 * 않아도 된다. 무엇을 보여줄지는 config/masters.php 가 정한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->comment('hospital | dealer');
            $table->string('code', 60)->nullable()->comment('요양기관번호 · 거래처코드');
            $table->string('name', 150);
            $table->string('biz_no', 40)->nullable();
            $table->string('ceo', 60)->nullable();
            $table->string('manager', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('fax', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('note', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // 목록은 카테고리별로만 읽는다
            $table->index(['category', 'is_active']);
            // 코드는 카테고리 안에서 겹치지 않게. 비어 있는 것은 여럿이어도 된다.
            $table->index(['category', 'code']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_items');
    }
};
