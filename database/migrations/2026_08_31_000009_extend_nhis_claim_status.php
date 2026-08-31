<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 청구 상태를 일곱으로 (요청서 13쪽, 2026-08-31 회신 A).
 *
 * 요청서가 여섯을 적었다 — 청구 전ㆍ청구중ㆍ청구완료ㆍ반려ㆍ보류ㆍ취소. 여기에
 * 「승인」이 하나 더 붙어 일곱이다. 낸 것(청구완료)과 공단이 인정한 것(승인)은 다른
 * 일이라 한 칸으로 묶지 않는다(2026-08-31 회신) — 청구 관리 화면도 진작 둘을 따로
 * 세고 있었다.
 *
 * 새로 드는 둘.
 *   submitting  청구중 — 서류를 올리는 중. 냈다고 말하기는 이르다.
 *   on_hold     보류  — 잠시 멈춰 둔 건. 미청구와 다르다. 미청구는 「아직 손대지
 *                       않았다」이고 보류는 「보고서 멈췄다」라, 섞으면 무엇을
 *                       살펴봐야 하는지가 묻힌다.
 *
 * 있던 다섯은 이름도 뜻도 그대로다. 쌓인 건을 옮길 일이 없다.
 *
 * 반려 뒤의 걸음도 함께 둔다(요청서 13쪽 — 「관할 지사의 검토결과 반려 / 재신청
 * (승인대기) / 재신청완료」). 반려는 끝이 아니라 다시 내는 일의 시작이라, 어디까지
 * 갔는지를 적을 자리가 있어야 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY nhis_claim_status
            ENUM('pending','submitting','submitted','approved','rejected','on_hold','cancelled')
            NOT NULL DEFAULT 'pending'");

        if (!Schema::hasColumn('orders', 'nhis_reject_stage')) {
            Schema::table('orders', function ($table) {
                /* 반려 뒤 어디까지 갔는가 — 반려 상태일 때만 뜻이 있다.
                   상태로 두지 않는 까닭: 재신청 중에도 그 건은 여전히 반려된 건이고,
                   상태를 옮겨 버리면 「반려 몇 건」이 갑자기 줄어든다. */
                $table->string('nhis_reject_stage', 20)->nullable()->after('nhis_rejection_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'nhis_reject_stage')) {
            Schema::table('orders', fn ($t) => $t->dropColumn('nhis_reject_stage'));
        }

        // 새 값이 쓰인 건은 미청구로 돌린다 — 없는 값으로 두면 열이 좁혀지지 않는다
        DB::table('orders')->whereIn('nhis_claim_status', ['submitting', 'on_hold'])
            ->update(['nhis_claim_status' => 'pending']);

        DB::statement("ALTER TABLE orders MODIFY nhis_claim_status
            ENUM('pending','submitted','approved','rejected','cancelled')
            NOT NULL DEFAULT 'pending'");
    }
};
