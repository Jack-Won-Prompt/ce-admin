<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 결제가 지나간 걸음을 **한 줄씩 쌓는다** (2026-09-30 지시).
 *
 * 여태 돈의 자취를 담는 표가 둘이었는데 둘 다 **덮어쓰는 표**다 —
 *
 *   toss_payments   한 주문에 한 줄. 재결제가 앞 줄을 덮는다.
 *   payment_links   한 요청에 한 줄. 승인 → 환불이 **같은 줄의 상태만** 바꾼다.
 *
 * 그래서 정정을 거친 건의 이력이 끊긴다. 2026-09-30 운영에서 그대로 드러났다 —
 *
 *   21:22  81,000원 승인            (payment_links #4 · paid)
 *   21:29  정정 → 81,000원 환불     (**같은 줄**이 refunded 로 바뀜)
 *   21:29  67,500원 재청구          (payment_links #5 · sent)
 *
 * 화면에는 `-81,000` 과 `67,500` 두 줄만 남아, **받았다는 사실이 사라졌다.**
 * 합을 세면 −13,500원이 되어 맞지 않는다. 맞으려면 `+81,000 −81,000 +67,500` 이다.
 *
 * ## 이 표는 고치지 않는다
 *
 * 한 번 적으면 바꾸지 않는다(append-only). 상태를 바꾸는 일은 여전히 payment_links
 * 가 하고, 이 표는 그 **바뀐 순간**을 하나씩 적어 둔다. 그래야 「지금 어떤가」와
 * 「어떻게 여기까지 왔나」가 서로를 지우지 않는다.
 *
 * 금액은 **부호를 담아** 적는다 — 승인은 양수, 환불ㆍ취소는 음수다. 그래야 줄을
 * 그대로 더해 지금 남은 돈이 나온다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_events')) {
            return;
        }

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('order_id')->index();
            /* 어느 요청에서 일어난 일인가 — 링크 없이 일어나는 일도 있다
               (담당자가 통장을 보고 확인한 입금 따위). */
            $t->unsignedBigInteger('payment_link_id')->nullable()->index();

            /* 무슨 일이 있었나 — sent · paid · refunded · cancelled · failed · expired */
            $t->string('kind', 20)->index();
            /* 무엇으로 — card · virtual · bank */
            $t->string('method', 20)->nullable();

            /* 부호를 담은 금액. 승인 +, 환불ㆍ취소 −. 더하면 남은 돈이다. */
            $t->integer('amount')->default(0);

            /* 언제 일어난 일인가 — 적은 때가 아니라 **일이 있었던 때**다.
               웹훅이 늦게 오면 둘이 벌어진다. */
            $t->timestamp('occurred_at')->nullable()->index();

            /* 토스가 준 열쇠 — 나중에 저쪽 화면과 맞춰 볼 때 쓴다 */
            $t->string('payment_key', 100)->nullable();
            $t->string('note', 255)->nullable();

            $t->timestamps();

            $t->index(['order_id', 'occurred_at']);
        });

        self::지난것옮기기();
    }

    /**
     * 이미 지나간 걸음을 옮겨 담는다 — 지금 남아 있는 것만큼은 되살린다.
     *
     * 덮어써진 승인은 되살릴 수 없다. 그러나 `paid_at` 이 적혀 있는 줄은 **분명히
     * 받았던 줄**이다 — 지금 상태가 환불이어도 그 사실은 남아 있다. 그 줄에서
     * 「승인」과 「환불」 두 걸음을 세워 둔다.
     */
    private static function 지난것옮기기(): void
    {
        if (! Schema::hasTable('payment_links')) {
            return;
        }

        $이제 = now();

        foreach (DB::table('payment_links')->orderBy('id')->cursor() as $l) {
            $줄들 = [];

            /* 보낸 걸음 — 돈은 아직 오가지 않았다 */
            if ($l->sent_at) {
                $줄들[] = ['kind' => 'sent', 'amount' => 0, 'at' => $l->sent_at];
            }

            /* 받은 걸음 — paid_at 이 있으면 지금 상태와 무관하게 받았던 것이다 */
            if ($l->paid_at) {
                $줄들[] = ['kind' => 'paid', 'amount' => (int) $l->amount, 'at' => $l->paid_at];
            }

            /* 되돌린 걸음 — 받은 뒤 돌려준 것(환불)과 받기 전 거둔 것(취소)은 다르다.
               언제 그랬는지는 적혀 있지 않아 고친 때로 둔다. */
            if (in_array($l->status, ['refunded', 'cancelled'], true)) {
                $줄들[] = [
                    'kind'   => $l->status,
                    'amount' => $l->paid_at ? -(int) $l->amount : 0,
                    'at'     => $l->updated_at ?? $l->created_at,
                ];
            }

            if ($l->status === 'failed') {
                $줄들[] = ['kind' => 'failed', 'amount' => 0, 'at' => $l->updated_at ?? $l->created_at];
            }

            foreach ($줄들 as $줄) {
                DB::table('payment_events')->insert([
                    'order_id'        => $l->order_id,
                    'payment_link_id' => $l->id,
                    'kind'            => $줄['kind'],
                    'method'          => $l->method,
                    'amount'          => $줄['amount'],
                    'occurred_at'     => $줄['at'],
                    'payment_key'     => $l->payment_key,
                    'note'            => '지난 자취를 옮겨 담음',
                    'created_at'      => $이제,
                    'updated_at'      => $이제,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
