<?php
// 상병 구분에 상병명이 들어갈 자리를 낸다.
//
// 이 칸은 varchar(10) 이었다 — 화면이 「1 · 2-1 · 2-2 · 3」 네 코드만 골라 넣었기
// 때문이다. 그 네 가지 목록이 틀렸고(요청), 공단 목록의 상병명을 그대로 적어야 한다.
// 「신경인성 방광 이외」처럼 열 자를 넘는 이름이 흔하므로 자리를 넓힌다.
//
// 담긴 자료는 없다(64건 모두 NULL) — 넓히기만 하면 되고 옮길 것이 없다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('prescriptions', 'disease_class')) {
            return;
        }

        // doctrine/dbal 없이도 도는 길로 간다 — 이 표는 다른 칸이 많아 change() 가 무겁다
        DB::statement('ALTER TABLE `prescriptions` MODIFY `disease_class` VARCHAR(100) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('prescriptions', 'disease_class')) {
            return;
        }

        // 되돌릴 때 열 자를 넘는 것이 있으면 잘린다 — 먼저 비운다
        DB::statement('UPDATE `prescriptions` SET `disease_class` = NULL WHERE CHAR_LENGTH(`disease_class`) > 10');
        DB::statement('ALTER TABLE `prescriptions` MODIFY `disease_class` VARCHAR(10) NULL');
    }
};
