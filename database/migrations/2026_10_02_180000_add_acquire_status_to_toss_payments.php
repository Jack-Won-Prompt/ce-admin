<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 카드 매입 상태를 따로 담는다 (2026-10-02 지시).
 *
 * 「토스페이먼츠 결제하면, 매입전인지 매입후인지도 화면에서 보이게」
 *
 * ## 담긴 값으로는 알 수 없다
 *
 * `raw_response` 에 `card.acquireStatus` 가 들어 있기는 하다. 그런데 그것은 **승인한
 * 그 순간의 사본**이다. 카드사 매입은 보통 다음 영업일에 끝나는데, 우리 사본은 그때
 * 바뀌지 않는다 — 운영의 카드 결제 23건이 **모두 `READY`** 다(2026-10-02 확인).
 * 한 달 전 결제도 그대로 `READY` 라, 그 값을 화면에 그대로 적으면 **영영 「매입 전」**
 * 이라 보인다.
 *
 * 그래서 토스에 다시 물어 받은 값을 **따로** 담는다. 언제 물었는지도 함께 적어,
 * 오래된 값인지 사람이 알 수 있게 한다.
 *
 * ## 왜 중요한가
 *
 * 취소에 걸리는 시간이 갈린다(토스 고객센터 안내).
 *
 *   매입 전 취소(전체) : 결제 **당일에만** 가능 · 즉시
 *   매입 전 취소(부분) : 영업일 3~4일
 *   매입 후 취소       : 영업일 3~4일
 *
 * 담당자가 「지금 취소하면 바로 되는가」를 알아야 고객에게 답할 수 있다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('toss_payments')) {
            return;
        }

        Schema::table('toss_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('toss_payments', 'acquire_status')) {
                /* 토스가 주는 값 그대로 — READY · REQUESTED · COMPLETED ·
                   CANCEL_REQUESTED · CANCELED. 우리 말로 바꾸는 일은 화면이 한다. */
                $table->string('acquire_status', 20)->nullable()->after('status');
            }

            if (! Schema::hasColumn('toss_payments', 'acquire_checked_at')) {
                /* 언제 물어본 값인가 — 오래된 값을 「지금 그렇다」고 읽지 않게 */
                $table->timestamp('acquire_checked_at')->nullable()->after('acquire_status');
            }
        });

        /* 승인할 때 받아 둔 사본에서 **첫 값만** 옮겨 적는다.

           다시 물어보기 전까지의 자리 메움이다. `acquire_checked_at` 은 비워 둔다 —
           물어본 적이 없다는 뜻이고, 화면은 그때 「확인 전」이라 적는다. 여기에
           지금 시각을 적으면 승인 때의 옛 값이 방금 확인한 값으로 둔갑한다. */
        if (Schema::hasColumn('toss_payments', 'raw_response')) {
            foreach (\App\Models\TossPayment::whereNull('acquire_status')
                         ->whereNotNull('raw_response')->cursor() as $줄) {
                $raw = is_array($줄->raw_response)
                    ? $줄->raw_response
                    : (json_decode((string) $줄->raw_response, true) ?: []);

                $값 = $raw['card']['acquireStatus'] ?? null;

                if (is_string($값) && $값 !== '') {
                    $줄->forceFill(['acquire_status' => $값])->saveQuietly();
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('toss_payments')) {
            return;
        }

        Schema::table('toss_payments', function (Blueprint $table) {
            foreach (['acquire_status', 'acquire_checked_at'] as $칸) {
                if (Schema::hasColumn('toss_payments', $칸)) {
                    $table->dropColumn($칸);
                }
            }
        });
    }
};
