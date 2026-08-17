<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 연동 설정 (단일 행).
 *
 * 지금까지는 .env 에 있었다. 그러면 테스트와 운영을 오갈 때마다 서버에 들어가 파일을 고치고
 * config:clear 를 해야 하고, 어느 쪽에 붙어 있는지 화면에서 알 수 없다. 담당자가 스스로
 * 확인하고 바꿀 수 있어야 한다.
 *
 * 테스트(데모웍스)와 운영(위드웍스)의 값을 나란히 담고 mode 로 어느 쪽을 쓸지 고른다.
 * 한 벌만 두고 갈아 끼우면 전환할 때마다 반대쪽 값을 잃는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withworks_settings', function (Blueprint $table) {
            $table->id();

            // test | production — 기본은 테스트다. 모르고 운영에 쏘는 일이 없어야 한다.
            $table->string('mode', 20)->default('test');

            $table->string('test_api_url', 190)->nullable();
            $table->text('test_api_token')->nullable();
            $table->string('test_account_id', 30)->nullable();

            $table->string('prod_api_url', 190)->nullable();
            $table->text('prod_api_token')->nullable();
            $table->string('prod_account_id', 30)->nullable();

            // 콜백은 위드웍스가 우리를 부르는 것이라 환경과 상관없이 우리 주소 하나다
            $table->string('webhook_url', 190)->nullable();
            $table->string('webhook_secret', 190)->nullable();

            // 위드웍스와 주고받는 판매유형. 지금은 End User Direct(5001) 하나만 쓴다.
            $table->string('so_type', 20)->default('5001');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withworks_settings');
    }
};
