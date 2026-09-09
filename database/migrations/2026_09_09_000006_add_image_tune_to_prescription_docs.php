<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 문서마다의 밝기ㆍ명암 (2026-09-09 지시).
 *
 * 휴대폰으로 찍은 종이는 한쪽이 어둡고 바탕이 잿빛으로 나온다. 그대로 공단에 내면
 * 글씨가 묻힌다.
 *
 * 예전에는 올릴 때 파일을 그 자리에서 고쳤다(ScanClean). 스캐너로 곧게 뜬 것까지
 * 나빠졌고 되돌릴 길이 없어 걷어냈다. 이번에는 **파일에 손대지 않고 숫자만 적어
 * 둔다** — 화면은 CSS 로, 공단 팩스와 서류는 GD 로 같은 값을 입힌다.
 *
 * 둘 다 -100 ~ 100. 0 이 원본이다.
 */
return new class extends Migration
{
    private const 표 = [
        'prescriptions'           => 'image_mime_type',
        'prescription_attachments' => 'file_path',
    ];

    public function up(): void
    {
        foreach (self::표 as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'img_brightness')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->smallInteger('img_brightness')->default(0)->after($after);
                $t->smallInteger('img_contrast')->default(0)->after('img_brightness');
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::표) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'img_brightness')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['img_brightness', 'img_contrast']);
            });
        }
    }
};
