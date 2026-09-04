<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 청구 자료를 한 묶음으로 엮는다 (요청서 10쪽, 2026-08-31 회신).
 *
 * 「지자체는 프린트 모두 필요하고, 건보는 간혹 프린트 필요」. 지자체는 서류를 등기로
 * 보내므로 다섯을 한 번에 뽑아야 하는데, 지금까지는 화면에서 하나씩 눌러 내려받고
 * 각각 인쇄해야 했다 — 다섯 번 누르는 동안 한둘을 빠뜨린다.
 *
 * 담는 것 (2026-08-31 회신):
 *   처방전 이미지 · 개인정보 동의서 · 거래명세서 · 전자세금계산서 · 의료용품구입확인서
 *
 * 위임장은 담지 않는다. 지자체는 위임 절차가 없다(2026-08-31 회신) — 공단 건에만 든다.
 *
 * PDF 와 그림이 섞여 있다. PDF 는 쪽을 그대로 옮겨 붙이고(FPDI), 그림은 새 쪽에 앉힌다.
 * 없는 서류는 건너뛰되 무엇이 빠졌는지는 첫 쪽에 적는다 — 빠진 채로 등기를 부치면
 * 반려되어 돌아오고, 그때는 이미 우편 값이 나간 뒤다.
 */
class ClaimBundle
{
    /** A4 세로 — 등기로 부치는 종이라 규격을 흔들지 않는다 */
    private const W = 210.0;
    private const H = 297.0;
    private const MARGIN = 10.0;

    /**
     * @return array{pdf: string, missing: list<string>}
     */
    public function build(Order $order): array
    {
        $order->loadMissing(['patient', 'prescription']);

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetCreator('CE Admin');
        $pdf->SetTitle('청구 자료 — ' . $order->order_number);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $font = $this->font();
        $missing = [];

        // 첫 쪽 — 무엇이 들었고 무엇이 빠졌는지. 부치기 전에 여기만 보면 된다.
        $parts = $this->parts($order);

        foreach ($parts as $p) {
            if (!$p['path']) {
                $missing[] = $p['name'];
            }
        }

        $this->cover($pdf, $font, $order, $parts);

        foreach ($parts as $p) {
            if (!$p['path']) {
                continue;
            }

            try {
                str_ends_with(strtolower($p['path']), '.pdf') || ($p['mime'] ?? '') === 'application/pdf'
                    ? $this->addPdf($pdf, $p['path'])
                    : $this->addImage($pdf, $p['path'], $font, $p['name']);
            } catch (\Throwable $e) {
                /* 한 장이 깨졌다고 묶음 전체를 못 만들면 담당자는 아무것도 못 뽑는다.
                   그 장만 빼고 빠진 것으로 적는다. */
                Log::warning('[청구 묶음] 한 장을 넣지 못했다', [
                    'order' => $order->order_number, 'doc' => $p['name'], 'error' => $e->getMessage(),
                ]);
                $missing[] = $p['name'] . ' (파일을 읽지 못했습니다)';
            }
        }

        return ['pdf' => $pdf->Output('', 'S'), 'missing' => $missing];
    }

    /**
     * 담을 다섯.
     *
     * 거래명세서와 처방전은 첨부문서(prescription_attachments)에, 동의서와 세금계산서는
     * 발행 서류(prescription_documents)에 있다. 표가 둘로 갈린 것은 만들어진 길이
     * 다르기 때문이다 — 하나는 올린 것이고 하나는 우리가 낸 것이다.
     *
     * @return list<array{name: string, path: ?string, mime: ?string}>
     */
    private function parts(Order $order): array
    {
        $rxId = $order->prescription_id;

        $att = fn (string $type) => $rxId
            ? PrescriptionAttachment::where('prescription_id', $rxId)
                ->where('doc_type', $type)->latest('id')->first()
            : null;

        $doc = fn (string $type) => $rxId
            ? PrescriptionDocument::where('prescription_id', $rxId)
                ->where('type', $type)->latest('id')->first()
            : null;

        $rxImage   = $att('prescription');
        $statement = $att('trade_statement');
        $cardSlip  = $att('card_sales') ?: \App\Support\CardSalesSlip::attach($order);
        $consent   = $doc('consent');
        $tax       = $doc('tax_invoice');

        /* 기초(의료급여) 대상자는 요양비 지급청구서를 함께 낸다(2026-09-01 회신).
           위임장이 「우리 계좌로 받겠다」는 것이라면 이것은 「얼마를 청구한다」는
           서류라, 하나가 다른 하나를 대신하지 못한다. 아직 만들어 두지 않았으면
           여기서 만든다 — 묶음을 뽑는 그때가 내는 때다. */
        $aidClaim = \App\Support\MedicalAidClaimForm::applies($order)
            ? ($att('medical_aid_claim') ?: \App\Support\MedicalAidClaimForm::attach($order))
            : null;

        return [
            ['name' => '처방전',
             'path' => $this->pathOf($rxImage?->file_path) ?: $this->pathOf($order->prescription?->image_path),
             'mime' => $rxImage?->file_mime_type],

            /* 기초 대상자에게만 선다 — 아니면 자리 자체를 두지 않는다 */
            ...(\App\Support\MedicalAidClaimForm::applies($order) ? [[
                'name' => '요양비 지급청구서(의료급여)',
                'path' => $this->pathOf($aidClaim?->file_path), 'mime' => 'application/pdf',
            ]] : []),

            ['name' => '개인정보 수집·이용 동의서',
             'path' => $this->pathOf($consent?->file_path), 'mime' => 'application/pdf'],

            ['name' => '거래명세서',
             'path' => $this->pathOf($statement?->file_path), 'mime' => $statement?->file_mime_type],

            ['name' => '전자세금계산서',
             'path' => $this->pathOf($tax?->file_path), 'mime' => 'application/pdf'],

            /* 카드로 받은 건만 선다(2026-09-04 확정). 카드 건은 현금영수증을 내지 않아
               본인부담 몫의 증빙이 이것뿐이다 — 아니면 자리 자체를 두지 않는다.
               현금영수증은 공단에 내지 않는다(환자에게 가는 증빙이다). */
            ...($cardSlip ? [[
                'name' => '카드 매출전표',
                'path' => $this->pathOf($cardSlip->file_path), 'mime' => 'application/pdf',
            ]] : []),

            /* 구입 확인서는 저장해 두는 서류가 아니라 그때그때 그리는 것이라(거래처의
               누적 내역), 여기서는 담지 않고 첫 쪽에 따로 뽑으라 적는다. */
            ['name' => '의료용품 구입 확인서', 'path' => null, 'mime' => null],
        ];
    }

