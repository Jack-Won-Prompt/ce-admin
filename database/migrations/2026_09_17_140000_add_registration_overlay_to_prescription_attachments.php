<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 올려 둔 등록신청서 그림에 신청인란을 입힌다 (2026-09-17 지시).
 *
 * 등록신청서는 병원이 ② 요양기관 확인란을 적고 도장을 찍어 내주는 종이다. 그 종이를
 * 찍거나 스캔해 올리는데, ③ 신청인란(신청인ㆍ수진자와의 관계ㆍ전화번호ㆍ서명)은
 * 비어 있다. 여태는 그 칸을 손으로 적어 다시 찍어 올렸다.
 *
 * 위임장이 하는 일과 같게 한다 — 받아 둔 전자서명과 우리가 아는 값을 그 자리에 얹는다.
 *
 *   overlay_source_path  손대지 않은 원본. 얹은 뒤에도 남겨 두어 자리를 다시 잡거나
 *                        되돌릴 수 있다. 비어 있으면 아직 얹지 않은 것이다.
 *   overlay_fields       무엇을 어디에 얹었는가. 자리는 그림 크기에 대한 몫(0~1)으로
 *                        적는다 — 찍은 사진마다 크기가 달라 mm 로는 맞출 수 없다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_attachments', function (Blueprint $table) {
            $table->string('overlay_source_path')->nullable()->after('file_path');
            $table->json('overlay_fields')->nullable()->after('overlay_source_path');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_attachments', function (Blueprint $table) {
            $table->dropColumn(['overlay_source_path', 'overlay_fields']);
        });
    }
};
