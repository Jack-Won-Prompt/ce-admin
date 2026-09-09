<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use App\Support\DeviceCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * 거래명세서 — 받은 서식대로 만들어 주문의 첨부문서로 넣는다.
 *
 * 서식은 **위드웍스(medical)의 「출고 거래명세서」와 같은 틀**이다(2026-09-09 지시)
 *   withworks/resources/views/main/medical/standard/print/account_salesorder_ship.blade.php
 *
 * 같은 서류가 두 시스템에서 서로 다르게 생기면 받는 쪽은 어느 것이 진짜인지 묻게
 * 된다. 칸 이름ㆍ차례ㆍ폭ㆍ글자 크기ㆍ테두리를 원본 값 그대로 옮겼다
 * (resources/views/documents/transaction_statement.blade.php).
 *
 * LOTㆍ유효기간ㆍ등급ㆍUDI 는 창고가 아는 값이다. 우리 주문 줄에는 없어 비워 둔다 —
 * 지어내지 않는다. 위드웍스에서 받아 올 길이 생기면 그 자리만 채우면 된다.
 */
final class TransactionStatement
{
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
        /* 원본 서식이 가로다(@page { size: landscape }). 품목 줄에 칸이 열다섯이라
           세로로 세우면 글자가 칸마다 한 자씩 쌓인다. */
        $dompdf->setPaper('A4', 'landscape');
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

    /**
     * 서식이 쓰는 값 한 벌.
     *
     * 칸 이름은 위드웍스(medical) 「출고 거래명세서」를 그대로 따른다 — 공급자ㆍ
     * 공급받는자 각각 등록번호ㆍ상호(법인명)ㆍ성명ㆍ사업장 소재지ㆍ업태ㆍ종목ㆍTELㆍFAX,
     * 품목 줄은 NOㆍ주문일자ㆍ제품코드ㆍ품목명ㆍLOTㆍ유효기간ㆍ수량ㆍ단가ㆍ금액(VAT
     * 불포함)ㆍ금액(VAT 포함)ㆍ등급ㆍ보험코드ㆍUDI 코드ㆍUDI 수량ㆍUDI 단가다.
     *
     * **우리에게 없는 값은 빈칸으로 둔다.** LOTㆍ유효기간ㆍ등급ㆍUDI 는 창고가 아는
     * 값이라 우리 주문 줄에 없다. 지어내면 종이에 그대로 찍혀 나간다.
     *
     * 공급받는자는 사업자가 아니라 사람이다. 그래서 등록번호 자리에 **가린**
     * 주민등록번호를, 상호와 성명 자리에 이름을 적는다 — 서식의 칸을 지우지 않고
     * 그 자리에 맞는 것을 넣는다. 업태ㆍ종목ㆍFAX 는 사람에게 없어 비운다.
     */
    public static function data(Order $order): array
    {
        $rx      = $order->prescription;
        $patient = $order->patient;

        /* 품목 — 주문 줄이 정본이다. 줄이 없으면 처방 줄로 대신한다
           (품목 표가 생기기 전에 만들어진 주문). */
        $lines = $order->items->isNotEmpty() ? $order->items : ($rx?->items ?? collect());

        $items = $lines->map(function ($i) {
            $qty    = (int) ($i->quantity ?? 0);
            $price  = (int) ($i->insurance_price ?: $i->product_price ?: 0);
            $amount = $qty * $price;

            return [
                'code'          => (string) ($i->product_code ?? ''),
                'name'          => (string) ($i->product_name ?? ''),
                // 창고가 아는 값 — 우리 줄에는 없다. 지어내지 않는다.
                'lot'           => '',
                'expiry'        => '',
                'qty'           => $qty,
                'price'         => $price,
                // 단가에 부가세가 들어 있다(vatIncluded)
                'supply'        => (int) round($amount / 1.1),
                'amount'        => $amount,
                'grade'         => '',
                // 공단에 청구할 때 쓰는 번호 — 품번으로 찾는다
                'insuranceCode' => DeviceCode::for($i->product_code) ?? '',
                'udiCode'       => '',
                'udiQty'        => '',
                'udiPrice'      => '',
            ];
        })->values()->all();

        $totalQty = array_sum(array_column($items, 'qty'));
        $amount   = array_sum(array_column($items, 'amount'));
        $supply   = (int) round($amount / 1.1);

        $company = config('popbill.company');

        return [
            'doc' => [
                'documentNo' => $order->withworks_ship_no ?: ($order->withworks_so_no ?: $order->order_number),
                'saleNo'     => $order->withworks_so_no ?: '',
                'issueDate'  => self::issueDate($order),
            ],
            'supplier' => [
                'bizNo'    => self::bizNo(),
                'corpName' => $company['corp_name'] ?? '',
                'ceoName'  => $company['ceo_name']  ?? '',
                'address'  => $company['addr']      ?? '',
                'bizType'  => $company['biz_type']  ?? '',
                'bizClass' => $company['biz_class'] ?? '',
                'tel'      => $company['tel']       ?? '',
                'fax'      => $company['fax']       ?? '',
            ],
            'buyer' => [
                // 사람이라 사업자등록번호가 없다 — **가린** 주민등록번호를 적는다(P0-1)
                'bizNo'    => self::maskedRrn($rx, $patient),
                'corpName' => $patient?->name ?? ($rx->patient_name_ocr ?? ''),
                'ceoName'  => $patient?->name ?? ($rx->patient_name_ocr ?? ''),
                'address'  => self::address($order, $rx),
                // 사람에게는 없는 칸이다 — 서식의 칸은 두고 값만 비운다
                'bizType'  => '',
                'bizClass' => '',
                'tel'      => $patient?->mobile ?? ($rx->mobile_ocr ?? ''),
                'fax'      => '',
            ],
            'items'  => $items,
            'totals' => [
                'qty'    => $totalQty,
                'amount' => $amount,
                'supply' => $supply,
                'vat'    => $amount - $supply,
            ],
        ];
    }

    /**
     * 가린 주민등록번호 — **원문은 열지 않는다**(P0-1).
     *
     * 처방전에 적힌 것이 먼저다. 처방전 없이 선 건이거나 그 칸이 비어 있으면
     * 거래처에 적어 둔 것을 쓴다 — 여태 처방전만 보아, 사람에게는 있는데
     * 명세서의 등록번호 칸이 비어 나갔다.
     */
    private static function maskedRrn($rx, $patient = null): string
    {
        return (string) ($rx?->masked_resident_no_ocr
            ?: $rx?->resident_no_ocr_masked
            ?: $patient?->masked_resident_no
            ?: '');
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

}
