<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->tinyInteger('popbill_state')->default(0)->after('request_num')
                ->comment('팝빌 전송상태 0=대기 1=전송중 2=성공 3=실패 4=취소');
            $table->integer('popbill_result')->nullable()->after('popbill_state')
                ->comment('팝빌 결과코드');
            $table->timestamp('synced_at')->nullable()->after('popbill_result')
                ->comment('마지막 팝빌 동기화 시각');
        });
    }

    public function down(): void
    {
        Schema::table('fax_histories', function (Blueprint $table) {
            $table->dropColumn(['popbill_state', 'popbill_result', 'synced_at']);
        });
    }
};
