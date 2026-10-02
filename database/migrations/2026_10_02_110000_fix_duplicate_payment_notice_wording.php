<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 중복 결제 안내 문구를 「환불」에서 「결제 취소」로 (2026-10-02 지시).
 *
 * 우리가 저쪽에 청하는 일은 `/v1/payments/{key}/cancel` — **결제 취소**다.
 * 「환불」은 그 결과로 돈이 돌아가는 것을 가리키는 말이라, 카드처럼 승인만
 * 취소되어 **애초에 청구되지 않는** 건에는 들어맞지 않는다.
 *
 * 변수 이름도 `#{환불금액}` 에서 `#{취소금액}` 으로 맞춘다. 다만 서비스는 두
 * 이름을 모두 치환하므로, 담당자가 이미 고쳐 둔 문구가 옛 이름을 쓰고 있어도
 * 그대로 나간다.
 *
 * 사람 손으로 달리 고쳐 둔 줄은 건드리지 않는다 — 처음 넣은 글 그대로인 줄만
 * 바꾼다.
 */
return new class extends Migration
{
    private const 옛글 = "[콜로플라스트] #{고객명}님, 중복으로 결제된 #{환불금액}원을 환불해 드렸습니다.\n"
                       . "주문번호: #{주문번호}\n"
                       . '카드 취소는 카드사에 따라 영업일 기준 3~5일이 걸릴 수 있습니다.';

    private const 새글 = "[콜로플라스트] #{고객명}님, 중복으로 결제된 #{취소금액}원의 결제를 취소했습니다.\n"
                       . "주문번호: #{주문번호}\n"
                       . '카드 취소는 카드사에 따라 영업일 기준 3~5일이 걸릴 수 있습니다.';

    public function up(): void
    {
        if (! Schema::hasTable('message_templates')) {
            return;
        }

        DB::table('message_templates')
            ->where('code', 'payment_refund')
            ->where('body', self::옛글)
            ->update([
                'label'       => '중복 결제 취소',
                'description' => '한 주문에 두 번 들어온 결제를 취소했음을 안내 — 취소 승인 직후 자동 발송',
                'body'        => self::새글,
                'variables'   => json_encode(['#{고객명}', '#{주문번호}', '#{취소금액}'], JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('message_templates')) {
            return;
        }

        DB::table('message_templates')
            ->where('code', 'payment_refund')
            ->where('body', self::새글)
            ->update([
                'label'      => '중복 결제 환불',
                'body'       => self::옛글,
                'variables'  => json_encode(['#{고객명}', '#{주문번호}', '#{환불금액}'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
