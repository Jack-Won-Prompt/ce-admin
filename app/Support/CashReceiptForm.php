<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\View;

/**
 * 현금영수증 — 받은 서식(「현금영수증_템플릿.html」)대로 그린다.
 *
 * 홈택스가 보여 주는 그 종이다. 예전에는 「국세청 현금영수증 발행 확인증」이라는
 * 두 칸짜리 표를 우리가 지어 냈다. 승인번호와 금액은 맞았지만 받는 쪽이 아는 종이가
 * 아니었고, 무엇보다 가맹점 정보가 어디에도 없었다.
 *
 * 서식이 한 벌이라 내려받는 PDF 도 공단 팩스 합본도 같은 것을 보여 준다
 * (resources/views/documents/_cash_receipt_form.blade.php).
 */
final class CashReceiptForm
{
    /** 서식대로 그려 PDF 바이트로 돌려준다. */
    public static function render(Order $order): string
    {
        $html = View::make('documents.cash_receipt', self::data($order))->render();

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

    /** 서식이 쓰는 값 한 벌. */
    public static function data(Order $order): array
    {
        $order->loadMissing('patient');

        $amount = (int) $order->cash_receipt_amount;
        $supply = (int) round($amount / 1.1);

        $company = config('popbill.company');

        return [
            'doc' => [
                'identifier'  => self::maskIdentifier((string) $order->cash_receipt_identifier),
                // 취소된 건은 취소거래로 선다 — 승인된 것처럼 보이면 안 된다
                'docKind'     => $order->cash_receipt_cancelled_at ? '취소거래' : '승인거래',
                'purpose'     => $order->cash_receipt_type === 'income_deduction' ? '소득공제용' : '지출증빙용',
                'dealType'    => '일반',
                'issuedAt'    => $order->cash_receipt_issued_at?->format('Y-m-d H:i:s') ?? '-',
                // 국세청 전송일은 우리가 받아 두지 않는다 — 지어내지 않고 비운다
                'sentAt'      => '-',
                'approvalNo'  => (string) ($order->cash_receipt_no ?? ''),

                'buyer'       => (string) ($order->patient?->name ?? ''),
                'orderNo'     => (string) $order->order_number,
                'productName' => (string) ($order->product_name ?: ''),

                'amount'      => number_format($amount),
                'supply'      => number_format($supply),
                'vat'         => number_format($amount - $supply),
                'tip'         => '0',
            ],

            'shop' => [
                'name'   => (string) ($company['corp_name'] ?? ''),
                'bizNo'  => self::bizNo((string) config('popbill.test.corp_num')),
                'subBiz' => '',
                'ceo'    => (string) ($company['ceo_name'] ?? ''),
                'tel'    => (string) ($company['tel'] ?? ''),
                'addr'   => (string) ($company['addr'] ?? ''),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 식별번호는 가려서 적는다 — 홈택스가 그렇게 보여 준다.
     *
     * 휴대폰번호는 가운데를 가리고(010-****-0084), 사업자번호는 그대로 적는다
     * (가리는 값이 아니다). 주민등록번호로 낸 건은 뒷자리를 가린다.
     */
    private static function maskIdentifier(string $raw): string
    {
        $n = preg_replace('/\D/', '', $raw);

        return match (strlen($n)) {
            11      => substr($n, 0, 3) . '-****-' . substr($n, 7),
            13      => substr($n, 0, 6) . '-' . substr($n, 6, 1) . '******',
            10      => substr($n, 0, 3) . '-' . substr($n, 3, 2) . '-' . substr($n, 5),
            default => $raw,
        };
    }

    private static function bizNo(string $raw): string
    {
        $n = preg_replace('/\D/', '', $raw);

        return strlen($n) === 10
            ? substr($n, 0, 3) . '-' . substr($n, 3, 2) . '-' . substr($n, 5)
            : $n;
    }
}
