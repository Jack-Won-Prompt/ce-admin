<?php

namespace App\Support;

use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Support\Facades\View;

/**
 * 카드 매출전표 — 토스가 준 승인 내용을 그대로 옮겨 그린다(2026-09-04 확정).
 *
 * 카드로 받은 건은 현금영수증을 내지 않는다. 카드 매출전표가 증빙이기 때문이다
 * (둘 다 내면 같은 금액이 두 번 신고된다 — DepositAutoIssue 의 주석 참고).
 * 그런데 그 매출전표를 만들거나 받아 오는 곳이 없어, 카드 건에는 증빙이 하나도
 * 남지 않았다. 공단 청구에도 붙일 것이 없었다.
 *
 * 토스는 승인 응답에 `card` 블록과 `receipt.url`(호스팅 영수증 주소)을 준다.
 * 그 값을 우리 서식에 옮겨 그리고, 확인할 수 있게 주소도 함께 적는다 —
 * 토스 페이지는 자바스크립트로 그려지는 화면이라 그대로 PDF 로 뜰 수 없다.
 *
 * 값은 지어내지 않는다. 없는 것은 「-」로 둔다.
 */
final class CardSalesSlip
{
    /** 이 주문이 카드로 결제되어 매출전표를 만들 수 있는가 */
    public static function applies(Order $order): bool
    {
        return self::payment($order) !== null;
    }

    /** 서식대로 그려 PDF 바이트로 돌려준다. 카드 결제가 아니면 null. */
    public static function render(Order $order): ?string
    {
        $data = self::data($order);
        if (!$data) {
            return null;
        }

        $html = View::make('documents.card_sales_slip', $data)->render();

        $options = new \Dompdf\Options();
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $options->setChroot(realpath(base_path()));
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('NanumGothic');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * 만들어 첨부문서로 넣는다. 이미 넣어 둔 것이 있으면 그것을 돌려준다.
     *
     * 거래명세서와 같은 자리(PrescriptionAttachment)에 둔다 — 공단 청구 묶음도
     * 서류 관리 화면도 그 표를 본다.
     */
    public static function attach(Order $order): ?\App\Models\PrescriptionAttachment
    {
        $order->loadMissing(['patient', 'prescription']);

        if (!$order->prescription_id || !self::applies($order)) {
            return null;
        }

        $existing = \App\Models\PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->where('doc_type', 'card_sales')
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $pdf = self::render($order);
            if ($pdf === null) {
                return null;
            }

            $name = '카드매출전표_' . ($order->patient?->name ?? '') . '_' . $order->order_number . '.pdf';
            $path = 'attachments/' . $order->prescription_id . '/' . uniqid('cs_') . '.pdf';

            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf);

            return \App\Models\PrescriptionAttachment::create([
                'prescription_id'    => $order->prescription_id,
                'file_path'          => $path,
                'file_original_name' => $name,
                'file_mime_type'     => 'application/pdf',
                'file_size'          => strlen($pdf),
                'doc_type'           => 'card_sales',
                'doc_label'          => '카드매출전표',
                'display_order'      => 98,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[카드매출전표] 만들지 못했다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** 서식이 쓰는 값 한 벌. 카드 결제가 아니면 null. */
    public static function data(Order $order): ?array
    {
        $payment = self::payment($order);
        if (!$payment) {
            return null;
        }

        $raw  = json_decode((string) $payment->raw_response, true) ?: [];
        $card = $raw['card'] ?? [];
        $dash = fn ($v) => ($v === null || $v === '') ? '-' : (string) $v;

        $amount = (int) ($raw['totalAmount'] ?? $payment->amount ?? 0);
        $supply = (int) ($raw['suppliedAmount'] ?? round($amount / 1.1));
        $vat    = (int) ($raw['vat'] ?? ($amount - $supply));

        $company = config('popbill.company');

        $months = (int) ($card['installmentPlanMonths'] ?? 0);

        return [
            'doc' => [
                /* 취소된 결제는 취소거래로 선다 — 승인된 것처럼 보이면 안 된다 */
                'docKind'    => in_array($raw['status'] ?? '', ['CANCELED', 'PARTIAL_CANCELED'], true)
                    ? '취소거래' : '승인거래',
                'issuer'     => $dash(self::issuerName($card['issuerCode'] ?? null)),
                'cardNo'     => $dash($card['number'] ?? null),
                'approvalNo' => $dash($card['approveNo'] ?? null),
                'approvedAt' => $dash(self::when($raw['approvedAt'] ?? null)),
                'install'    => $months > 0 ? $months . '개월' : '일시불',
                'cardType'   => $dash($card['cardType'] ?? null),

                'buyer'       => (string) ($order->patient?->name ?? ''),
                'orderNo'     => (string) $order->order_number,
                'productName' => (string) ($order->product_name ?: ''),

                'amount' => number_format($amount),
                'supply' => number_format($supply),
                'vat'    => number_format($vat),

                /* 토스가 보여 주는 영수증 주소 — 여기 적힌 값을 그대로 대조할 수 있다 */
                'receiptUrl' => (string) ($raw['receipt']['url'] ?? ''),
            ],

            'shop' => [
                'name'  => (string) ($company['corp_name'] ?? ''),
                'bizNo' => self::bizNo((string) ($company['corp_num'] ?? config('popbill.test.corp_num'))),
                'ceo'   => (string) ($company['ceo_name'] ?? ''),
                'tel'   => (string) ($company['tel'] ?? ''),
                'addr'  => (string) ($company['addr'] ?? ''),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────

    /** 이 주문의 카드 결제 — 없으면 null */
    private static function payment(Order $order): ?TossPayment
    {
        return TossPayment::where('order_id', $order->id)
            ->whereNotNull('raw_response')
            ->get()
            ->first(function (TossPayment $p) {
                $raw = json_decode((string) $p->raw_response, true) ?: [];

                return !empty($raw['card']);
            });
    }

    /** ISO8601 을 사람이 읽는 꼴로 */
    private static function when(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($iso)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $iso;
        }
    }

    /**
     * 카드사 코드 → 이름.
     *
     * 토스가 주는 것은 두 자리 코드다. 표에 없는 코드는 코드 그대로 적는다 —
     * 모르는 것을 아는 척 적으면 대조할 때 더 헷갈린다.
     */
    private static function issuerName(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        return config('toss.issuers.' . $code, $code);
    }

    private static function bizNo(string $raw): string
    {
        $n = preg_replace('/\D/', '', $raw);

        return strlen($n) === 10
            ? substr($n, 0, 3) . '-' . substr($n, 3, 2) . '-' . substr($n, 5)
            : $n;
    }
}
