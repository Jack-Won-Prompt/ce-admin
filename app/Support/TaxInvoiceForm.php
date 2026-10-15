<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    /** 붙는 종이의 갈래와 이름 (2026-09-16) */
    private const DOC_TYPE = 'tax_invoice_form';
    private const LABEL    = '세금계산서';
    private const PREFIX   = 'tif_';

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
    /**
     * 「이 금액을 [청구] 함」 — 새로 발행하는 계산서가 신고하는 값 (2026-09-14 지시).
     *
     * 이 계산서가 다루는 돈은 공단ㆍ지자체에서 받을 기관부담금이고, 발행 시점은 그
     * 돈을 받기 전이다. 「영수」는 이미 받았다는 뜻이라 맞지 않는다.
     *
     * 여태 영수로 나간 것은 주문의 입금확인을 보고 정했기 때문인데, 그 입금은 환자가
     * 내는 본인부담금이다 — 세금계산서와는 다른 돈이다.
     */
    public const PURPOSE = '청구';

    /**
     * 이 칸이 비어 있는 옛 건에 적는 값.
     *
     * 2026-09-14 전에 발행한 건은 팝빌로 「영수」가 나갔다. 종이를 지금 잣대로 다시
     * 그리면 국세청에 신고된 것과 어긋난다 — 그 건들은 신고된 대로 그린다.
     */
    private const PURPOSE_LEGACY = '영수';

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

        /* 「이 금액을 [영수/청구] 함」 — 신고할 때 정한 값을 그대로 읽는다.
           되짚어 그리지 않는다. 여태는 주문의 입금확인을 보고 정했는데, 그 입금은
           환자가 내는 본인부담금이고 이 계산서가 다루는 돈은 공단ㆍ지자체에서 받을
           기관부담금이다 — 서로 다른 돈이라 맞을 리가 없었다(2026-09-14 지시). */
        $purpose  = $order->tax_invoice_purpose ?: self::PURPOSE_LEGACY;
        $received = $purpose === '영수';

        return [
            'doc' => [
                'ntsNo'      => (string) ($order->tax_invoice_no ?? ''),
                'year'       => $at->format('Y'),
                'month'      => (string) (int) $at->format('m'),
                'day'        => (string) (int) $at->format('d'),
                'blankCount' => (string) max(0, count(self::SUPPLY_HEAD) - strlen((string) $supply)),
                'remark'     => (string) $order->order_number,
                'total'      => number_format($total),
                // 영수면 현금 칸에 선다
                'cash'       => $received ? number_format($total) : '',
                /* 외상미수금은 비워 둔다 (2026-09-14 지시). 청구 건의 금액을 이 칸에
                   적으면 받을 돈이 장부에 잡힌 것으로 읽힌다 — 공단 심사가 끝나기
                   전이라 얼마를 받을지 아직 정해지지 않았다. */
                'credit'     => '',
                'purpose'    => $purpose,
                'issuer'     => '팝빌(www.popbill.com)',
            ],

            'parties'     => self::parties($order, $company),
            'supplyHead'  => self::SUPPLY_HEAD,
            'vatHead'     => self::VAT_HEAD,
            'supplyDigits' => self::digits($supply, count(self::SUPPLY_HEAD)),
            'vatDigits'    => self::digits($vat, count(self::VAT_HEAD)),

            'items' => self::items($order, $supply, $vat, $at),
            'rows' => self::ROWS,
        ];
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 품목 줄 — 국세청에 신고한 줄을 그대로 옮겨 그린다.
     *
     * 예전에는 이 자리에서 「대표 제품 하나 · 규격 빈칸 · 수량 1」을 지어냈다.
     * 그런데 팝빌에 나가는 줄은 IssueLines 가 품목마다 만들고 규격 칸에 장비코드를
     * 적는다. 그래서 신고 내용과 우리가 그려 붙이는 종이가 서로 달랐다 —
     * 공단 청구 묶음에 이 종이가 들어가므로, 대조하는 쪽에서는 다른 문서로 보인다.
     *
     * 서식은 네 줄짜리 종이다. 품목이 그보다 많으면 세 줄을 적고 나머지는
     * 「외 N건」 한 줄로 합친다 — 합계는 어긋나지 않는다.
     */
    private static function items(Order $order, int $supply, int $vat, $at): array
    {
        $rows = \App\Support\IssueLines::split($order, $supply, $vat);

        if (count($rows) > self::ROWS) {
            $head = array_slice($rows, 0, self::ROWS - 1);
            $tail = array_slice($rows, self::ROWS - 1);

            $head[] = [
                'name'   => '외 ' . count($tail) . '건',
                'code'   => '',
                'device' => '',
                // 수량은 종이에도 신고에도 쓰지 않는다 — 합계만 남겨 둔다
                'qty'    => array_sum(array_column($tail, 'qty')),
                'unit'   => 0,
                'supply' => array_sum(array_column($tail, 'supply')),
                'vat'    => array_sum(array_column($tail, 'vat')),
            ];

            $rows = $head;
        }

        /* 규격에는 품목코드, 비고에는 장비코드가 선다 (2026-09-28 지시).

           받아 본 세금계산서 원본이 그 꼴이다 — 규격 5368 · 비고 NBC0121000006.
           한동안 규격에 장비코드를, 비고에 주문번호를 적었다. 주문번호는 위쪽
           「비고1」 칸에 이미 한 번 서므로 품목 줄에서는 자리를 비켜 준다. */
        return array_map(fn (array $r) => [
            'month'  => (string) (int) $at->format('m'),
            'day'    => (string) (int) $at->format('d'),
            'name'   => $r['name'],
            'spec'   => $r['code'] ?? '',
            /* 수량ㆍ단가는 비운다 (2026-09-28 지시) — 원본이 그 두 칸을 비워 두고
               공급가액ㆍ세액만 적는다. 신고 쪽도 같이 비운다(IssueLines::taxDetails). */
            'qty'    => '',
            'price'  => '',
            'supply' => number_format($r['supply']),
            'vat'    => number_format($r['vat']),
            'note'   => $r['device'],
        ], $rows);
    }

    /**
     * 네 줄짜리 당사자 칸 — 공급자와 공급받는자.
     *
     * 공급받는자가 개인이면 등록번호 자리에 주민등록번호가 선다. 그 값은 이미 가려진
     * 것이다(발행 때 마스킹해 저장한다) — 원문은 처방전의 전용 암호화 칸에만 있다.
     *
     * **공급받는자 「사업장 주소」도 채운다** (2026-09-28 지시). 여태 비워 두었는데,
     * 받는 쪽이 아는 종이는 두 칸이 다 찬 그 서식이다. 값은 신고할 때 적어 둔
     * tax_invoice_addr 을 읽는다 — 환자 자료에서 그때그때 읽으면 환자가 이사한 뒤
     * 예전 계산서를 다시 뽑았을 때 그 시절에 없던 주소가 찍힌다.
     *
     * 그 칸이 없던 시절에 발행한 건은 환자의 지금 주소로 물러난다. 비워 두는 것보다
     * 낫고, 개인 건의 주소는 대개 바뀌지 않는다.
     */
    /**
     * 공급받는자 등록번호 — 개인 건은 **주민등록번호를 가리지 않고** 적는다
     * (2026-10-15 지시).
     *
     * `tax_invoice_biz_no` 는 평문 칸이라 발행할 때 일부러 가려서 담는다(P0-1) —
     * 원문은 처방전ㆍ거래처의 암호화 칸에만 있다. 그 때문에 종이에도 가려진 채로
     * 찍혔는데, **공단에 내는 서류는 번호가 보이지 않으면 반려된다.**
     *
     * 담는 값은 그대로 둔다. 종이를 그릴 때만 원문을 연다 — 여는 자리마다 감사로그가
     * 남으므로 누가 언제 보았는지 되짚을 수 있다. 사유 코드는 `tax_invoice` 다
     * (`config/rrn.php` · 「전자세금계산서 공급받는자(개인) 주민등록번호 기재」).
     * 등록되지 않은 코드를 쓰면 복호화가 거절된다.
     *
     * 원문을 찾지 못하면 **담긴 값을 그대로 쓴다.** 가려진 번호라도 비어 있는 것보다
     * 낫고, 지어낼 수는 없다.
     */
    private static function 등록번호(Order $order, bool $개인인가): string
    {
        $담긴것 = (string) ($order->tax_invoice_biz_no ?? '');

        if (! $개인인가 || ! str_contains($담긴것, '*')) {
            return $담긴것;
        }

        $원문 = preg_replace('/\D/', '',
            (string) $order->prescription?->residentNoOcrFor('tax_invoice')) ?? '';

        if (strlen($원문) !== 13) {
            $원문 = preg_replace('/\D/', '',
                (string) $order->patient?->residentNoFor('tax_invoice')) ?? '';
        }

        return strlen($원문) === 13
            ? substr($원문, 0, 6) . '-' . substr($원문, 6)
            : $담긴것;
    }

    /**
     * 사업장 주소 칸에서 **주소가 아닌 것을 걷는다** (2026-10-15 지시).
     *
     * 이 칸은 환자의 주소를 그대로 옮겨 적는데, 상세주소 칸에 받는 사람과 전화번호를
     * 함께 적어 둔 건이 많다. 그대로 찍히면 세금계산서에 전화번호가 박힌다.
     *
     *     (21065) 인천 … 410호 이건우님(010-4238-8077) 010-4238-8077
     *     (54898) 전북 … 1215호((E)박소영 010-2088-2573) (E)박소영 010-2088-2573
     *
     * 걷는 차례가 중요하다 —
     *
     *   1. `(E)` 와 그 뒤에 붙은 이름을 뗀다. 우리가 붙인 표시이지 주소가 아니다
     *   2. **전화번호가 처음 나오는 자리에서 뒤를 통째로 자른다.** 주소는 늘 그 앞이다
     *   3. 자르고 나면 괄호가 열린 채 끝날 수 있다 — 짝 없는 여는 괄호부터 끝까지 자른다
     *   4. 「○○○님」처럼 받는 사람을 적어 둔 꼬리를 뗀다
     *
     * 우편번호 `(21065)` 와 법정동 `(공릉동)` 은 괄호 안에 전화번호가 없으므로 남는다.
     *
     * **홀로 선 이름은 떼지 않는다.** 「103호 김슬기」의 김슬기를 지우려면 사람 이름인지
     * 건물 이름인지 갈라야 하는데, 「라온채」 같은 세 글자 건물명과 구별할 길이 없다.
     * 주소를 깎는 쪽이 더 나쁘다.
     */
    private static function 주소만(string $주소): string
    {
        $값 = trim($주소);

        if ($값 === '') {
            return '';
        }

        /* 1. (E) 와 뒤에 붙은 이름 */
        $값 = preg_replace('/\(E\)\s*[가-힣]{2,5}/u', '', $값);
        $값 = str_replace('(E)', '', $값);

        /* 2. 전화번호가 처음 나오는 자리부터 끝까지 */
        $전화 = '/(01[016789]|0\d{1,2})[-\s]?\d{3,4}[-\s]?\d{4}/';

        if (preg_match($전화, $값, $m, PREG_OFFSET_CAPTURE)) {
            $값 = substr($값, 0, $m[0][1]);
        }

        /* 3. 짝 없는 여는 괄호부터 끝까지 */
        $깊이 = 0;
        $연자리 = null;

        for ($i = 0, $끝 = strlen($값); $i < $끝; $i++) {
            if ($값[$i] === '(') {
                if ($깊이 === 0) { $연자리 = $i; }
                $깊이++;
            } elseif ($값[$i] === ')') {
                $깊이 = max(0, $깊이 - 1);
                if ($깊이 === 0) { $연자리 = null; }
            }
        }

        if ($연자리 !== null) {
            $값 = substr($값, 0, $연자리);
        }

        /* 4. 받는 사람을 적어 둔 꼬리 — 「○○○님」ㆍ「○○○님앞」 */
        $값 = preg_replace('/\s*[가-힣]{2,4}님(앞)?\s*$/u', '', $값);

        /* 5. 끝에 홀로 선 **사람 이름** (2026-10-15 지시) */
        $값 = self::끝의이름떼기($값);

        /* 남은 자국 — 빈 괄호와 겹친 공백, 끝에 걸린 구두점 */
        $값 = preg_replace('/\(\s*\)/', '', $값);
        $값 = preg_replace('/\s+/u', ' ', $값);

        /* 앞뒤에 걸린 공백ㆍ쉼표ㆍ중점ㆍ빗금을 걷는다 */
        return trim(preg_replace('/^[\s,\x{00B7}\/]+|[\s,\x{00B7}\/]+$/u', '', $값));
    }

    /**
     * 주소 끝에 홀로 선 **사람 이름**을 뗀다 (2026-10-15 지시).
     *
     * 상세주소 끝에 받는 사람을 적어 둔 건이 있다 — 「102동 303호 김슬기」.
     * 전화번호와 달리 생김새만으로는 가릴 수 없다. 운영 342건의 끝 토막을 모두
     * 눈으로 보니 스물여섯이 순 한글이었는데, 그 가운데 **사람 이름은 여덟뿐**이었다.
     *
     *     사람 이름   김슬기 · 김천수 · 이승민 · 남석현 · 손주훈 …
     *     주소다      지하 · 지층 · 좌측 · 중문앞 · 초록문 · 단독주택 · 청구빌라
     *                 빨간대문 · 디딤자리 · 또래분식 · 대흥칼국수 · 우리세차장
     *                 승표기름집 · 복뎅이갈비
     *
     * 「한글 두세 자면 이름」이라는 잣대는 **지하ㆍ지층ㆍ좌측ㆍ초록문을 함께 지운다.**
     * 성씨로 가르는 잣대도 마찬가지다 — 초ㆍ우ㆍ승ㆍ복 모두 실제 성씨다.
     *
     * 그래서 **우리가 아는 이름과 같을 때만 뗀다.** 거래처ㆍ환자 명단(운영 15,383개)에
     * 그 이름이 있으면 사람으로 보고, 없으면 주소로 두고 건드리지 않는다. 위 열넷은
     * 하나도 명단에 없고, 여덟은 모두 있다.
     *
     * 명단은 열 분 동안 담아 둔다 — 공단 팩스 합본처럼 여러 장을 잇달아 그릴 때
     * 장마다 표를 훑지 않게 한다.
     */
    private static function 끝의이름떼기(string $주소): string
    {
        $토막들 = preg_split('/\s+/u', trim($주소));
        $끝     = (string) (end($토막들) ?: '');

        /* 이름일 만한 것만 묻는다 — 숫자가 섞였으면 주소다(「1102호」).
           토막이 하나뿐이면 그것이 주소 전부이므로 떼지 않는다. */
        if ($끝 === '' || count($토막들) < 2
            || ! preg_match('/^[가-힣]{2,4}$/u', $끝)) {
            return $주소;
        }

        if (! isset(self::아는이름들()[$끝])) {
            return $주소;
        }

        array_pop($토막들);

        return implode(' ', $토막들);
    }

    /** 거래처ㆍ환자 이름 — 꼬리와 (E) 를 뗀 실명으로 모은다 */
    private static function 아는이름들(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'tax_invoice.known_names', now()->addMinutes(10),
            function (): array {
                $모음 = [];

                $담기 = function ($값) use (&$모음) {
                    $실 = \App\Models\Patient::실명((string) $값);
                    if ($실 !== '') { $모음[$실] = true; }
                };

                \App\Models\Patient::select('name')->orderBy('id')
                    ->chunk(2000, function ($덩어리) use ($담기) {
                        foreach ($덩어리 as $p) { $담기($p->name); }
                    });

                if (\Illuminate\Support\Facades\Schema::hasTable('ww_customers')) {
                    \Illuminate\Support\Facades\DB::table('ww_customers')
                        ->select('account_name')->orderBy('id')
                        ->chunk(2000, function ($덩어리) use ($담기) {
                            foreach ($덩어리 as $c) { $담기($c->account_name); }
                        });
                }

                return $모음;
            }
        );
    }

    private static function parties(Order $order, array $company): array
    {
        /* 상호ㆍ성명에서 **동명이인 꼬리를 뗀다** (2026-10-15 지시).

           「이영주A」의 A 는 같은 이름을 가르려고 우리가 붙인 글자다. 그 사람의 이름은
           「이영주」다. 공단에 내는 종이에 꼬리가 찍히면 이름이 맞지 않는다고 반려된다.

           **신고한 값(`tax_invoice_biz_name`)은 그대로 둔다** — 국세청에 간 것은 그것이고,
           되짚어 고치면 기록과 어긋난다. 종이에 그릴 때만 뗀다. */
        $buyerName = \App\Models\Patient::실명((string) ($order->tax_invoice_biz_name ?? ''));

        /* 개인 건만 주소를 세운다 — 사업자 건은 받아 둔 주소가 없다.
           개인인지는 등록번호로 가른다(사업자등록번호는 열 자리다). */
        $번호   = preg_replace('/\D/', '', (string) ($order->tax_invoice_biz_no ?? '')) ?? '';
        $개인인가 = strlen($번호) !== 10;

        $buyerAddr = (string) ($order->tax_invoice_addr ?? '');

        if ($buyerAddr === '' && $개인인가) {
            $buyerAddr = trim((string) $order->patient?->full_address);
        }

        /* 주소 칸에는 **주소만** 남긴다 (2026-10-15 지시) */
        $buyerAddr = self::주소만($buyerAddr);

        return [
            [
                'label' => '등록번호', 'label2' => '종사업장', 'narrow' => false, 'wide' => false,
                'supplier'  => self::bizNo((string) config('popbill.test.corp_num')),
                'supplier2' => '',
                'buyer'     => self::등록번호($order, $개인인가),
                'buyer2'    => '',
            ],
            [
                'label' => '상호', 'label2' => '성<br>명', 'narrow' => true, 'wide' => false,
                'supplier'  => (string) ($company['corp_name'] ?? ''),
                'supplier2' => (string) ($company['ceo_name'] ?? ''),
                'buyer'     => $buyerName,
                'buyer2'    => \App\Models\Patient::실명((string) ($order->tax_invoice_ceo_name ?? '')),
            ],
            [
                'label' => '사업장 주소', 'label2' => '', 'narrow' => false, 'wide' => true,
                'supplier'  => (string) ($company['addr'] ?? ''),
                'supplier2' => '',
                'buyer'     => $buyerAddr,
                'buyer2'    => '',
            ],
            [
                /* 공급받는자 업태ㆍ종목은 **개인 건에서 비운다.**

                   개인은 사업자가 아니라 적을 업태도 종목도 없다. 칸을 채우려고
                   무엇이든 적으면 국세청에 신고한 적 없는 말이 세금계산서에 찍힌다 —
                   빈 칸은 「해당 없음」으로 읽히지만 지어낸 말은 되돌릴 수 없다.
                   사업자 건이면 받아 둔 값이 없어 역시 비운다. */
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
