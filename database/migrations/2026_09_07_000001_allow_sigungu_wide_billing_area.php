<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 지자체는 시군구 하나가 관할이다 — 읍ㆍ면ㆍ동을 비울 수 있게 한다.
 *
 * 공단은 한 지사가 여러 동을 나눠 맡아 동으로 가려야 하지만, 지자체(의료급여)는
 * 시ㆍ군ㆍ구청 하나가 그 안을 통째로 맡는다. 그런데 emd 가 필수라 「서울특별시 중구」
 * 한 줄로는 적을 수가 없었고, 동을 스무 개 넘게 적어 두지 않으면 찾히지 않았다.
 *
 * 비면 「그 시군구 전체」라는 뜻이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_office_areas', function (Blueprint $table) {
            $table->string('emd', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        // 비어 있는 줄은 되돌릴 자리가 없다 — 지우고 되돌린다
        \DB::table('billing_office_areas')->whereNull('emd')->delete();

        Schema::table('billing_office_areas', function (Blueprint $table) {
            $table->string('emd', 40)->nullable(false)->change();
        });
    }
};
