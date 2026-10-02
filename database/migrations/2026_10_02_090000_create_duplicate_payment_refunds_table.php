<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 중복으로 받은 돈을 돌려주기까지의 걸음을 담는다 (2026-10-02 지시).
 *
 * ## 왜 따로 담는가
 *
 * 중복 결제는 **우리 표 어디에도 남지 않는다.**
 *
 *   toss_payments   한 주문 한 줄이라 뒤 결제가 앞 결제를 덮는다
 *   payment_links   링크는 하나뿐이고 paid_at 도 하나다
 *   payment_events  두 번째 승인 때 링크는 이미 paid 라 상태가 바뀌지 않아
 *                   PaymentLinkObserver 가 걸음을 적지 않는다
 *
 * 2026-10-01 (E)윤채우 건이 그랬다 — 토스에는 81,000원 승인이 둘(13:33:15 ·
 * 13:34:27) 살아 있는데 우리 장부는 하나만 알았다. 찾아낸 곳은 토스의 거래
 * 목록과 nginx 기록뿐이었다.
 *
 * 그러니 이 표는 **되찾은 사실을 적어 두는 자리**다. 토스가 정본이고, 여기에는
 * 「누가 돌려주자고 했고, 누가 승인했고, 언제 실제로 나갔는가」를 남긴다.
 *
 * ## 승인을 거친다
 *
 * 환불은 되돌릴 수 없고 곧 돈이 나가는 일이라 담당자 혼자 누르지 않는다
 * (2026-10-02 지시). 요청과 승인을 다른 사람이 하도록 두 자리를 나눠 둔다 —
 * 반품의 결재(OrderReturn::APPROVAL_PERMS)와 같은 결이다.
 *
 * ## 결제키가 열쇠다
 *
 * 한 결제를 두 번 무르는 일이 없어야 하므로 payment_key 에 유일 색인을 둔다.
 * 주문이 아니라 결제키로 가리는 까닭은, 한 주문에 돌려줄 결제가 둘 이상일 수
 * 있기 때문이다(세 번 결제된 건).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('duplicate_payment_refunds')) {
            return;
        }

        Schema::create('duplicate_payment_refunds', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('order_id')->index();
            /* 어느 요청에서 비롯했나 — 토스 orderId 로 되짚어 찾는다. 못 찾을 수도 있다. */
            $t->unsignedBigInteger('payment_link_id')->nullable()->index();

            /* 돌려줄 그 결제. 한 번만 무를 수 있도록 유일하게 둔다. */
            $t->string('payment_key', 100)->unique();
            /* 토스가 준 주문번호 — 저쪽 화면과 맞춰 볼 때 쓴다 */
            $t->string('toss_order_id', 120)->nullable();
            $t->string('method', 20)->nullable();

            $t->integer('amount')->default(0);
            /* 토스가 알려 준 그 결제의 승인 시각 — 어느 쪽을 무르는지 사람이 가린다 */
            $t->timestamp('approved_at')->nullable();

            /* requested  담당자가 돌려주자고 올림
             * approved   최종승인자가 승인함 (아직 토스에 가기 전)
             * done       토스에서 실제로 물렀음
             * failed     토스가 거절했음 — 까닭은 toss_response 에 남는다
             * rejected   최종승인자가 반려함 */
            $t->string('status', 20)->default('requested')->index();

            $t->unsignedBigInteger('requested_by')->nullable()->index();
            $t->timestamp('requested_at')->nullable();
            $t->string('request_note', 255)->nullable();

            $t->unsignedBigInteger('approved_by')->nullable()->index();
            $t->timestamp('approved_at_by')->nullable();
            $t->string('reject_reason', 255)->nullable();

            /* 실제로 무른 때와 저쪽이 돌려준 말 */
            $t->timestamp('refunded_at')->nullable();
            $t->json('toss_response')->nullable();

            /* 고객에게 알린 때 — 승인과 안내는 다른 걸음이다 */
            $t->timestamp('notified_at')->nullable();
            $t->string('notify_result', 255)->nullable();

            $t->timestamps();

            $t->index(['status', 'created_at']);
        });

        self::안내문구심기();
    }

    /**
     * 환불 안내 문구를 메시지 유형에 한 줄 세운다.
     *
     * 없으면 서비스가 들고 있는 기본 문구로 나가지만, 그 글은 **담당자가 고칠 수
     * 없다.** 모든 알림 문구는 message_templates 에서 고치는 것이 우리 규칙이다.
     *
     * 알림톡은 팝빌 승인을 받아야 쓸 수 있어 지금은 문자만 세운다. 승인이 나면
     * 같은 코드로 알림톡 줄을 더하면 그때부터 알림톡이 먼저 나간다
     * (MessageTemplate::채널마다).
     */
    private static function 안내문구심기(): void
    {
        if (! Schema::hasTable('message_templates')) {
            return;
        }

        $있나 = \Illuminate\Support\Facades\DB::table('message_templates')
            ->where('channel', 'sms')->where('code', 'payment_refund')->exists();

        if ($있나) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('message_templates')->insert([
            'channel'     => 'sms',
            'code'        => 'payment_refund',
            'label'       => '중복 결제 환불',
            'description' => '한 주문에 두 번 들어온 결제를 돌려줬음을 안내 — 환불 승인 직후 자동 발송',
            /* 카드 취소는 그 자리에서 통장에 꽂히지 않는다. 그 말을 적지 않으면
               「환불했다는데 안 들어왔다」는 문의가 그대로 온다. */
            'body'        => "[콜로플라스트] #{고객명}님, 중복으로 결제된 #{환불금액}원을 환불해 드렸습니다.\n"
                           . "주문번호: #{주문번호}\n"
                           . '카드 취소는 카드사에 따라 영업일 기준 3~5일이 걸릴 수 있습니다.',
            'variables'   => json_encode(['#{고객명}', '#{주문번호}', '#{환불금액}'], JSON_UNESCAPED_UNICODE),
            'sort_order'  => 95,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_payment_refunds');
    }
};
