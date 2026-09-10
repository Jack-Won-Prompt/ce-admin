<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 웹훅을 사건마다 한 줄로 나눈다 (2026-09-10 지시).
 *
 * 여태 받는 자리 하나를 대표로 세워 두었다. 저쪽이 보내는 사건은 열 가지인데
 * 목록에는 「위드웍스 알림」 한 줄뿐이라, 무엇을 받고 있는지 화면에서 알 수 없었고
 * 로그도 모두 그 한 줄에 붙어 「출고 알림만 실패했다」가 보이지 않았다.
 *
 * 주소는 열 줄이 모두 같다(/api/webhook/withworks). 저쪽은 한 자리로 보내고
 * 본문의 event 로 무엇인지 밝힌다 — 우리가 그 event 로 갈라 세운다.
 *
 * 이미 쌓인 로그는 옛 줄에 붙어 있다. 그 줄을 지우면 로그의 이어짐만 끊기고
 * 기록 자체는 남는다(webhook_id 는 비워진다) — 그때 무슨 일이 있었는지는
 * 구분ㆍ이벤트ㆍ본문에 그대로 있다.
 */
return new class extends Migration
{
    /** 판매 — 창고가 주문을 처리하며 알려 오는 차례 */
    private const 판매 = [
        'so.confirmed' => ['확정',      '창고가 주문을 확정했습니다.', 210],
        'so.allocated' => ['할당',      '재고가 할당되었습니다.', 220],
        'so.picked'    => ['피킹',      '창고에서 물건을 집었습니다.', 230],
        'so.invoiced'  => ['송장',      '송장이 붙었습니다 — 송장번호가 함께 옵니다.', 240],
        'so.shipped'   => ['출고',      '물건이 나갔습니다. 이 시각이 우리 출고일이 됩니다.', 250],
        'so.delivered' => ['배송완료',  '배송이 끝났습니다. 저쪽에 택배사 조회가 없어 지금은 오지 않습니다(2026-08-15 합의) — 자리는 둔다.', 260],
        'so.cancelled' => ['취소',      '창고에서 주문이 취소되었습니다.', 270],
    ];

    /** 반품 — 이름을 판매와 가른다. so.* 를 쓰면 원 주문이 되돌아간다. */
    private const 반품 = [
        'ro.rcpt_completed' => ['입고완료', '반품 물건이 창고에 들어왔습니다 — 우리 쪽은 검수 단계로 옮깁니다.', 310],
        'ro.confirmed'      => ['확정',     '창고 담당자가 반품을 확정했습니다.', 320],
        'ro.cancelled'      => ['취소',     '반품이 취소되었습니다.', 330],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        // 이미 나눠 두었으면 두 번 하지 않는다
        if (DB::table('webhooks')->where('provider', 'withworks')->whereNotNull('event_code')->exists()) {
            return;
        }

        $이제 = now();
        $줄 = fn (string $code, string $이름, string $설명, int $차례) => [
            'provider'    => 'withworks',
            'name'        => $이름,
            'event_code'  => $code,
            'direction'   => 'inbound',
            'url'         => '/api/webhook/withworks',
            'http_method' => 'POST',
            'is_active'   => true,
            'secret_env'  => 'DEMOWORKS_WEBHOOK_SECRET',
            'description' => $설명,
            'sort'        => $차례,
            'created_at'  => $이제,
            'updated_at'  => $이제,
        ];

        $넣을것 = [];

        foreach (self::판매 as $code => [$이름, $설명, $차례]) {
            $넣을것[] = $줄($code, '판매 · ' . $이름, $설명, $차례);
        }

        foreach (self::반품 as $code => [$이름, $설명, $차례]) {
            $넣을것[] = $줄($code, '반품 · ' . $이름, $설명, $차례);
        }

        DB::table('webhooks')->insert($넣을것);

        /* 파라미터 — 저쪽이 싣는 칸이다. 판매와 반품이 다른 것만 갈라 단다.
           비밀은 헤더로 온다(X-Withworks-Secret) — 값은 담지 않고 이름만 적는다. */
        $칸 = [];
        $이름표 = function (int $id, int $i, string $name, string $type, bool $req, string $desc, string $pos = 'body') use ($이제, &$칸) {
            $칸[] = [
                'webhook_id' => $id, 'position' => $pos, 'name' => $name, 'data_type' => $type,
                'required' => $req, 'sample' => null, 'description' => $desc, 'sort' => $i * 10,
                'created_at' => $이제, 'updated_at' => $이제,
            ];
        };

        foreach (DB::table('webhooks')->where('provider', 'withworks')->whereNotNull('event_code')->get() as $w) {
            $반품인가 = str_starts_with($w->event_code, 'ro.');

            $이름표($w->id, 1, 'X-Withworks-Secret', 'string', true, '함께 정해 둔 비밀 — 틀리면 받지 않는다', 'header');
            $이름표($w->id, 2, 'event_id', 'string', true, '사건 번호 — 같은 사건이 두 번 와도 이것으로 거른다');
            $이름표($w->id, 3, 'event', 'string', true, '무슨 사건인가 — ' . $w->event_code);
            $이름표($w->id, 4, 'occurred_at', 'datetime', false, '저쪽에서 그 일이 일어난 때');

            if ($반품인가) {
                $이름표($w->id, 5, 'ce_return_number', 'string', false, '우리 반품 접수번호');
                $이름표($w->id, 6, 'origin_so_no', 'string', false, '되돌아온 원 판매번호');
                $이름표($w->id, 7, 'return_kind', 'string', false, '반품ㆍ교환ㆍ취소 가운데 무엇인가');
            } else {
                $이름표($w->id, 5, 'ce_order_number', 'string', false, '우리 주문번호 — 이것으로 건을 찾는다');
                $이름표($w->id, 6, 'so_no', 'string', false, '위드웍스 판매번호');
                $이름표($w->id, 7, 'so_type', 'string', false, '판매 갈래');
            }

            $이름표($w->id, 8, 'status', 'string', false, '저쪽 상태 코드');
            $이름표($w->id, 9, 'status_label', 'string', false, '저쪽 상태 이름');

            if (in_array($w->event_code, ['so.invoiced', 'so.shipped'], true)) {
                $이름표($w->id, 10, 'ship', 'object', false, '택배사ㆍ송장번호가 담긴 묶음');
            }
        }

        if ($칸) {
            DB::table('webhook_params')->insert($칸);
        }

        /* 대표로 세워 두었던 한 줄은 걷는다 — 열 줄이 그 자리를 대신한다.
           지우면 그 줄에 붙어 있던 로그의 이어짐만 끊긴다(기록은 남는다). */
        DB::table('webhooks')
            ->where('provider', 'withworks')
            ->whereNull('event_code')
            ->update(['deleted_at' => $이제, 'updated_at' => $이제]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        DB::table('webhooks')->where('provider', 'withworks')->whereNotNull('event_code')->delete();
        DB::table('webhooks')->where('provider', 'withworks')->whereNull('event_code')
            ->update(['deleted_at' => null]);
    }
};
