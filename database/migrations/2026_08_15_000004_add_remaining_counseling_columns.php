<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * counseling_data JSON 을 끝내기 위한 나머지 컬럼.
 *
 * 000003 에서 검색 대상 항목을 컬럼으로 뺐고, 상담 항목은 JSON 에 남겨 두었다. 그런데 값이
 * 두 곳에 사는 상태가 남으면 결국 같은 문제가 반복된다 — 모든 값을 컬럼에서 관리한다.
 *
 * 이 마이그레이션까지 적용되면 코드에서 counseling_data 를 읽거나 쓰는 곳이 없어진다.
 * (컬럼 자체는 남겨 둔다. 옛 27건의 값이 그 안에 있고, 지우는 것은 되돌릴 수 없다.)
 */
return new class extends Migration
{
    private const COLS = [
        // 상담 정보
        'counsel_no'             => 'string:50',
        'counsel_date'           => 'date',
        'counsel_type'           => 'string:10',
        'counsel_acc_add_type'   => 'string:10',
        'counsel_status'         => 'string:10',
        'counsel_call_no'        => 'string:30',
        'counsel_re_date'        => 'date',
        'counsel_contents'       => 'text',
        // 그 밖에 JSON 에만 있던 것
        'dealer_type'            => 'string:50',   // 판매거래처 유형
        'caregiver_name'         => 'string:50',   // 보호자명 (미성년 법정대리인과 다른 칸)
    ];

    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            foreach (self::COLS as $col => $type) {
                if (Schema::hasColumn('prescriptions', $col)) { continue; }
                [$kind, $len] = array_pad(explode(':', $type), 2, null);
                match ($kind) {
                    'string' => $table->string($col, (int) $len)->nullable(),
                    'date'   => $table->date($col)->nullable(),
                    'text'   => $table->text($col)->nullable(),
                };
            }
        });

        // 상담번호는 채번할 때마다 최대값을 찾는다. 인덱스가 없으면 전 건을 훑는다.
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index('counsel_no', 'rx_counsel_no_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('rx_counsel_no_idx');
            $table->dropColumn(array_keys(self::COLS));
        });
    }
};
