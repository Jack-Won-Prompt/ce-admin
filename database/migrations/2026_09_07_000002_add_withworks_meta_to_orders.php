<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스가 알려 주는 나머지 값 (2026-09-07 지시).
 *
 * 판매현황과 같은 칸을 세우라는 요청인데, 그 가운데 스무 남짓은 저쪽 창고ㆍ제품
 * 마스터에만 있다(제품그룹ㆍ바코드ㆍ등급ㆍ표준코드ㆍDescription 1~4ㆍ라인번호ㆍ
 * 발주번호ㆍ확정수량 …). 우리가 만들 수 있는 값이 아니라 받아 두는 수밖에 없다.
 *
 * 칸을 스무 개 늘리지 않는다. 저쪽이 무엇을 더 보내게 될지 지금 다 알 수 없고,
 * 그때마다 마이그레이션을 하나씩 다는 것은 받는 쪽이 할 일이 아니다.
 * 한 칸에 그대로 담고 화면이 열쇠로 꺼내 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->json('withworks_meta')->nullable()->after('withworks_deliver_warehouse')
              ->comment('위드웍스가 웹훅으로 알려 준 판매현황 값 — 우리가 만들지 않는 것들');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('withworks_meta');
        });
    }
};
