<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 처방전 상태에 「검수 보류」와 「검수 재요청」을 더한다 (2026-09-15 지시).
 *
 * `prescriptions.status` 가 ENUM 이라, 코드에서 새 값을 쓰면 MySQL 이 조용히
 * 잘라 버린다 — 실제로 `Data truncated for column 'status'` 로 500 이 났다.
 *
 *   review_hold    파일 다시 올리기를 청해 두고 답을 기다리는 건
 *   review_resent  되물은 자료가 다시 올라와 검수를 청한 건
 *
 * 차례는 흐름을 따른다 — 검수 요청 다음, 검수 완료 앞이다. ENUM 의 차례는
 * 정렬에 쓰이므로(ORDER BY status) 아무 데나 붙이면 목록 차례가 흐트러진다.
 */
return new class extends Migration
{
    /** 지금 쓰는 값 — 옛 것(pending·ocr_*)도 그대로 둔다. 그 상태로 저장된 줄이 있다. */
    private const 새값 = [
        'pending', 'ocr_processing', 'ocr_done',
        'review_needed', 'review_requested',
        'review_hold', 'review_resent',
        'approved', 'rejected', 'ordered',
    ];

    private const 옛값 = [
        'pending', 'ocr_processing', 'ocr_done',
        'review_needed', 'review_requested',
        'approved', 'rejected', 'ordered',
    ];

    public function up(): void
    {
        $this->바꾸기(self::새값);
    }

    public function down(): void
    {
        /* 되돌리기 전에 새 값으로 저장된 줄을 먼저 옮긴다 — 그러지 않으면 ENUM 을
           좁히는 순간 그 줄들의 상태가 빈 문자열이 되어 목록에서 사라진다.

           보류ㆍ재요청 둘 다 「아직 검수가 남았다」는 뜻이므로 검수 필요로 내린다. */
        DB::table('prescriptions')
            ->whereIn('status', ['review_hold', 'review_resent'])
            ->update(['status' => 'review_needed']);

        $this->바꾸기(self::옛값);
    }

    /**
     * ENUM 을 통째로 다시 적는다.
     *
     * Laravel 의 스키마 빌더는 ENUM 변경에 doctrine/dbal 을 요구하는데 이 저장소에는
     * 없다. 원시 질의가 짧고 분명하다.
     */
    private function 바꾸기(array $값들): void
    {
        $목록 = implode(',', array_map(fn ($v) => "'" . $v . "'", $값들));

        DB::statement(
            "ALTER TABLE `prescriptions` MODIFY `status` ENUM({$목록}) NOT NULL DEFAULT 'pending'"
        );
    }
};
