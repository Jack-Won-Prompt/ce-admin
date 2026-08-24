<?php
// 청구처 정보 — 공단 지사와 지자체 부서를, 관할 읍ㆍ면ㆍ동과 함께 적어 둔다.
//
// 지금까지는 건마다 손으로 찾았다. 공단은 지사찾기 사이트에 환자 주소를 넣어 지사를
// 고르고 보험급여부 담당자를 찾았고, 지자체는 통합 사이트조차 없어 시군구청 홈페이지에
// 들어가 부서명이 제각각인 목록에서 담당자를 찾았다(화면정의서 「청구처 정보」 14ㆍ15).
//
// 미리 다 채워 넣지 않는다. 한 번 찾은 것을 그 자리에서 적어 두고, 다음 건부터는 그것을
// 쓴다 — 쓰는 곳부터 쌓인다. 그래서 관할은 읍ㆍ면ㆍ동 하나하나로 적는다(별도 표).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_offices')) {
            Schema::create('billing_offices', function (Blueprint $table) {
                $table->id();
                /* 공단(nhis) 인가 지자체(local) 인가 — 이후 절차가 통째로 갈린다 */
                $table->string('kind', 10)->default('nhis');
                $table->string('region', 40)->nullable();       // 지역본부 · 시도
                $table->string('office_name', 100);             // 마포지사 · 마포구청
                $table->string('dept', 100)->nullable();        // 보험급여부 · 복지정책과
                $table->string('title', 40)->nullable();        // 직책
                $table->string('duty', 200)->nullable();        // 담당업무
                $table->string('tel', 40)->nullable();
                $table->string('fax', 40)->nullable();
                $table->string('address', 200)->nullable();
                $table->string('note', 200)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['kind', 'is_active']);
                $table->index('office_name');
            });
        }

        if (!Schema::hasTable('billing_office_areas')) {
            Schema::create('billing_office_areas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('billing_office_id');
                $table->string('sido', 30)->nullable();      // 서울특별시
                $table->string('sigungu', 40)->nullable();   // 마포구
                $table->string('emd', 40);                   // 용강동 — 관할을 가리는 열쇠
                $table->timestamps();

                $table->index('billing_office_id');
                /* 읍ㆍ면ㆍ동 이름은 시군구가 달라도 겹친다(중동ㆍ신흥동…).
                   찾을 때는 읍면동으로 먼저 좁히고, 여럿이면 시군구로 가린다. */
                $table->index('emd');
                $table->unique(['billing_office_id', 'sido', 'sigungu', 'emd'], 'bo_area_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_office_areas');
        Schema::dropIfExists('billing_offices');
    }
};
