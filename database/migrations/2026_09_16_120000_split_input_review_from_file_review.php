<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 입력 검수를 파일 검수에서 떼어 낸다 (2026-09-16 지시).
 *
 * 업무상 검수는 둘이다.
 *
 *   파일 검수  처방전 목록에서 — 올라온 처방전ㆍ서류 이미지가 제대로인가
 *   입력 검수  주문 등록에서   — 담당자가 적어 넣은 환자ㆍ병원ㆍ처방 값이 제대로인가
 *
 * 그런데 둘 다 prescriptions.status 와 reviewed_at 하나를 함께 썼다. 그래서
 *
 *   · 파일 검수만 승인해도 주문 등록의 두 단추가 「입력 검수 승인됨」으로 잠겼다.
 *     입력값은 승인 시점에 존재하지도 않았는데 승인된 것으로 보였다.
 *   · 이력이 양쪽 모두 「검수 승인」이라, 무엇을 본 것인지 가릴 수 없었다.
 *   · 파일 검수가 끝난 건(approved)은 입력 검수 요청이 422 로 막혀, 정상 흐름에서는
 *     입력 검수 요청을 누를 창이 사실상 없었다.
 *   · 입력값을 다시 봐 달라고 요청하면 처방전 목록의 파일 검수까지 함께 풀렸다.
 *
 * 입력 검수 전용 칸을 둔다. 파일 검수 쪽(status·reviewed_at·reviewed_by)은 손대지 않는다.
 *
 * **지난 건은 비워 둔다.** 소급해서 「입력 검수를 했다」고 적을 근거가 없다 —
 * 그 승인은 파일을 본 것이었다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            /* null = 아직 요청 전 · requested = 요청됨 · approved = 승인됨 */
            $table->string('input_review_status', 20)->nullable()->after('review_request_memo');

            $table->timestamp('input_review_requested_at')->nullable()->after('input_review_status');
            $table->unsignedBigInteger('input_review_requested_by')->nullable()->after('input_review_requested_at');
            $table->string('input_review_request_memo', 500)->nullable()->after('input_review_requested_by');

            $table->timestamp('input_review_approved_at')->nullable()->after('input_review_request_memo');
            $table->unsignedBigInteger('input_review_approved_by')->nullable()->after('input_review_approved_at');
            $table->string('input_review_memo', 500)->nullable()->after('input_review_approved_by');

            /* 목록에서 「입력 검수를 기다리는 건」을 모아 보는 자리가 생긴다 */
            $table->index('input_review_status', 'prescriptions_input_review_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('prescriptions_input_review_status_idx');
            $table->dropColumn([
                'input_review_status',
                'input_review_requested_at',
                'input_review_requested_by',
                'input_review_request_memo',
                'input_review_approved_at',
                'input_review_approved_by',
                'input_review_memo',
            ]);
        });
    }
};
