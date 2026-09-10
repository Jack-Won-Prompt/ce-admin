<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 웹훅 관리 — 정의ㆍ파라미터ㆍ로그 (2026-09-10 지시).
 *
 * 여태 밖과 주고받는 알림이 코드 안에만 있었다. 무엇을 어디로 받는지 알려면 파일을
 * 뒤져야 했고, 실제로 왔는지는 서버 로그를 열어야 알 수 있었다 — 토스 웹훅은
 * 한 번도 등록되지 않았는데 그 사실조차 로그를 세어 보고서야 알았다.
 *
 * 세 표로 나눈다
 *   webhooks        무엇을 어디로 (구분ㆍ이름ㆍ방향ㆍ주소)
 *   webhook_params  그 웹훅이 주고받는 값의 이름표
 *   webhook_logs    실제로 오간 것 (값ㆍ성공 여부ㆍ시각)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->id();

                // 구분 — 코드로 둔다. 팝빌ㆍNICE 처럼 뒤에 붙는 것이 있다(config/webhooks.php)
                $table->string('provider', 30)->index();
                $table->string('name', 100);                 // 웹훅 명 — 사람이 부르는 이름
                $table->string('event_code', 80)->nullable(); // 저쪽이 쓰는 이벤트 이름
                $table->string('direction', 10)->default('inbound'); // inbound · outbound
                $table->string('url', 500);
                $table->string('http_method', 10)->default('POST');

                $table->boolean('is_active')->default(true);

                /* 비밀키는 여기 담지 않는다. 어디에 있는지 이름만 적는다 —
                   화면에서 볼 수 있는 자리에 열쇠를 두지 않는다. */
                $table->string('secret_env', 60)->nullable();

                $table->string('description', 300)->nullable();
                $table->text('note')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['provider', 'direction']);
            });
        }

        if (! Schema::hasTable('webhook_params')) {
            Schema::create('webhook_params', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();

                $table->string('position', 10)->default('body');   // body · header · query · path
                $table->string('name', 100);
                $table->string('data_type', 20)->default('string');
                $table->boolean('required')->default(false);
                $table->string('sample', 200)->nullable();
                $table->string('description', 300)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['webhook_id', 'sort']);
            });
        }

        if (! Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();

                /* 정의에 없는 것이 들어와도 남긴다 — 모르는 이벤트가 온 것이야말로
                   봐야 할 일이다. 그래서 이어짐은 있어도 되고 없어도 된다. */
                $table->foreignId('webhook_id')->nullable()->constrained('webhooks')->nullOnDelete();

                // 그때의 값을 그대로 박아 둔다. 정의를 고쳐도 지난 기록은 그때 것이다.
                $table->string('provider', 30)->index();
                $table->string('event_code', 80)->nullable();
                $table->string('direction', 10)->default('inbound');
                $table->string('url', 500)->nullable();
                $table->string('http_method', 10)->default('POST');

                $table->boolean('ok')->default(false);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->boolean('signature_ok')->nullable();   // 서명이 없는 웹훅은 null

                $table->json('headers')->nullable();
                $table->longText('payload')->nullable();       // 파라미터 값
                $table->longText('response')->nullable();
                $table->text('error')->nullable();

                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('ip', 45)->nullable();

                /* 어느 건의 일인가 — 주문번호ㆍ처방번호처럼 사람이 찾는 값을 적는다 */
                $table->string('ref', 60)->nullable()->index();

                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamps();

                $table->index(['provider', 'direction']);
                $table->index(['ok', 'occurred_at']);
            });
        }

        /* 이미 도는 것부터 적어 둔다 — 표가 비어 있으면 무엇을 적는 자리인지 알기 어렵다.
           토스 둘은 아직 등록 전이라 꺼 둔 채로 세운다(2026-09-10 보고). */
        if (DB::table('webhooks')->count() === 0) {
            $이제 = now();
            $줄 = fn (array $v) => $v + ['created_at' => $이제, 'updated_at' => $이제];

            DB::table('webhooks')->insert([
                $줄([
                    'provider' => 'toss', 'name' => '결제 상태 변경', 'event_code' => 'PAYMENT_STATUS_CHANGED',
                    'direction' => 'inbound', 'url' => '/toss/webhook', 'http_method' => 'POST',
                    'is_active' => false, 'secret_env' => 'TOSS_WEBHOOK_SECRET', 'sort' => 10,
                    'description' => '카드ㆍ간편결제가 승인되면 온다. 결제창이 돌아오지 않아도 여기서 마무리한다.',
                ]),
                $줄([
                    'provider' => 'toss', 'name' => '가상계좌 입금', 'event_code' => 'DEPOSIT_CALLBACK',
                    'direction' => 'inbound', 'url' => '/toss/webhook', 'http_method' => 'POST',
                    'is_active' => false, 'secret_env' => 'TOSS_WEBHOOK_SECRET', 'sort' => 20,
                    'description' => '가상계좌에 돈이 들어오면 온다. 본문에 paymentKey 가 없어 orderId 로 찾는다.',
                ]),
                $줄([
                    'provider' => 'withworks', 'name' => '위드웍스 알림', 'event_code' => null,
                    'direction' => 'inbound', 'url' => '/api/webhook/withworks', 'http_method' => 'POST',
                    'is_active' => true, 'secret_env' => 'DEMOWORKS_WEBHOOK_SECRET', 'sort' => 30,
                    'description' => '창고 쪽에서 보내는 알림을 받는 자리 — 확정ㆍ피킹ㆍ송장ㆍ출고ㆍ배송.',
                ]),
                $줄([
                    'provider' => 'ce_shop', 'name' => 'CE 샵 주문', 'event_code' => null,
                    'direction' => 'inbound', 'url' => '/api/webhook/shop-order', 'http_method' => 'POST',
                    'is_active' => true, 'secret_env' => 'CE_SHOP_WEBHOOK_SECRET', 'sort' => 40,
                    'description' => '샵에서 주문이 들어오면 온다.',
                ]),
            ]);

            /* 토스 두 건의 파라미터 — 저쪽 문서에 적힌 것을 그대로 옮긴다 */
            $토스결제 = DB::table('webhooks')->where('event_code', 'PAYMENT_STATUS_CHANGED')->value('id');
            $토스입금 = DB::table('webhooks')->where('event_code', 'DEPOSIT_CALLBACK')->value('id');

            $칸 = fn (int $id, int $i, string $name, string $type, bool $req, ?string $sample, string $desc, string $pos = 'body') => [
                'webhook_id' => $id, 'position' => $pos, 'name' => $name, 'data_type' => $type,
                'required' => $req, 'sample' => $sample, 'description' => $desc, 'sort' => $i * 10,
                'created_at' => $이제, 'updated_at' => $이제,
            ];

            if ($토스결제) {
                DB::table('webhook_params')->insert([
                    $칸($토스결제, 1, 'eventType', 'string', true, 'PAYMENT_STATUS_CHANGED', '이벤트 이름'),
                    $칸($토스결제, 2, 'createdAt', 'datetime', true, '2026-09-10T12:00:00+09:00', '보낸 시각'),
                    $칸($토스결제, 3, 'data.paymentKey', 'string', true, 'tviva20260910...', '결제 열쇠 — 이것으로 토스에 다시 묻는다'),
                    $칸($토스결제, 4, 'data.orderId', 'string', true, 'CE-EUD...', '우리가 매긴 주문 번호'),
                    $칸($토스결제, 5, 'data.status', 'string', true, 'DONE', '결제 상태 — 그대로 믿지 않고 재조회한다'),
                    $칸($토스결제, 6, 'data.method', 'string', false, '간편결제', '무엇으로 냈는가'),
                    $칸($토스결제, 7, 'data.approvedAt', 'datetime', false, '2026-09-10T12:00:00+09:00', '승인 시각 — 결제 시각으로 쓴다'),
                ]);
            }

            if ($토스입금) {
                DB::table('webhook_params')->insert([
                    $칸($토스입금, 1, 'eventType', 'string', true, 'DEPOSIT_CALLBACK', '이벤트 이름'),
                    $칸($토스입금, 2, 'createdAt', 'datetime', true, '2026-09-10T12:00:00+09:00', '보낸 시각'),
                    $칸($토스입금, 3, 'data.orderId', 'string', true, 'CE-EUD...', '우리가 매긴 주문 번호 — 이것으로 건을 찾는다'),
                    $칸($토스입금, 4, 'data.status', 'string', true, 'DONE', 'DONE 이면 입금, CANCELED 면 입금 취소'),
                    $칸($토스입금, 5, 'data.secret', 'string', true, '(가림)', '가상계좌를 만들 때 받아 둔 값과 맞춰 본다'),
                    $칸($토스입금, 6, 'data.transactionKey', 'string', false, null, '거래 열쇠'),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhook_params');
        Schema::dropIfExists('webhooks');
    }
};
