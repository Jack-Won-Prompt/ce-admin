<?php
/* 확정 결과를 모은다 — 출고 번호는 so.confirmed 웹훅의 so_meta.ship_no 에 실려 온다. */
$root = "E:/xampp/htdocs/ce-admin";
require $root . "/vendor/autoload.php";
$app = require $root . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$표 = json_decode(file_get_contents('E:/tmp/all.json'), true);
$나옴 = [];

foreach ($표 as $x) {
    $o = \App\Models\Order::where('order_number', $x['주문'])->first();
    $ev = \App\Models\WithworksEvent::where('ce_order_number', $x['주문'])
        ->where('event', 'so.confirmed')->latest('id')->first();
    $meta = $ev->payload['so_meta'] ?? [];

    $세금 = \Spatie\Activitylog\Models\Activity::where('subject_type', 'App\\Models\\Order')
        ->where('subject_id', $o->id)->where('description', 'like', '%세금계산서 발행%')
        ->latest('id')->first();
    preg_match('/\(([A-Z0-9]+)\)/', (string) ($세금->description ?? ''), $m);

    $나옴[$x['no']] = [
        'no'        => $x['no'],
        '이름'      => $x['이름'],
        '주문'      => $o->order_number,
        '판매번호'  => $o->withworks_so_no,
        '출고번호'  => $meta['ship_no'] ?? null,
        '창고상태'  => $o->withworks_status . ' ' . $o->withworks_status_label,
        '주문상태'  => $o->status,
        '입금확인'  => $o->deposit_confirmed_at?->format('Y-m-d H:i'),
        '입금액'    => (int) $o->deposit_amount,
        '세금계산서' => $m[1] ?? null,
        '명세서일'  => $o->statement_date?->format('Y-m-d'),
        '현금영수증' => $o->cash_receipt_status,
        '출고창고'  => $meta['warehouse']['ship_from']['name'] ?? $o->withworks_warehouse,
        '확정수량'  => $meta['conf_qty'] ?? null,
        '확정때'    => $ev?->created_at?->format('Y-m-d H:i:s'),
    ];
}

file_put_contents('E:/tmp/gen/confirm.json',
    json_encode($나옴, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("%-3s %-5s %-12s %-13s %-8s %-18s %s\n",
    'no', '이름', '판매번호', '출고번호', '창고', '세금계산서', '입금확인');
foreach ($나옴 as $r) {
    printf("%-3s %-5s %-12s %-13s %-8s %-18s %s\n",
        $r['no'], $r['이름'], $r['판매번호'], $r['출고번호'] ?? '-',
        $r['창고상태'], $r['세금계산서'] ?? '-', $r['입금확인']);
}