    /**
     * 있는 파일만 돌려준다 — 서버에만 있고 여기 없는 건이 흔하다.
     *
     * 디스크를 하나만 보면 안 된다. 첨부는 public 에 쌓이지만 동의서ㆍ위임장은
     * 기본 디스크(private)에 들어간다 — public 만 보다가 공단에 낼 동의서가
     * 늘 빠진 채로 묶였다.
     */
    private function pathOf(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        return null;
    }

    /** 첫 쪽 — 무엇이 들었고 무엇이 빠졌는가 */
    private function cover(\setasign\Fpdi\Tcpdf\Fpdi $pdf, string $font, Order $order, array $parts): void
    {
        $pdf->AddPage('P', [self::W, self::H]);
        $pdf->SetFont($font, '', 16);
        $pdf->SetXY(self::MARGIN, 24);
        $pdf->Cell(0, 10, '청구 자료', 0, 1, 'C');

        $pdf->SetFont($font, '', 10);
        $pdf->SetXY(self::MARGIN, 42);

        $rx = $order->prescription;
        foreach ([
            '주문번호'  => $order->order_number,
            '처방번호'  => $rx?->rx_number ?? '',
            '이름'      => $order->patient?->name ?? '',
            '관할'      => $rx?->billingOffice?->displayName() ?? ($rx?->local_gov ?? ''),
            '뽑은 날'   => now()->format('Y-m-d'),
        ] as $k => $v) {
            $pdf->Cell(30, 7, $k, 0, 0);
            $pdf->Cell(0, 7, (string) $v, 0, 1);
        }

        $pdf->Ln(6);
        $pdf->SetFont($font, '', 11);
        $pdf->Cell(0, 8, '담긴 서류', 0, 1);
        $pdf->SetFont($font, '', 10);

        foreach ($parts as $p) {
            $pdf->Cell(6, 7, $p['path'] ? 'O' : 'X', 0, 0, 'C');
            $pdf->Cell(70, 7, $p['name'], 0, 0);
            $pdf->Cell(0, 7, $p['path'] ? '' : '— 빠졌습니다', 0, 1);
        }

        $pdf->Ln(4);
        $pdf->SetFont($font, '', 9);
        $pdf->MultiCell(0, 5,
            "X 로 표시된 것은 이 묶음에 없습니다. 그대로 부치면 반려되어 돌아옵니다.\n"
            . '의료용품 구입 확인서는 거래처 화면에서 따로 뽑습니다 — 한 사람의 누적 '
            . '내역이라 주문 하나에 매이지 않습니다.', 0, 'L');
    }

    /** PDF 는 쪽을 그대로 옮겨 붙인다 */
    private function addPdf(\setasign\Fpdi\Tcpdf\Fpdi $pdf, string $file): void
    {
        $count = $pdf->setSourceFile($file);

        for ($i = 1; $i <= $count; $i++) {
            $tpl  = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
    }

    /**
     * 그림은 새 쪽에 앉힌다.
     *
     * 넘치지 않게 줄이되 늘리지는 않는다 — 늘리면 글자가 뭉개져 읽을 수 없다.
     */
    private function addImage(\setasign\Fpdi\Tcpdf\Fpdi $pdf, string $file, string $font, string $name): void
    {
        $info = @getimagesize($file);

        if (!$info) {
            throw new \RuntimeException('그림을 읽지 못했습니다');
        }

        $pdf->AddPage('P', [self::W, self::H]);

        $pdf->SetFont($font, '', 9);
        $pdf->SetXY(self::MARGIN, self::MARGIN);
        $pdf->Cell(0, 6, $name, 0, 1);

        $maxW = self::W - self::MARGIN * 2;
        $maxH = self::H - self::MARGIN * 2 - 8;

        // 화소를 밀리미터로 — 96dpi 로 본다. 스캔본은 대개 그보다 촘촘해 줄어든다.
        $w = $info[0] * 25.4 / 96;
        $h = $info[1] * 25.4 / 96;
        $k = min($maxW / $w, $maxH / $h, 1.0);

        $pdf->Image($file, self::MARGIN, self::MARGIN + 8, $w * $k, $h * $k);
    }

    /** 한글이 박히는 글꼴 — 위임장이 쓰는 것과 같다 */
    private function font(): string
    {
        $ttf = storage_path('fonts/NanumGothic.ttf');

        return is_file($ttf)
            ? \TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 32)
            : 'cid0kr';
    }
}
