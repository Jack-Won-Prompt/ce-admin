<?php

use App\Support\PopbillLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 팝빌에 발행한 건을 우리 주문에 잇는다 (요청서 6쪽, 2026-08-31).
 *
 * 현금영수증ㆍ전자세금계산서 화면은 팝빌 목록을 그대로 비춰, 주문ㆍ처방 칸을 세울 수
 * 없었다. 이을 열쇠는 있었다 — 현금영수증은 팝빌이 주문번호를 돌려주고, 세금계산서는
 * 관리번호에 주문 id 를 심어 두었다. 다만 그것을 읽어 둔 자리가 없었다.
 *
 * 열쇠를 그때그때 파싱하지 않고 칸으로 둔다. 목록을 그릴 때마다 문자열을 뜯으면 정렬도
 * 검색도 걸 수 없고, 규칙이 바뀌면 옛 건을 영영 못 잇는다.
 *
 * 이미 쌓인 건도 함께 잇는다. 규칙에서 벗어난 것과 팝빌에서 직접 발행한 것은 빈칸으로
 * 남는다 — 짐작으로 붙이지 않는다. 남의 주문에 붙은 계산서는 정산을 통째로 어긋나게 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cashbill_records', 'popbill_taxinvoices'] as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'order_id')) {
                Schema::table($t, function (Blueprint $table) {
                    /* 주문이 지워져도 발행 기록은 남아야 한다 — 국세청에 이미 간 것이라
                       우리 쪽 사정으로 없앨 수 없다. 끊어만 둔다. */
                    $table->foreignId('order_id')->nullable()->after('id')
                          ->constrained()->nullOnDelete();
                });
            }
        }

        $this->backfill();
    }

    /** 이미 쌓인 건을 잇는다 — 못 이으면 빈칸으로 둔다 */
    private function backfill(): void
    {
        if (Schema::hasTable('cashbill_records')) {
            DB::table('cashbill_records')->whereNull('order_id')
                ->orderBy('id')->chunkById(200, function ($rows) {
                    foreach ($rows as $r) {
                        $id = PopbillLink::orderIdFor($r->mgt_key ?? null, $r->order_number ?? null);
                        if ($id) {
                            DB::table('cashbill_records')->where('id', $r->id)->update(['order_id' => $id]);
                        }
                    }
                });
        }

        if (Schema::hasTable('popbill_taxinvoices')) {
            DB::table('popbill_taxinvoices')->whereNull('order_id')
                ->orderBy('id')->chunkById(200, function ($rows) {
                    foreach ($rows as $r) {
                        if ($id = PopbillLink::fromMgtKey($r->mgt_key ?? null)) {
                            DB::table('popbill_taxinvoices')->where('id', $r->id)->update(['order_id' => $id]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['cashbill_records', 'popbill_taxinvoices'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'order_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('order_id');
                });
            }
        }
    }
};
