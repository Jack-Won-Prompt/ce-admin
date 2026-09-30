<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 시스템이 만드는 증빙은 처방전마다 한 장뿐임을 **표가 지킨다** (2026-09-30 지시).
 *
 * 카드매출전표ㆍ거래명세서ㆍ세금계산서 양식ㆍ현금영수증 양식ㆍ의료급여 청구서는
 * 만드는 자리마다 「먼저 조회해 보고 없으면 만든다」로 막고 있었다. 그런데 결제가
 * 끝나면 두 길이 거의 동시에 그 자리를 부른다 — 결제 화면의 승인과 토스 웹훅이다.
 * 둘 다 조회 시점에 아무것도 없어 **둘 다 만들었다.**
 *
 *   2026-09-30 17:40:59  카드매출전표 2장 · 거래명세서 2장
 *
 * 부르는 쪽에 빗장을 걸어 두었지만(DepositAutoIssue), 그것만으로는 부족하다 —
 * 부르는 자리가 여럿이고 새로 생길 수도 있다. **표에서 막으면 어느 길로 와도 막힌다.**
 *
 * ## 어떻게 막는가
 *
 * 처방전 사진처럼 여러 장이 정상인 갈래가 있어 `(prescription_id, doc_type)` 전체에
 * 유일 잣대를 걸 수는 없다. 한 장뿐이어야 하는 갈래일 때만 값이 서고 나머지는 NULL 인
 * 칸을 하나 두고, 그 칸으로 유일 잣대를 건다 — **MySQL 의 유일 잣대는 NULL 끼리는
 * 겹침으로 보지 않는다.**
 *
 * 갈래가 바뀌면(담당자가 서류 유형을 고쳐도) 칸이 따라 바뀐다 — 저장 칸(STORED)이라
 * 잣대에 쓸 수 있고, 값을 따로 채워 넣을 일이 없다.
 */
return new class extends Migration
{
    /** 처방전마다 한 장뿐이어야 하는 갈래 */
    private const 한장뿐 = [
        'card_sales', 'trade_statement', 'tax_invoice_form', 'cash_receipt_form', 'medical_aid_claim',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('prescription_attachments')) {
            return;
        }

        /* ① 이미 겹쳐 있는 것을 먼저 푼다 — 잣대를 걸려면 표가 깨끗해야 한다.
              **가장 먼저 만든 것을 남긴다.** 뒤엣것은 같은 순간에 겹쳐 생긴 것이라
              내용이 같고, 앞엣것이 다른 자리(청구 묶음 따위)에서 이미 쓰였을 수 있다. */
        $겹친것 = DB::table('prescription_attachments')
            ->select('prescription_id', 'doc_type', DB::raw('MIN(id) AS 남길것'))
            ->whereIn('doc_type', self::한장뿐)
            ->groupBy('prescription_id', 'doc_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($겹친것 as $줄) {
            DB::table('prescription_attachments')
                ->where('prescription_id', $줄->prescription_id)
                ->where('doc_type', $줄->doc_type)
                ->where('id', '>', $줄->남길것)
                ->delete();
        }

        if (Schema::hasColumn('prescription_attachments', 'single_doc_type')) {
            return;
        }

        /* ② 한 장뿐인 갈래일 때만 값이 서는 칸.
              Blueprint 로는 생성 칸을 만들 수 없어 그대로 적는다. */
        $갈래 = "'" . implode("','", self::한장뿐) . "'";

        DB::statement("
            ALTER TABLE prescription_attachments
            ADD COLUMN single_doc_type VARCHAR(50)
                GENERATED ALWAYS AS (
                    CASE WHEN doc_type IN ({$갈래}) THEN doc_type END
                ) STORED
        ");

        DB::statement('
            ALTER TABLE prescription_attachments
            ADD UNIQUE KEY uk_attach_single_doc (prescription_id, single_doc_type)
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescription_attachments')
            || ! Schema::hasColumn('prescription_attachments', 'single_doc_type')) {
            return;
        }

        DB::statement('ALTER TABLE prescription_attachments DROP INDEX uk_attach_single_doc');
        DB::statement('ALTER TABLE prescription_attachments DROP COLUMN single_doc_type');
    }
};
