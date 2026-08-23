<?php
// 옛 판매 유형 코드를 지금 쓰는 코드로 옮긴다.
//
// 위드웍스가 2026-08-18 코드를 개편하면서 5000·6000번대가 지워지고 1500·1600번대가
// 그 자리를 받았다. 그런데 담긴 주문은 모두 개편 전 코드를 들고 있었다(1013·1016·5001).
// 화면은 지금 고를 수 있는 유형 하나만 그리고 서버도 그것만 받으므로, 옛 코드를 든
// 주문은 손대는 순간 「The selected so type is invalid.」로 저장이 거절됐다.
//
// 1013(CE 판매)ㆍ5001(End User Direct) 을 1501(End User Direct) 로 옮긴다.
//  · 5001 → 1501 은 이름이 같다. 개편으로 번호만 바뀐 같은 유형이다.
//  · 1013 → 1501 은 요청이다.
// 1016(개인판매)은 손대지 않는다 — 뜻이 다른 유형이라 옮기라는 말을 듣기 전에는
//   섣불리 바꾸지 않는다.
//
// updated_at 은 건드리지 않는다. 코드 정리 때문에 스물몇 건이 「방금 고친 주문」으로
// 목록 맨 위에 올라오면, 정작 사람이 고친 건이 묻힌다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FROM = ['1013', '5001'];
    private const TO   = '1501';

    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'so_type')) {
            return;
        }

        DB::table('orders')->whereIn('so_type', self::FROM)->update(['so_type' => self::TO]);
    }

    public function down(): void
    {
        // 되돌리지 않는다. 어느 건이 1013 이었고 어느 건이 5001 이었는지 알 길이 없어,
        // 한 코드로 되돌리면 없던 사실을 만들어 낸다.
    }
};
