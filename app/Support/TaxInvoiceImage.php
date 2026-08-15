<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * 발행된 전자세금계산서를 장표 이미지(PNG)로 그린다.
 *
 * 왜 이미지인가 — 공단에 보내는 팩스 합본은 dompdf 로 만드는데, dompdf 는 외부 PDF 를
 * 페이지로 끼워 넣지 못한다(첨부 PDF 를 못 싣는 것과 같은 이유다). 장표를 이미지로 두면
 * 처방전 사진·신분증과 똑같은 방법으로 한 장씩 합본에 실린다. 팝빌 팩스도 이미지를 받는다.
 *
 * 왜 GD 로 직접 그리는가 — 이 서버에는 Imagick 도 Ghostscript 도 없어 PDF→이미지 변환을
 * 쓸 수 없다. 한글은 storage/fonts/NanumGothic.ttf 로 찍는다.
 */
final class TaxInvoiceImage
{
    /** A5 세로, 150dpi */
    private const W = 874;
    private const H = 1240;

    private const PAD = 56;

    public static function fontPath(): string
    {
        $path = storage_path('fonts/NanumGothic.ttf');
        if (!is_file($path)) {
            throw new RuntimeException('한글 폰트를 찾을 수 없습니다: ' . $path);
        }
        return $path;
    }

    /** 저장 경로 — PDF 와 같은 폴더에 둔다 */
    public static function path(Order $order): string
    {
        return 'tax_invoices/' . $order->id . '/'
            . '세금계산서_' . ($order->tax_invoice_biz_name ?? '') . '_' . $order->order_number . '.png';
    }

    /**
     * 장표 이미지가 없으면 만들고 서류로 등록한다. 저장 경로를 돌려준다.
     *
     * 발행 직후·팩스 합본·팝빌 전송 세 군데가 모두 이 함수를 부른다. 옛 발행 건이나
     * 이미지가 지워진 건도 여기서 되살아난다.
     */
    public static function ensure(Order $order): string
    {
        $path = self::path($order);

        if (!Storage::exists($path)) {
            Storage::put($path, self::render($order));
        }

        $exists = PrescriptionDocument::where('prescription_id', $order->prescription_id)
            ->where('file_path', $path)->exists();

        if (!$exists && $order->prescription_id) {
            PrescriptionDocument::create([
                'prescription_id'   => $order->prescription_id,
                'patient_id'        => $order->patient_id,
                'created_by'        => Auth::id(),
                'type'              => 'tax_invoice',
                'file_path'         => $path,
                'original_filename' => basename($path),
            ]);
        }

        return $path;
    }

    /** PNG 바이너리 */
    public static function render(Order $order): string
    {
        $font = self::fontPath();
        $im   = imagecreatetruecolor(self::W, self::H);

        $white = imagecolorallocate($im, 255, 255, 255);
        $ink   = imagecolorallocate($im, 17, 17, 17);
        $gray  = imagecolorallocate($im, 110, 110, 110);
        $line  = imagecolorallocate($im, 205, 205, 205);
        $head  = imagecolorallocate($im, 245, 246, 248);
        imagefill($im, 0, 0, $white);

        $x0 = self::PAD;
        $x1 = self::W - self::PAD;

        /* 제목 */
        $title = '전 자 세 금 계 산 서';
        $box   = imagettfbbox(26, 0, $font, $title);
        imagettftext($im, 26, 0, (int) ((self::W - ($box[2] - $box[0])) / 2), 96, $ink, $font, $title);
        $sub = '(공급받는자 보관용)';
        $box = imagettfbbox(11, 0, $font, $sub);
        imagettftext($im, 11, 0, (int) ((self::W - ($box[2] - $box[0])) / 2), 124, $gray, $font, $sub);
        imagefilledrectangle($im, $x0, 140, $x1, 142, $ink);

        $y = 176;

        /* 한 줄 = 라벨칸 + 값칸 */
        $row = function (string $label, string $value, bool $strong = false) use (
            $im, $font, $ink, $gray, $line, $head, $x0, $x1, &$y
        ): void {
            $h  = 46;
            $lw = 236;
            imagefilledrectangle($im, $x0, $y, $x0 + $lw, $y + $h, $head);
            imagerectangle($im, $x0, $y, $x1, $y + $h, $line);
            imageline($im, $x0 + $lw, $y, $x0 + $lw, $y + $h, $line);
            imagettftext($im, 11, 0, $x0 + 16, $y + 29, $gray, $font, $label);
            imagettftext($im, $strong ? 15 : 12, 0, $x0 + $lw + 18, $y + ($strong ? 31 : 29), $ink, $font, $value);
            $y += $h;
        };

        /* 구역 제목 */
        $band = function (string $text) use ($im, $font, $ink, $x0, $x1, &$y): void {
            $y += 18;
            imagettftext($im, 12, 0, $x0 + 2, $y + 14, $ink, $font, $text);
            $y += 24;
        };

        $money = fn ($n) => '\\' . number_format((int) $n);

        $supply = (int) $order->tax_invoice_supply;
        $vat    = (int) $order->tax_invoice_vat;
        $issued = $order->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '-';

        $row('국세청 승인번호', (string) $order->tax_invoice_no, true);
        $row('작성일자', $issued);

        $band('■ 공급자');
        $row('등록번호', self::bizNo((string) config('popbill.test.corp_num')));
        $row('상호', (string) config('popbill.company.corp_name'));
        $row('대표자', (string) config('popbill.company.ceo_name'));
        $row('사업장 주소', self::fit((string) config('popbill.company.addr'), 34));
        $row('업태 / 종목', config('popbill.company.biz_type') . ' / ' . config('popbill.company.biz_class'));

        $band('■ 공급받는자');
        $row('구분', self::isPerson($order) ? '개인' : '사업자');
        $row(self::isPerson($order) ? '주민등록번호' : '등록번호', (string) $order->tax_invoice_biz_no);
        $row('성명 / 상호', (string) $order->tax_invoice_biz_name);
        $row('대표자', (string) $order->tax_invoice_ceo_name);

        $band('■ 금액');
        $row('공급가액', $money($supply));
        $row('세액', $money($vat));
        $row('합계금액', $money($supply + $vat), true);

        $band('■ 품목');
        $row('품명', self::fit((string) ($order->product_name ?? '처방 제품'), 30));
        $row('주문번호', (string) $order->order_number);

        /* 꼬리말 */
        $foot = '본 세금계산서는 국세청 전자세금계산서 시스템을 통해 발행되었습니다.';
        imageline($im, $x0, self::H - 96, $x1, self::H - 96, $line);
        imagettftext($im, 10, 0, $x0, self::H - 70, $gray, $font, $foot);
        imagettftext($im, 9, 0, $x0, self::H - 48, $gray, $font, '출력 ' . now()->format('Y-m-d H:i'));

        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return $bin;
    }

    /** 공급받는자가 개인인지 — 저장된 번호가 주민번호 마스킹 모양이면 개인이다 */
    private static function isPerson(Order $order): bool
    {
        return (bool) preg_match('/^\d{6}-\d/', (string) $order->tax_invoice_biz_no);
    }

    private static function bizNo(string $digits): string
    {
        $d = preg_replace('/\D/', '', $digits);
        return strlen($d) === 10
            ? substr($d, 0, 3) . '-' . substr($d, 3, 2) . '-' . substr($d, 5)
            : $digits;
    }

    /** 칸을 넘치면 자른다 — GD 는 줄바꿈을 해 주지 않는다 */
    private static function fit(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
}
