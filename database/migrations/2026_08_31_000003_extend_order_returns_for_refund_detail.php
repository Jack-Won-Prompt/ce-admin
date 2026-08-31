<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 환불을 실제로 처리한 자취 (요청서 4쪽, 2026-08-31).
 *
 * 지금까지는 「얼마를 어느 계좌로 돌려줬는가」까지만 남았다. 그것으로는 카드 취소가
 * 승인됐는지, 무통장을 언제 물렸는지, 환불한 현금영수증 번호가 무엇인지를 알 수 없어
 * 담당자가 팝빌과 토스 화면을 따로 열어 맞춰 봐야 했다.
 *
 * 여기 두지 않은 것들 — 가상계좌 번호ㆍ은행ㆍ예금주명은 toss_payments 에 있고,
 * 결제수단은 orders.pay_method 에 있으며, 현금영수증ㆍ세금계산서 취소는 주문의
 * *_status 와 *_cancelled_at 이 이미 적고 있다. 같은 것을 두 곳에 두면 언젠가 갈린다.
 *
 * 기한도 두지 않는다 — 절차서의 기한은 입고일에서 셈해 나오는 값이라(inspectDueAt ·
 * finalDueAt) 적어 두면 규칙이 바뀔 때 옛 값이 남는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $add = function (string $name, callable $make) use ($table) {
                if (!Schema::hasColumn('order_returns', $name)) {
                    $make();
                }
            };

            // ── 카드로 돌려줄 때 ──────────────────────────────
            $add('card_issuer', fn () => $table->string('card_issuer', 50)->nullable()->after('refund_holder'));
            // 카드번호는 받지 않는다. 유효기간만으로는 결제할 수 없어 남겨도 위험이 적다.
            $add('card_expiry', fn () => $table->string('card_expiry', 7)->nullable()->after('card_issuer'));
            $add('refund_approval_no', fn () => $table->string('refund_approval_no', 50)->nullable()->after('card_expiry'));
            $add('card_cancelled_at', fn () => $table->timestamp('card_cancelled_at')->nullable()->after('refund_approval_no'));

            // ── 통장으로 돌려줄 때 ────────────────────────────
            $add('bank_cancelled_at', fn () => $table->timestamp('bank_cancelled_at')->nullable()->after('card_cancelled_at'));
            /* 취급점 — 콜로플라스트에서 환자에게 보낼 때 어느 은행 어느 점을 거쳤는가.
               통장 내역과 맞춰 볼 때 이것이 없으면 같은 금액이 여럿일 때 가려지지 않는다. */
            $add('handling_branch', fn () => $table->string('handling_branch', 100)->nullable()->after('bank_cancelled_at'));

            // ── 기관에 돌려줄 때 ──────────────────────────────
            $add('refund_agency', fn () => $table->string('refund_agency', 200)->nullable()->after('handling_branch'));

            // ── 환불분 현금영수증 ─────────────────────────────
            $add('refund_cash_receipt_no', fn () => $table->string('refund_cash_receipt_no', 50)->nullable()->after('refund_agency'));
            $add('refund_cash_receipt_type', fn () => $table->string('refund_cash_receipt_type', 20)->nullable()->after('refund_cash_receipt_no'));

            // ── 적바림 ────────────────────────────────────────
            // 적요는 통장에 찍히는 글자, 담당자메모는 우리끼리 보는 글이다. 뜻이 달라 따로 둔다.
            $add('memo', fn () => $table->string('memo', 500)->nullable()->after('refund_cash_receipt_type'));
            $add('staff_memo', fn () => $table->string('staff_memo', 500)->nullable()->after('memo'));
        });

        Schema::table('order_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_return_items', 'lot_no')) {
                /* 되돌아온 물건의 Lot. 출고 Lot(order_item_lots)과 달리 사람이 상자를 보고
                   적는다 — 창고가 알려 주는 값이 아니라 표를 따로 두지 않았다. 둘이 섞여
                   왔으면 쉼표로 잇는다. */
                $table->string('lot_no', 200)->nullable()->after('product_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_return_items', 'lot_no')) {
                $table->dropColumn('lot_no');
            }
        });

        Schema::table('order_returns', function (Blueprint $table) {
            foreach ([
                'staff_memo', 'memo', 'refund_cash_receipt_type', 'refund_cash_receipt_no',
                'refund_agency', 'handling_branch', 'bank_cancelled_at', 'card_cancelled_at',
                'refund_approval_no', 'card_expiry', 'card_issuer',
            ] as $c) {
                if (Schema::hasColumn('order_returns', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
