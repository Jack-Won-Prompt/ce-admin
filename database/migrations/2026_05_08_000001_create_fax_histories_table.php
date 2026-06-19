<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fax_histories', function (Blueprint $table) {
            $table->id();
            $table->string('corp_num', 20)->comment('사업자번호');
            $table->string('receipt_num', 50)->unique()->comment('팝빌 접수번호');
            $table->string('sender', 30)->comment('발신번호');
            $table->string('sender_name', 100)->nullable()->comment('발신자명');
            $table->string('title', 200)->nullable()->comment('팩스 제목');
            $table->json('receivers')->comment('수신자 목록 [{rcv,rcvnm}]');
            $table->json('file_names')->comment('첨부 파일명 목록');
            $table->string('reserve_dt', 14)->nullable()->comment('예약일시 YYYYMMDDHHmmss');
            $table->string('request_num', 50)->nullable()->comment('임의 접수번호');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fax_histories');
    }
};
