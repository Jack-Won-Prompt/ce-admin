<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    /** 붙는 종이의 갈래와 이름 (2026-09-16) */
    private const DOC_TYPE = 'cash_receipt_form';
    private const LABEL    = '현금영수증';
    private const PREFIX   = 'crf_';

    /**
     * 양식을 그려 이 건의 첨부문서로 붙인다 (2026-09-16 지시).
     *
     * 팝빌로 실제 신고가 나가지 않는 자리 — 시험 환경, 발행 시뮬레이션, 인증서
     * 미등록 — 에서도 종이는 있어야 한다. 실제로 발행된 건은 발행 경로가 팝빌이 준
     * PDF 를 이미 붙이므로, 이 길은 **발행되지 않은 건**에만 쓴다.
     *
     * 이미 같은 갈래의 종이가 붙어 있으면 다시 만들지 않는다. 금액이 바뀌어 다시
     * 그려야 하면 먼저 지운 뒤 부른다.
     *
     * @param array $덧댈값 서식이 읽는 칸 가운데 주문에 아직 없는 것
     *                     (공급가액ㆍ세액 따위). 주문을 고치지 않고 그리기만 한다.
     */
    public static function attach(Order $order, array $덧댈값 = []): ?PrescriptionAttachment
    {
        if (! $order->prescription_id) {
            Log::info('[' . self::LABEL . '] 처방이 없는 주문 — 붙이지 않는다',
                ['order' => $order->order_number]);

            return null;
        }

        $이미 = PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->where('doc_type', self::DOC_TYPE)
            ->first();

        if ($이미) {
            return $이미;
        }

        try {
            /* 주문을 고치지 않고 그리기만 한다 — 덧댈 값을 얹은 복제본으로 그린다.
               실제 발행이 아니므로 승인번호ㆍ발행시각을 주문에 적어서는 안 된다. */
            $그릴것 = $덧댈값 ? (clone $order)->forceFill($덧댈값) : $order;

            $pdf  = self::render($그릴것);
            $이름 = self::LABEL . '_' . \App\Models\Patient::실명($order->patient?->name)
                  . '_' . $order->order_number . '.pdf';
            $길   = 'attachments/' . $order->prescription_id . '/' . uniqid(self::PREFIX) . '.pdf';

            Storage::disk('public')->put($길, $pdf);

            return PrescriptionAttachment::한장만만들기([
                'prescription_id'    => $order->prescription_id,
                'file_path'          => $길,
                'file_original_name' => $이름,
                'file_mime_type'     => 'application/pdf',
                'file_size'          => strlen($pdf),
                'doc_type'           => self::DOC_TYPE,
                'doc_label'          => self::LABEL,
                'display_order'      => 99,
            ], $길);
        } catch (\Throwable $e) {
            Log::warning('[' . self::LABEL . '] 양식을 만들지 못했다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
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

    /**
     * 그 증빙에 대한 **팝빌 기록 한 줄** — 거래일시를 저쪽 값으로 적기 위함이다
     * (2026-10-16 지시).
     *
     * 여태 거래일시에 `cash_receipt_issued_at`(우리가 발행을 요청한 시각)을 적었다.
     * 국세청에 신고된 거래일시는 팝빌이 적은 값이고, 둘이 다른 건이 있다 —
     * (E)김정수C 님 건은 우리 10-13 12:20:49, 팝빌 10-12 16:12:51 이었다.
     *
     * 증빙에 찍히는 값은 **신고된 값**이어야 한다. 승인번호로 그 줄을 찾는다.
     */
    private static function 팝빌줄(Order $order): ?object
    {
        $번호 = trim((string) ($order->cash_receipt_no ?? ''));

        if ($번호 === '' || ! \Illuminate\Support\Facades\Schema::hasTable('cashbill_records')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('cashbill_records')
            ->where('order_id', $order->id)
            ->where('confirm_num', $번호)
            ->orderByDesc('id')
            ->first(['trade_dt', 'trade_type', 'confirm_num']);
    }

    /** `YmdHis` 를 사람이 읽는 꼴로 — 못 읽으면 null */
    private static function 시각(?string $값): ?string
    {
        $값 = preg_replace('/\D/', '', (string) $값);

        if (strlen((string) $값) < 8) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat(
                strlen($값) >= 14 ? 'YmdHis' : 'Ymd',
                substr($값, 0, strlen($값) >= 14 ? 14 : 8)
            )->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** 서식이 쓰는 값 한 벌. */
    public static function data(Order $order): array
    {
        $order->loadMissing('patient');

        $amount = (int) $order->cash_receipt_amount;
        $supply = (int) round($amount / 1.1);

        $company = config('popbill.company');

        /* 거래일시는 **팝빌이 적은 값**을 쓴다. 저쪽 기록이 없으면(아직 동기화되지
           않았거나 옛 건) 우리 발행 시각으로 갈음한다 — 비워 두는 것보다 낫다. */
        $팝빌 = self::팝빌줄($order);
        $거래일시 = self::시각($팝빌->trade_dt ?? null)
                 ?? $order->cash_receipt_issued_at?->format('Y-m-d H:i:s')
                 ?? '-';

        return [
            'doc' => [
                'identifier'  => self::maskIdentifier((string) $order->cash_receipt_identifier),
                /* 취소된 건은 취소거래로 선다 — 승인된 것처럼 보이면 안 된다.

                   **상태를 먼저 본다** (2026-10-16 확인요청). 취소 시각 하나로 가리면,
                   취소하고 다시 발행한 건이 「취소거래」로 그려진다. 지금 살아 있는
                   증빙이 무엇인지는 상태 칸이 말한다. */
                'docKind'     => ((string) $order->cash_receipt_status === 'cancelled'
                                  || (! $order->cash_receipt_no && $order->cash_receipt_cancelled_at))
                                 ? '취소거래' : '승인거래',
                'purpose'     => $order->cash_receipt_type === 'income_deduction' ? '소득공제용' : '지출증빙용',
                'dealType'    => '일반',
                'issuedAt'    => $거래일시,
                // 국세청 전송일은 우리가 받아 두지 않는다 — 지어내지 않고 비운다
                'sentAt'      => '-',
                'approvalNo'  => (string) ($order->cash_receipt_no ?? ''),

                /* 환자에게 주는 증빙이다 — 「(E)」를 뗀다 (2026-09-27 지시) */
                /* 영수증에 찍히는 이름 — (E) 와 동명이인 꼬리를 뗀다 (SR #90) */
                'buyer'       => \App\Models\Patient::실명($order->patient?->name),
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
