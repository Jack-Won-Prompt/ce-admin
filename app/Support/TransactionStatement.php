<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * 거래명세서 — 받은 서식대로 만들어 주문의 첨부문서로 넣는다.
 *
 * 서식은 화면정의서의 「서식 파일/거래명세서.html」이다. 그 문서는 브라우저에서
 * 자바스크립트가 그리므로 PDF 로 굳힐 수 없다 — 같은 생김새를 서버가 그리게 옮겼다
 * (resources/views/documents/transaction_statement.blade.php).
 *
 * LOT 과 장비코드는 창고가 아는 값이다. 우리 주문 줄에는 없어 비워 둔다 —
 * 지어내지 않는다. 위드웍스에서 받아 올 길이 생기면 그 자리만 채우면 된다.
 */
final class TransactionStatement
{
    /** 한 장에 세우는 품목 줄 수 — 서식이 정한 값이다 */
    private const ROWS = 10;

    /**
     * 만들어 첨부문서로 넣는다. 이미 넣어 둔 것이 있으면 다시 만들지 않는다.
     *
     * @return PrescriptionAttachment|null 만들지 못했으면 null(까닭은 로그에 남는다)
     */
    public static function attach(Order $order): ?PrescriptionAttachment
    {
        $order->loadMissing(['patient', 'prescription', 'items']);

        if (!$order->prescription_id) {
            Log::info('[거래명세서] 처방이 없는 주문 — 첨부하지 않는다', ['order' => $order->order_number]);
            return null;
        }

        $existing = PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->where('doc_type', 'trade_statement')
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $pdf  = self::render($order);
            $name = '거래명세서_' . ($order->patient?->name ?? '') . '_' . $order->order_number . '.pdf';
            $path = 'attachments/' . $order->prescription_id . '/' . uniqid('ts_') . '.pdf';

            Storage::disk('public')->put($path, $pdf);

            return PrescriptionAttachment::create([
                'prescription_id'    => $order->prescription_id,
                'file_path'          => $path,
                'file_original_name' => $name,
                'file_mime_type'     => 'application/pdf',
                'file_size'          => strlen($pdf),
                'doc_type'           => 'trade_statement',
                'doc_label'          => '거래명세서',
                'display_order'      => 99,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[거래명세서] 만들지 못했다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** 서식대로 그려 PDF 바이트로 돌려준다. */
    public static function render(Order $order): string
    {
        $data = self::data($order);

        $html = View::make('documents.transaction_statement', $data)->render();

        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        // 쓰인 글자만 심는다 — 나눔고딕을 통째로 심으면 산출물이 몇 배로 커진다
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('NanumGothic');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /** 서식이 쓰는 값 한 벌 — 화면에서도 같은 것을 쓴다. */
    /**
     * 명세서에 찍는 날 — 그 건의 증빙이 선 날과 같아야 한다(2026-09-03 확정).
     *
     * 여태 만드는 날(now())을 찍었다. 어제 결제된 건을 오늘 뽑으면 오늘 날짜가
     * 나와, 명세서와 세금계산서ㆍ현금영수증의 날이 어긋났다. 세무 자료끼리 날이
     * 다르면 어느 것이 맞는지 대조할 길이 없다.
     *
     * 잣대는 「돈이 오간 날」이다.
     *
     *   본인부담이 있는 건  카드 결제일 → 현금영수증 발행일 → 세금계산서 발행일
     *                       → 입금 확인일
     *   본인부담이 0 인 건  주문 확정일
     *
     * 카드로 결제한 건은 카드 자료가 현금영수증ㆍ세금계산서를 대신하므로 카드
     * 결제일이 먼저다. 차상위경감ㆍ기초는 낼 돈이 없어 결제일이란 것이 없다 —
     * 세금계산서는 공단ㆍ지자체에 청구한 뒤에야 서는데, 명세서는 물건과 함께
     * 나가야 하므로 그날을 기다릴 수 없다. 그래서 주문이 확정된 날로 찍는다.
     *
     * 취소된 증빙은 보지 않는다 — 취소했으면 그 날은 근거가 아니다.
     */
    public static function issueDate(Order $order): string
    {
        $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d') : null;

        if ((int) $order->patient_copay > 0) {
            /* 카드 — 결제창에서 승인이 떨어진 날. 여러 번 시도한 건은 마지막 것이다. */
            $card = \Illuminate\Support\Facades\DB::table('payment_links')
                ->where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->whereNotNull('paid_at')
                ->orderByDesc('paid_at')
                ->value('paid_at');

            $d = $fmt($card)
              ?: (!$order->cash_receipt_cancelled_at ? $fmt($order->cash_receipt_issued_at) : null)
              ?: (!$order->tax_invoice_cancelled_at  ? $fmt($order->tax_invoice_issued_at)  : null)
              ?: $fmt($order->deposit_confirmed_at);

            if ($d) {
                return $d;
            }
        }

        /* 확정일 — 창고로 넘어간 날이 있으면 그날이다. 아직 안 넘어갔으면 주문이 선 날. */
        return $fmt($order->withworks_status_at) ?: $fmt($order->created_at) ?: now()->format('Y-m-d');
    }

    public static function data(Order $order): array
    {
        $rx      = $order->prescription;
        $patient = $order->patient;

        /* 품목 — 주문 줄이 정본이다. 줄이 없으면 처방 줄로 대신한다
           (품목 표가 생기기 전에 만들어진 주문). */
        $lines = $order->items->isNotEmpty() ? $order->items : ($rx?->items ?? collect());

        $items = $lines->map(fn ($i) => [
            'spec'       => (string) ($i->product_code ?? ''),
            'name'       => (string) ($i->product_name ?? ''),
            // 창고가 아는 값이다 — 우리 줄에는 없다
            'deviceCode' => '',
            'lot'        => '',
            'unit'       => 'EA',
            'qty'        => (int) ($i->quantity ?? 0),
            'price'      => (int) ($i->insurance_price ?: $i->product_price ?: 0),
        ])->values()->all();

        $totalQty = array_sum(array_column($items, 'qty'));
        $amount   = 0;
        foreach ($items as $it) {
            $amount += $it['qty'] * $it['price'];
        }

        // 서식의 vatIncluded = true 와 같다 — 단가에 부가세가 들어 있다
        $supply = (int) round($amount / 1.1);

        $company = config('popbill.company');

        return [
            'doc' => [
                'documentNo' => $order->withworks_ship_no ?: ($order->withworks_so_no ?: $order->order_number),
                'saleNo'     => $order->withworks_so_no ?: '',
                'issueDate'  => self::issueDate($order),
                'footNote'   => '',
            ],
            'partyRows' => [
                ['주민번호', self::maskedRrn($rx), '등록번호', self::bizNo()],
                ['성명',     $patient?->name ?? ($rx->patient_name_ocr ?? ''), '상호', $company['corp_name'] ?? ''],
                ['주소',     self::address($order, $rx), '주소', $company['addr'] ?? ''],
                ['연락처',   $patient?->mobile ?? ($rx->mobile_ocr ?? ''), '연락처', $company['tel'] ?? ''],
            ],
            'pages'  => $items ? array_chunk($items, self::ROWS) : [[]],
            'rows'   => self::ROWS,
            'totals' => [
                'qty'    => $totalQty,
                'amount' => $amount,
                'supply' => $supply,
                'vat'    => $amount - $supply,
            ],
            'barcode' => self::barcode(
                $order->withworks_ship_no ?: ($order->withworks_so_no ?: $order->order_number)
            ),
        ];
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 주민번호는 가려서 적는다.
     *
     * 이 서류는 환자에게 함께 나가는 종이다. 뒷자리를 그대로 찍어 보낼 까닭이 없고,
     * 찍으려면 복호화가 필요해 열람 기록이 남는다 — 가린 값은 그럴 일이 없다.
     */
    private static function maskedRrn($rx): string
    {
        return (string) ($rx?->masked_resident_no_ocr ?? '');
    }

    private static function address(Order $order, $rx): string
    {
        $addr = trim((string) ($order->shipping_address
            ?: trim(($rx->address_ocr ?? '') . ' ' . ($rx->address_detail ?? ''))));

        return $addr;
    }

    private static function bizNo(): string
    {
        $n = preg_replace('/\D/', '', (string) config('popbill.test.corp_num'));

        return strlen($n) === 10
            ? substr($n, 0, 3) . '-' . substr($n, 3, 2) . '-' . substr($n, 5)
            : $n;
    }

    /**
     * CODE128-B 바코드 — 서식의 자바스크립트를 그대로 옮겼다.
     *
     * 라이브러리를 들이지 않는다. 막대 하나가 칸 하나이므로 dompdf 도 그대로 그린다
     * (SVG 는 그리다 마는 일이 있다).
     *
     * @return array<int, array{w:float, on:bool}>
     */
    private static function barcode(string $text): array
    {
        static $C128 = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112',
        ];

        $codes = [104];          // Start B
        $sum   = 104;
        $len   = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $v = ord($text[$i]) - 32;
            if ($v < 0 || $v > 94) {
                $v = 0;
            }
            $codes[] = $v;
            $sum    += $v * ($i + 1);
        }

        $codes[] = $sum % 103;   // check digit
        $codes[] = 106;          // stop

        $pattern = '';
        foreach ($codes as $c) {
            $pattern .= $C128[$c] ?? '';
        }

        // 서식의 바코드 폭은 62mm 다. 칸 하나의 폭을 거기에 맞춘다.
        $units = 0;
        for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
            $units += (int) $pattern[$i];
        }
        $unit = $units > 0 ? 62 / $units : 0.2;

        $bars = [];
        $on   = true;
        for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
            $bars[] = ['w' => round((int) $pattern[$i] * $unit, 4), 'on' => $on];
            $on = !$on;
        }

        return $bars;
    }
}
