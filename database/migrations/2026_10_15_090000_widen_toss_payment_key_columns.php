<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 토스 거래식별자(paymentKey/TID)를 담는 칸을 200자로 넓힌다 (2026-10-15).
 *
 * 토스 공지 — 2026-11-04 14:30 부터 거래식별자의 무작위성이 대폭 강화된다.
 *
 *   현재  : tossp20261010192637AbEC1  (우리 상점은 link_20261014113157rY7y8 꼴 · 24자)
 *   이후  : 규칙을 예측할 수 없는 형태
 *
 * 「길이ㆍ문자 패턴을 검사하는 로직이 있으면 즉시 제거」하라 했다. 우리는 식별자를
 * 쪼개 쓰는 자리가 없어 대부분 손댈 것이 없었는데, **담는 칸 두 개가 100자**였다.
 * 새 식별자가 그보다 길면 **조용히 잘려** 결제를 되짚을 수 없게 된다 — 돈이 걸린 표다.
 *
 * 토스 문서가 적어 둔 최대 길이(200자)에 맞춘다. 다른 표들은 이미 200자다.
 */
return new class extends Migration
{
    private const 넓힐것 = [
        'payment_events'            => 'payment_key',
        'duplicate_payment_refunds' => 'payment_key',
    ];

    public function up(): void
    {
        foreach (self::넓힐것 as $표 => $칸) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, $칸)) {
                continue;
            }

            /* doctrine/dbal 없이도 도는 길로 바꾼다 — 인덱스ㆍ유일 제약은 그대로 둔다.
               utf8mb4 에서 200자는 800바이트라 인덱스 한도(3072)에 걸리지 않는다. */
            DB::statement("ALTER TABLE `{$표}` MODIFY `{$칸}` VARCHAR(200) NULL");
        }
    }

    public function down(): void
    {
        foreach (self::넓힐것 as $표 => $칸) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, $칸)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$표}` MODIFY `{$칸}` VARCHAR(100) NULL");
        }
    }
};
