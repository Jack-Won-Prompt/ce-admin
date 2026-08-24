<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\View;

/**
 * 전자세금계산서 — 국세청 별지 제11호 서식대로 그린다.
 *
 * 예전에는 「발행 확인증」이라는 이름의 표 한 장이었다. 승인번호와 금액은 맞았지만
 * 세금계산서가 아니었다 — 받는 쪽이 아는 종이는 붉은 선이 박힌 그 서식뿐이다.
 * 서식은 「서식 파일/청구서 정보/p07_e_tax_invoice.pdf」에서 옮겼다.
 *
 * 종이에 적히는 값은 팝빌에 실제로 신고된 값과 같아야 한다. 그래서 품목 줄도
 * 신고한 그대로 한 줄이다(품명ㆍ수량 1ㆍ단가 = 공급가액). 주문의 품목 줄을 여기서만
 * 펼쳐 적으면 종이와 신고가 어긋난다 — 어긋난 종이가 남는 쪽이 더 나쁘다.
 */
final class TaxInvoiceForm
{
    /** 한 장에 세우는 품목 줄 수 — 서식이 정한 값이다 */
    private const ROWS = 4;

    /** 공급가액 칸 — 십조부터 일까지 열네 자리 */
    private const SUPPLY_HEAD = ['십', '조', '천', '백', '십', '억', '천', '백', '십', '만', '천', '백', '십', '일'];

    /** 세액 칸 — 조부터 일까지 열세 자리 */
    private const VAT_HEAD = ['조', '천', '백', '십', '억', '천', '백', '십', '만', '천', '백', '십', '일'];

    /** 서식대로 그려 PDF 바이트로 돌려준다. */
    public static function render(Order $order): string
    {
        $html = View::make('documents.tax_invoice', self::data($order))->render();

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

        $supply = (int) $order->tax_invoice_supply;
        $vat    = (int) $order->tax_invoice_vat;
        $total  = $supply + $vat;
        $at     = $order->tax_invoice_issued_at ?? now();

        $company = config('popbill.company');

        /* 「이 금액을 [영수] 함」 — 발행이 입금 확인 뒤에 이뤄지므로 영수다.
           팝빌에 보내는 purposeType 도 같은 값이라 종이와 신고가 어긋나지 않는다. */
        $received = $order->isDepositConfirmed();

        return [
            'doc' => [
                'ntsNo'      => (string) ($order->tax_invoice_no ?? ''),
                'year'       => $at->format('Y'),
                'month'      => (string) (int) $at->format('m'),
                'day'        => (string) (int) $at->format('d'),
                'blankCount' => (string) max(0, count(self::SUPPLY_HEAD) - strlen((string) $supply)),
                'remark'     => (string) $order->order_number,
                'total'      => number_format($total),
                // 영수면 현금 칸에, 청구면 외상미수금 칸에 선다 — 서식이 그렇게 읽힌다
                'cash'       => $received ? number_format($total) : '',
                'credit'     => $received ? '' : number_format($total),
                'purpose'    => $received ? '영수' : '청구',
                'issuer'     => '팝빌(www.popbill.com)',
            ],

            'parties'     => self::parties($order, $company),
            'supplyHead'  => self::SUPPLY_HEAD,
            'vatHead'     => self::VAT_HEAD,
            'supplyDigits' => self::digits($supply, count(self::SUPPLY_HEAD)),
            'vatDigits'    => self::digits($vat, count(self::VAT_HEAD)),

            'items' => [[
                'month'  => (string) (int) $at->format('m'),
                'day'    => (string) (int) $at->format('d'),
                'name'   => (string) ($order->product_name ?: '처방약'),
                'spec'   => '',
                'qty'    => '1',
                'price'  => number_format($supply),
                'supply' => number_format($supply),
                'vat'    => number_format($vat),
                'note'   => (string) $order->order_number,
            ]],
            'rows' => self::ROWS,
        ];
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 네 줄짜리 당사자 칸.
     *
     * 공급받는자가 개인이면 등록번호 자리에 주민등록번호가 선다. 그 값은 이미 가려진
     * 것이다(발행 때 마스킹해 저장한다) — 원문은 처방전의 전용 암호화 칸에만 있다.
     */
    private static function parties(Order $order, array $company): array
    {
        $buyerName = (string) ($order->tax_invoice_biz_name ?? '');

        return [
            [
                'label' => '등록번호', 'label2' => '종사업장', 'narrow' => false, 'wide' => false,
                'supplier'  => self::bizNo((string) config('popbill.test.corp_num')),
                'supplier2' => '',
                'buyer'     => (string) ($order->tax_invoice_biz_no ?? ''),
                'buyer2'    => '',
            ],
            [
                'label' => '상호', 'label2' => '성<br>명', 'narrow' => true, 'wide' => false,
                'supplier'  => (string) ($company['corp_name'] ?? ''),
                'supplier2' => (string) ($company['ceo_name'] ?? ''),
                'buyer'     => $buyerName,
                'buyer2'    => (string) ($order->tax_invoice_ceo_name ?? ''),
            ],
            [
                'label' => '사업장 주소', 'label2' => '', 'narrow' => false, 'wide' => true,
                'supplier'  => (string) ($company['addr'] ?? ''),
                'supplier2' => '',
                // 신고하지 않은 칸이다 — 종이에도 비워 둔다
                'buyer'     => '',
                'buyer2'    => '',
            ],
            [
                'label' => '업태', 'label2' => '종<br>목', 'narrow' => true, 'wide' => false,
                'supplier'  => (string) ($company['biz_type'] ?? ''),
                'supplier2' => (string) ($company['biz_class'] ?? ''),
                'buyer'     => '',
                'buyer2'    => '',
            ],
        ];
    }

    /**
     * 금액을 자릿수 칸에 흩는다. 앞의 빈 자리는 빈 칸으로 둔다.
     *
     * @return array<int, string>
     */
    private static function digits(int $amount, int $cells): array
    {
        $s = $amount > 0 ? (string) $amount : '';
        $s = str_pad($s, $cells, ' ', STR_PAD_LEFT);

        $out = [];
        for ($i = 0; $i < $cells; $i++) {
            $out[] = $s[$i] === ' ' ? '' : $s[$i];
        }

        return $out;
    }

    private static function bizNo(string $raw): string
    {
        $n = preg_replace('/\D/', '', $raw);

        return strlen($n) === 10
            ? substr($n, 0, 3) . '-' . substr($n, 3, 2) . '-' . substr($n, 5)
            : $n;
    }
}
