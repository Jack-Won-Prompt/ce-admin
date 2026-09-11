<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 서버에서 난 잘못을 담는 자리 (2026-09-11 지시).
 *
 * 여태는 storage/logs 의 파일에만 쌓였다. 서버에 들어가야 볼 수 있고, 하루가 지나면
 * 파일이 갈려 찾기 어려웠다 — 담당자가 「아까 그 오류」를 말해도 함께 볼 자리가 없었다.
 *
 * 같은 잘못이 되풀이되면 줄을 하나로 묶고 셈만 올린다(fingerprint). 백 번 난 잘못이
 * 백 줄이면 다른 잘못이 묻힌다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();

            /* 같은 자리에서 난 같은 잘못을 묶는 열쇠 — 갈래ㆍ파일ㆍ줄로 짓는다 */
            $table->string('fingerprint', 64)->index();

            $table->string('level', 20)->default('error')->index();   // error · critical · warning
            $table->string('kind', 80)->index();                      // 갈래 — 예외 클래스의 끝 이름
            $table->string('exception', 200)->nullable();             // 온전한 클래스 이름
            $table->unsignedSmallInteger('http_status')->nullable()->index();

            $table->text('message')->nullable();                      // 한 줄로 적힌 까닭
            $table->string('file', 300)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->longText('trace')->nullable();                    // 쌓인 자취

            /* 어느 화면에서 났는가 */
            $table->string('url', 500)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();

            /* 누가 겪었는가 — 사람이 지워져도 이름은 남는다 */
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 60)->nullable();

            /* 무엇을 보내다 났는가 — 열쇠가 될 만한 이름은 가려서 담는다 */
            $table->longText('input')->nullable();

            /* 되풀이 */
            $table->unsignedInteger('hit')->default(1);
            $table->timestamp('first_at')->nullable();
            $table->timestamp('last_at')->nullable()->index();

            /* 살펴본 자취 — 담당자가 확인했는지 */
            $table->string('status', 20)->default('open')->index();   // open · checked · fixed · ignored
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->string('memo', 500)->nullable();

            $table->timestamps();

            $table->index(['kind', 'last_at']);
            $table->index(['status', 'last_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
