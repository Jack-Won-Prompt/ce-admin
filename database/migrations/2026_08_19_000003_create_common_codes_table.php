<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 공통 코드.
 *
 * 화면마다 고르는 목록(서류 유형 같은 것)을 코드에 박아 두면, 한 줄 늘리는 데도 배포가
 * 필요했다. 표에 담아 두고 환경 설정 화면에서 등록·수정한다.
 *
 *   group : 무슨 목록인가 (doc_type …)
 *   kind  : 그 목록 안의 갈래 (rx=처방 서류 · claim=청구 자료 · etc=그 밖)
 *   code  : 저장에 쓰는 값. 이미 쌓인 자료와 이어지도록 쓰던 값을 그대로 옮겨 심는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_codes', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);
            $table->string('kind', 40)->nullable();
            $table->string('code', 60);
            $table->string('label', 100);
            $table->string('note', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            /* 시스템이 만드는 코드(위임장·기타)는 지우지 못하게 표시해 둔다 —
               지워 놓으면 이미 쌓인 서류가 이름을 잃는다. */
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['group', 'code']);
            $table->index(['group', 'kind', 'is_active']);
        });

        $now  = now();
        $rows = [];
        $add  = function (string $kind, string $code, string $label, int $sort, bool $system = false)
                use (&$rows, $now) {
            $rows[] = [
                'group' => 'doc_type', 'kind' => $kind, 'code' => $code, 'label' => $label,
                'sort_order' => $sort, 'is_active' => true, 'is_system' => $system,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };

        // 처방 서류 — 병원에서 받아 오는 종이다
        $add('rx', 'registration_form', '등록신청서', 10);
        $add('rx', 'prescription',      '처방전',     20);
        $add('rx', 'test_result',       '결과지',     30);
        $add('rx', 'id_card',           '신분증',     40);

        // 청구 자료 — 공단·지자체에 내는 증빙이다
        $add('claim', 'trade_statement',  '거래명세서',                 10);
        $add('claim', 'cash_receipt',     '현금영수증',                 20);
        $add('claim', 'card_sales',       '카드매출',                   30);
        $add('claim', 'tax_invoice',      '세금계산서(주민등록번호)',   40);
        $add('claim', 'purchase_confirm', '의료용품구입확인서(지자체용)', 50);
        $add('claim', 'registered_post',  '등기처리 영수증',            60);

        // 시스템이 만들거나 이름 붙일 수 없는 것들
        $add('etc', 'delegation', '위임장', 10, true);
        $add('etc', 'other',      '기타',   90, true);

        DB::table('common_codes')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('common_codes');
    }
};
