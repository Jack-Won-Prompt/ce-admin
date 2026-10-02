<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「창고가 주문을 확정했습니다」를 「창고에서 주문을 확정했습니다」로 (2026-10-02 지시).
 *
 * 창고는 일을 하는 **곳**이지 주체로 읽히는 말이 아니다. 같은 자리의 다른 글은
 * 이미 「창고에서 물건을 집었습니다」ㆍ「창고에서 주문이 취소되었습니다」로 적혀
 * 있어, 이 한 줄만 어긋나 있었다.
 *
 * 글을 담는 자리가 둘이다 — 코드가 들고 있는 알림 문구(WithworksWebhookController)
 * 와 `webhooks` 표의 설명이다. 앞엣것은 코드로 고치고, 뒤엣것은 이미 담긴 값이라
 * 여기서 고친다.
 *
 * 담긴 값이 이미 사람 손으로 달리 고쳐져 있으면 건드리지 않는다 — 옛 글 그대로인
 * 줄만 바꾼다.
 */
return new class extends Migration
{
    private const 옛글 = '창고가 주문을 확정했습니다.';
    private const 새글 = '창고에서 주문을 확정했습니다.';

    public function up(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        DB::table('webhooks')
            ->where('provider', 'withworks')
            ->where('event_code', 'so.confirmed')
            ->where('description', self::옛글)
            ->update(['description' => self::새글, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        DB::table('webhooks')
            ->where('provider', 'withworks')
            ->where('event_code', 'so.confirmed')
            ->where('description', self::새글)
            ->update(['description' => self::옛글, 'updated_at' => now()]);
    }
};
