<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 교환ㆍ반품 배송비는 우리가 낸다(2026-09-02 자 유형표).
 *
 * 표의 일곱 줄 가운데 여섯 줄이 「콜로」다 — 단순 변심과 사이즈 교환까지 그렇다.
 * 지금 표에는 그 둘이 고객 부담으로 적혀 있어, 접수할 때마다 담당자가 손으로
 * 고쳐야 했고 고치지 않은 건은 고객에게 청구되었다.
 *
 * 자격 변경은 되돌려 받을 물건이 없어 배송비가 없다 — 지금도 비어 있다.
 *
 * 이미 접수된 건의 부담은 손대지 않는다. 그때 정한 것이고, 고객에게 이미 말했을
 * 수 있다 — 지나간 건의 조건을 뒤에서 바꾸지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('return_reasons')
            ->whereIn('code', ['change_mind', 'size_exchange'])
            ->update(['burden' => 'company', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('return_reasons')
            ->whereIn('code', ['change_mind', 'size_exchange'])
            ->update(['burden' => 'customer', 'updated_at' => now()]);
    }
};
