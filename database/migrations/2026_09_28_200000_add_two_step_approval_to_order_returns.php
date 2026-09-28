<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 교환ㆍ반품의 두 걸음 결재 (2026-09-28 지시).
 *
 * 창고가 입고 검수를 마치고 승인을 청하면, 책임자가 보고 승인하거나 반려하고,
 * 승인된 건은 최종승인자가 서명한다. 서명이 곧 실행이다 —
 *
 *   반품ㆍ취소 · 이상 없음   토스 전액 환불
 *   반품ㆍ취소 · 이상 있음   토스 부분 환불 (차감액을 뺀 금액)
 *   교환      · 이상 있음   차액 결제 링크 (담당자가 전화한 뒤 보낸다)
 *
 * **단계는 늘리지 않는다.** 흐름의 `inspected`(검수 확정)가 책임자 승인이고
 * `approved`(전자 승인)가 최종승인자 서명이다. 단계를 늘리면 옛 건의 흐름이 끊기고
 * 목록의 「승인 대기」 셈과 기한 계산이 어긋난다.
 *
 * 승인한 자취는 이미 있는 칸을 쓴다(inspect_confirmed_by/at · approved_by/at).
 * 여기서 더하는 것은 **반려ㆍ검수 결과ㆍ서명ㆍ실행**의 자리다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            /* 창고가 검수 승인을 청한 때. 목록의 「창고 검수 요청」 칩이 이것을 본다 —
               지금은 상태가 inspecting 으로만 남아 「창고가 올린 것」과 「우리가 손으로
               옮긴 것」을 가릴 수 없다. */
            $table->timestamp('warehouse_inspect_requested_at')->nullable()->after('arrived_at');
            $table->timestamp('warehouse_inspect_seen_at')->nullable()->after('warehouse_inspect_requested_at');

            /* 입고 검수 결과 — **결재 경로를 가르는 값**이다.
               ok 면 전액(교환은 그대로 재발송), defect 면 부분 환불ㆍ차액 청구로 간다. */
            $table->string('inspect_result', 10)->nullable()->after('warehouse_inspect_seen_at');
            $table->integer('inspect_defect_qty')->nullable()->after('inspect_result');
            $table->string('inspect_defect_note', 500)->nullable()->after('inspect_defect_qty');

            /* 반품ㆍ취소면 환불에서 뺄 돈, 교환이면 고객에게 더 받을 돈.
               둘 다 「하자ㆍ수량 차이만큼의 금액」이라 한 칸에 담는다. */
            $table->integer('inspect_deduct_amount')->nullable()->after('inspect_defect_note');

            /* 저쪽이 보낸 값인가, 사람이 고른 값인가 — warehouse · manual.
               위드웍스가 inspection 을 실어 보내기 전까지는 책임자가 고른다. */
            $table->string('inspect_source', 12)->nullable()->after('inspect_deduct_amount');

            /* 반려 — 승인은 기존 칸에 남지만 반려는 담을 자리가 없었다 */
            $table->unsignedBigInteger('manager_rejected_by')->nullable()->after('inspect_confirmed_at');
            $table->timestamp('manager_rejected_at')->nullable()->after('manager_rejected_by');
            $table->string('manager_reject_reason', 500)->nullable()->after('manager_rejected_at');

            $table->unsignedBigInteger('final_rejected_by')->nullable()->after('approved_at');
            $table->timestamp('final_rejected_at')->nullable()->after('final_rejected_by');
            $table->string('final_reject_reason', 500)->nullable()->after('final_rejected_at');

            /* 어느 길로 가는가 · 어디까지 왔는가.
               상태(status)는 절차서의 단계이고, 이 둘은 결재와 실행의 자리다. */
            $table->string('refund_route', 12)->nullable()->after('final_reject_reason');
            $table->string('refund_stage', 20)->nullable()->after('refund_route');

            /* 최종승인자 서명 — delegation_signs 와 같은 꼴이다 */
            $table->string('final_sign_token', 64)->nullable()->unique()->after('refund_stage');
            $table->unsignedBigInteger('final_sign_target_user_id')->nullable()->after('final_sign_token');
            $table->string('final_sign_sent_to', 30)->nullable()->after('final_sign_target_user_id');
            $table->timestamp('final_sign_sent_at')->nullable()->after('final_sign_sent_to');
            $table->timestamp('final_sign_expires_at')->nullable()->after('final_sign_sent_at');
            $table->unsignedBigInteger('final_signed_by')->nullable()->after('final_sign_expires_at');
            $table->timestamp('final_signed_at')->nullable()->after('final_signed_by');
            $table->string('final_sign_path', 255)->nullable()->after('final_signed_at');
            $table->longText('final_sign_base64')->nullable()->after('final_sign_path');
            $table->string('final_sign_ip', 45)->nullable()->after('final_sign_base64');
            $table->string('final_sign_user_agent', 255)->nullable()->after('final_sign_ip');

            /* 실행이 실패하면 서명은 그대로 두고 다시 시도한다 — 서명을 무르면
               최종승인자에게 다시 받아야 한다. */
            $table->unsignedSmallInteger('refund_attempts')->default(0)->after('final_sign_user_agent');
            $table->string('refund_last_error', 500)->nullable()->after('refund_attempts');

            /* 교환의 차액 청구 — 어느 결제 링크로 청했나 */
            $table->unsignedBigInteger('topup_payment_link_id')->nullable()->after('refund_last_error');
            $table->timestamp('topup_sent_at')->nullable()->after('topup_payment_link_id');

            /* 목록의 칩이 이 둘로 좁힌다 — 건수가 늘면 훑는 자리다 */
            $table->index(['warehouse_inspect_requested_at', 'warehouse_inspect_seen_at'], 'or_wh_inspect_idx');
            $table->index('refund_stage', 'or_refund_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropIndex('or_wh_inspect_idx');
            $table->dropIndex('or_refund_stage_idx');

            $table->dropColumn([
                'warehouse_inspect_requested_at', 'warehouse_inspect_seen_at',
                'inspect_result', 'inspect_defect_qty', 'inspect_defect_note',
                'inspect_deduct_amount', 'inspect_source',
                'manager_rejected_by', 'manager_rejected_at', 'manager_reject_reason',
                'final_rejected_by', 'final_rejected_at', 'final_reject_reason',
                'refund_route', 'refund_stage',
                'final_sign_token', 'final_sign_target_user_id', 'final_sign_sent_to',
                'final_sign_sent_at', 'final_sign_expires_at',
                'final_signed_by', 'final_signed_at', 'final_sign_path',
                'final_sign_base64', 'final_sign_ip', 'final_sign_user_agent',
                'refund_attempts', 'refund_last_error',
                'topup_payment_link_id', 'topup_sent_at',
            ]);
        });
    }
};
