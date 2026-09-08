<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 검수 요청 메모 — 담당자가 검수를 청하며 남기는 말.
 *
 * 여태 한 칸(review_memo)에 셋이 섞였다. 담당자가 요청하며 남긴 말, 검수자가 승인하며
 * 남긴 말, 그리고 반려 사유다. 나중에 적은 것이 앞의 것을 덮어, 검수 승인 창을 비운 채
 * 누르면 담당자가 적어 둔 말이 그대로 사라졌다.
 *
 * 담당자의 말을 따로 받는다. review_memo 는 **검수자의 말**로만 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'review_request_memo')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->text('review_request_memo')->nullable()->after('review_memo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prescriptions', 'review_request_memo')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('review_request_memo');
        });
    }
};
