<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 첨부 서류의 doc_type 을 enum 에서 풀어 준다.
 *
 * 서류 유형을 화면(환경 설정)에서 늘릴 수 있게 해 두고도, 칸은 네 가지만 받는
 * enum('prescription','id_card','delegation','other') 이었다. 등록신청서·결과지처럼
 * 예전부터 쓰던 유형조차 저장에서 잘려(Data truncated) 업로드가 통째로 실패했다.
 *
 * 무엇을 담을 수 있는지는 이제 공통 코드가 정한다 — 칸은 그 값을 담을 그릇이면 된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `prescription_attachments`
                       MODIFY `doc_type` VARCHAR(60) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        /* 되돌릴 때는 enum 밖의 값을 먼저 'other' 로 내린다 — 그러지 않으면 되돌리기 자체가
           같은 이유로 막힌다. 되돌린 뒤 그 서류들의 유형은 「기타」로 남는다. */
        DB::statement("UPDATE `prescription_attachments`
                       SET `doc_type` = 'other'
                       WHERE `doc_type` NOT IN ('prescription','id_card','delegation','other')");

        DB::statement("ALTER TABLE `prescription_attachments`
                       MODIFY `doc_type` ENUM('prescription','id_card','delegation','other')
                       NOT NULL DEFAULT 'other'");
    }
};
