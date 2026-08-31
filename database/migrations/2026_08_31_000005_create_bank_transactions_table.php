<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 통장에 무엇이 들어왔는가 (요청서 5쪽, 2026-08-31).
 *
 * 팝빌 계좌조회가 긁어 오는 은행 거래내역을 그대로 담는다. 손대지 않고 받아 적는 표라,
 * 우리가 붙이는 것은 「어느 주문의 돈인가」 하나뿐이다.
 *
 * 은행이 준 것과 우리가 붙인 것을 한 표에 두되 섞지 않는다 — 위쪽은 팝빌이 주는 그대로,
 * 아래쪽은 담당자가 맞춰 둔 것이다. 다시 긁어도 위쪽만 덮고 아래쪽은 지키려면 그 경계가
 * 분명해야 한다.
 *
 * 한 입금이 여러 환자 몫일 때가 있다(지자체가 여러 건을 통으로 보낸다). 그때는 이 줄을
 * 쪼개지 않고 bank_transaction_splits 가 나눠 적는다 — 원본은 은행이 준 그대로 남아야
 * 나중에 통장과 맞춰 볼 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();

            // ── 팝빌이 주는 그대로 ────────────────────────────
            /* 거래 하나를 가리키는 팝빌의 번호. 다시 긁어도 같은 값이라 이것으로 겹침을
               막는다 — 없으면 30분마다 같은 줄이 쌓인다. */
            $table->string('tid', 100)->unique();
            $table->string('bank_code', 10)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->date('trade_date')->nullable();
            $table->dateTime('traded_at')->nullable();
            $table->string('trade_serial', 30)->nullable();
            // 입금과 출금을 한 칸에 부호로 담지 않는다 — 은행이 두 칸으로 준다
            $table->bigInteger('amount_in')->default(0);
            $table->bigInteger('amount_out')->default(0);
            $table->bigInteger('balance')->nullable();
            /* 적요ㆍ기재내용ㆍ취급점 — 은행마다 어느 칸에 무엇을 넣는지가 달라 팝빌은
               번호로만 준다. 뜻을 붙이지 않고 그대로 담는다. */
            $table->string('remark1', 200)->nullable();
            $table->string('remark2', 200)->nullable();
            $table->string('remark3', 200)->nullable();
            $table->string('remark4', 200)->nullable();
            $table->string('bank_memo', 500)->nullable();

            // ── 우리가 붙이는 것 ──────────────────────────────
            /* 어느 주문의 돈인가. 담당자가 맞추거나 이름ㆍ금액으로 우리가 짚어 준다.
               지워진 주문을 가리키지 않게 끊어 둔다 — 돈줄은 남아야 한다. */
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            /* 무엇으로 들어온 돈인가 — 본인부담금인가 기관 환급인가.
               둘은 화면의 탭이 갈리고 정산에서 세는 자리도 다르다. */
            $table->string('kind', 20)->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            // 담당자가 남기는 글. 은행이 준 bank_memo 와 다른 것이라 따로 둔다.
            $table->string('staff_memo', 500)->nullable();

            $table->timestamps();

            // 화면이 늘 기간으로 거른다
            $table->index(['trade_date', 'id']);
            $table->index(['order_id']);
        });

        /**
         * 한 입금을 환자별로 나눈 몫.
         *
         * 지자체는 여러 환자 건을 통으로 보낸다. 「환자별로 분리해야 함」이 요청서 5쪽의
         * 말이다. 원본 줄은 그대로 두고 여기서 나눈다 — 원본을 쪼개면 통장과 맞춰 볼 수
         * 없게 된다.
         */
        Schema::create('bank_transaction_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount');
            // 청구했던 기관ㆍ확인할 것 — 요청서가 「적요 메모ㆍ담당자메모」로 나눠 적었다
            $table->string('memo', 300)->nullable();
            $table->string('staff_memo', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_splits');
        Schema::dropIfExists('bank_transactions');
    }
};
