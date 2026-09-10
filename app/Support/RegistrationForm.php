<?php

namespace App\Support;

use App\Models\PrescriptionConsent;
use Illuminate\Support\Facades\Log;

/**
 * 자가도뇨 소모성 재료 급여대상자 등록 신청서 — [별지 제4호서식] (2026-09-10 「서명 동의」).
 *
 * 공단에 새로 등록하거나 다시 등록할 때 내는 서류다. 여태 담당자가 종이로 받아
 * 스캔해 올렸는데, 환자의 전자서명이 닿아야 할 서류 가운데 하나다.
 *
 * **우리가 채우는 것은 ① 수진자와 ③ 신청인뿐이다.** ② 요양기관 확인란은 병원이
 * 적고 확인하는 자리라 비워 둔다 — 우리가 아는 값을 얹으면 병원이 확인한 것처럼
 * 보인다.
 *
 * 자리는 config/registration_form.php 에 mm 로 적어 두었다.
 */
final class RegistrationForm
{
    /** 원본 서식 위에 값을 얹어 PDF 바이트를 돌려준다 */
    public static function render(PrescriptionConsent $consent, bool $withSignature = false): string
    {
        $consent->loadMissing('prescription.patient');

        $cfg      = config('registration_form');
        $template = resource_path($cfg['template']);

        if (! is_file($template)) {
            throw new \RuntimeException('등록 신청서 원본 서식을 찾을 수 없습니다.');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetCreator('CE Admin');
        $pdf->SetTitle('자가도뇨 소모성 재료 급여대상자 등록 신청서');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $쪽수 = $pdf->setSourceFile($template);
        $font = self::font($pdf);

        for ($p = 1; $p <= $쪽수; $p++) {
            $tpl  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            /* 값은 앞쪽에만 들어간다 — 뒤쪽은 유의사항과 작성방법이다 */
            if ($p !== 1) {
                continue;
            }

            $pdf->SetTextColor(0, 0, 0);

            foreach (self::values($consent) as $key => $text) {
                $pos = $cfg['fields'][$key] ?? null;
                if (! $pos || $text === null || $text === '') {
                    continue;
                }

                $pdf->SetFont($font, '', $pos['size'] ?? $cfg['size']);
                $맞춤 = $pos['align'] ?? 'L';

                if ($맞춤 === 'R') {
                    $pdf->SetXY($pos['x'] - $pdf->GetStringWidth((string) $text), $pos['y']);
                } elseif ($맞춤 === 'C') {
                    $pdf->SetXY($pos['x'] - $pdf->GetStringWidth((string) $text) / 2, $pos['y']);
                } else {
                    $pdf->SetXY($pos['x'], $pos['y']);
                }

                $pdf->Cell(0, 4, (string) $text, 0, 0, 'L');
            }

            if ($withSignature) {
                self::signature($pdf, $consent, $cfg);
            }

            if ($cfg['grid'] ?? false) {
                self::grid($pdf, $size['width'], $size['height'], $font);
            }
        }

        return $pdf->Output('', 'S');
    }

    /** 서식에 적을 값 — 없는 것은 비워 둔다(지어내지 않는다) */
    private static function values(PrescriptionConsent $consent): array
    {
        $rx = $consent->prescription;
        $pt = $rx?->patient;
        $이제 = now();

        /* 미성년이면 신청인은 법정대리인이다. 수진자 칸은 그대로 아이의 것이다. */
        $신청인 = $consent->is_minor
            ? ($consent->guardian_name ?: $pt?->guardian_name)
            : ($pt?->bare_name ?: $consent->patient_name);
        $관계 = $consent->is_minor
            ? ($consent->guardian_relation ?: $pt?->guardian_relation ?: '법정대리인')
            : '본인';
        $신청인전화 = $consent->is_minor
            ? ($consent->guardian_phone ?: $pt?->guardian_phone)
            : ($pt?->mobile ?: $consent->patient_mobile);

        return [
            'name'       => $pt?->bare_name ?: $consent->patient_name,
            /* 공단에 내는 법정서식이라 가린 번호로는 접수되지 않는다. 푸는 까닭을
               남긴다(P0-1) — 사유 코드는 위임장ㆍ청구서와 같은 것을 쓴다
               (config/rrn.php · 「급여비 지급청구서·위임장 등 법정서식 출력」). */
            'rrn'        => $rx?->resident_no ?: $pt?->residentNoFor('nhis_claim_form'),
            'tel_home'   => PhoneNo::format($pt?->phone),
            'tel_mobile' => PhoneNo::format($pt?->mobile ?: $consent->patient_mobile),
            /* 등록 결과를 문자로 받겠다 — 환자에게 곧바로 닿는 길이다 */
            'sms_yes'    => 'V',

            'apply_y'       => $이제->format('Y'),
            'apply_m'       => $이제->format('n'),
            'apply_d'       => $이제->format('j'),
            'applicant'     => $신청인,
            'relation'      => $관계,
            'tel_applicant' => PhoneNo::format($신청인전화),
        ];
    }

    /** 받아 둔 서명을 그대로 얹는다 — 없으면 비워 둔다 */
    private static function signature(\setasign\Fpdi\Tcpdf\Fpdi $pdf, PrescriptionConsent $consent, array $cfg): void
    {
        /* 미성년이면 보호자 서명이 신청인 자리에 들어간다 */
        $sig = $consent->is_minor
            ? ($consent->guardian_signature_data ?: $consent->signature_data)
            : $consent->signature_data;

        if (! $sig) {
            return;
        }

        $raw = preg_match('#^data:image/\w+;base64,(.+)$#s', $sig, $m) ? $m[1] : $sig;
        $img = base64_decode($raw, true);

        if ($img === false || $img === '') {
            return;
        }

        try {
            $pdf->Image('@' . $img, $cfg['signature']['x'], $cfg['signature']['y'],
                        $cfg['signature']['w'], 0, 'PNG');
        } catch (\Throwable $e) {
            Log::warning('[등록 신청서] 서명을 얹지 못했습니다', ['error' => $e->getMessage()]);
        }
    }

    /** 자리를 맞출 때만 그리는 10mm 눈금 */
    private static function grid(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $w, float $h, string $font): void
    {
        $pdf->SetDrawColor(200, 210, 255);
        $pdf->SetTextColor(120, 140, 220);
        $pdf->SetFont($font, '', 4);

        for ($x = 0; $x <= $w; $x += 10) {
            $pdf->Line($x, 0, $x, $h);
            $pdf->SetXY($x + 0.4, 1);
            $pdf->Cell(8, 2, (string) $x, 0, 0, 'L');
        }
        for ($y = 0; $y <= $h; $y += 10) {
            $pdf->Line(0, $y, $w, $y);
            $pdf->SetXY(0.4, $y + 0.4);
            $pdf->Cell(8, 2, (string) $y, 0, 0, 'L');
        }

        $pdf->SetTextColor(0, 0, 0);
    }

    /** 한글이 깨지지 않게 — 등록해 둔 나눔고딕을 쓴다 */
    private static function font(\setasign\Fpdi\Tcpdf\Fpdi $pdf): string
    {
        try {
            $path = storage_path('fonts/NanumGothic.ttf');
            if (is_file($path)) {
                return \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 32);
            }
        } catch (\Throwable $e) {
            Log::warning('[등록 신청서] 한글 글꼴을 등록하지 못했습니다', ['error' => $e->getMessage()]);
        }

        return 'cid0kr';
    }
}
