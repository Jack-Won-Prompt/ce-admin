<?php
// 「검수 요청」 상태를 status 칸이 받아들이게 한다.
//
// 담당자가 수기 입력을 마치면 검수자에게 넘기는 자리에서 status 를 review_requested 로
// 적는데, 이 칸은 enum 이고 그 값이 목록에 없었다 — 「검수 요청」을 누를 때마다 저장이
// 거절되어 Server Error 로 끝났다(화면ㆍ모델에는 이미 있는 상태인데 표만 몰랐다).
//
// enum 은 목록에 없는 값을 받지 않으므로 목록에 더한다. 자리는 검수 필요 다음이다 —
// 검수 필요 → 검수 요청 → 검수 완료 순으로 흐른다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VALUES = "'pending','ocr_processing','ocr_done','review_needed','review_requested','approved','rejected','ordered'";
    private const OLD    = "'pending','ocr_processing','ocr_done','review_needed','approved','rejected','ordered'";

    public function up(): void
    {
        if (!Schema::hasColumn('prescriptions', 'status')) {
            return;
        }

        DB::statement("ALTER TABLE `prescriptions` MODIFY `status` ENUM(" . self::VALUES . ") NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('prescriptions', 'status')) {
            return;
        }

        // 되돌리기 전에 그 값을 쓰고 있는 건을 앞 단계로 내린다 — 목록에 없는 값이
        // 남아 있으면 ALTER 가 그것을 빈 문자열로 만든다
        DB::table('prescriptions')->where('status', 'review_requested')->update(['status' => 'review_needed']);
        DB::statement("ALTER TABLE `prescriptions` MODIFY `status` ENUM(" . self::OLD . ") NOT NULL DEFAULT 'pending'");
    }
};
