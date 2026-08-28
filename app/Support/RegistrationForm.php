<?php
// app/Support/RegistrationForm.php
// 자가도뇨 소모성 재료 급여대상자 등록 신청서(별지 제4호서식) — 원본 위에 값을 얹는다.

namespace App\Support;

use App\Models\Prescription;
use App\Models\RegistrationSetting;

/**
 * 원본 양식 PDF 에 우리가 아는 값을 얹어 낸다. 위임장(요양비 지급청구 위임장)과 같은
 * 방식이다 — 좌표는 코드에 박지 않고 설정에서 읽는다.
 *
 * ② 요양기관 확인란은 병원이 적고 직인을 찍는 자리다. 우리가 아는 값만 채우고
 * 「위에 기록한 사항이 사실임을 확인함」의 날짜ㆍ직인ㆍ서명 자리는 비워 둔다 —
 * 확인의 책임은 병원에 남아야 한다.
 */
class RegistrationForm
{
    /** 원본 양식. 두 쪽인데 적을 칸은 1쪽에만 있다. */
    private const TEMPLATE = 'pdf/registration_form.pdf';

    /**
     * @return string PDF 바이너리
     */
    public static function build(Prescription $prescription): string
    {
        RegistrationSetting::applyToConfig();

        $path = resource_path(self::TEMPLATE);
        if (! is_file($path)) {
            throw new \RuntimeException('등록 신청서 원본 양식 파일을 찾을 수 없습니다.');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($path);

        $font = \TCPDF_FONTS::addTTFfont(storage_path('fonts/NanumGothic.ttf'), 'TrueTypeUnicode', '', 32);

        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl  = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

            if ($p === 1) {
                self::stamp($pdf, $prescription, $font);
            }
        }

        return $pdf->Output('', 'S');
    }

    private static function stamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, Prescription $rx, string $font): void
    {
        $patient = $rx->patient;
        $fields  = (array) config('registration.fields', []);
        $checks  = (array) config('registration.checks', []);

        $pdf->SetTextColor(0, 0, 0);

        $put = function (string $key, ?string $text) use ($pdf, $font, $fields): void {
            $text = trim((string) $text);
            $f    = $fields[$key] ?? null;
            if ($text === '' || ! $f) return;
            $pdf->SetFont($font, '', (float) ($f['size'] ?? 8));
            $pdf->Text((float) $f['x'], (float) $f['y'], $text);
        };

        /* 체크는 선 두 개로 긋는다. 글리프(✔)는 폰트마다 깨져 위임장에서도 그렇게 한다. */
        $tick = function (string $key) use ($pdf, $checks): void {
            $c = $checks[$key] ?? null;
            if (! $c || ! isset($c['x'], $c['y'])) return;
            $x = (float) $c['x'];
            $y = (float) $c['y'];
            $pdf->SetLineStyle(['width' => 0.4, 'cap' => 'round', 'join' => 'round', 'color' => [0, 0, 0]]);
            $pdf->Line($x,       $y + 0.9, $x + 1.2, $y + 2.2);
            $pdf->Line($x + 1.2, $y + 2.2, $x + 3.5, $y - 1.0);
        };

        // ── ① 수진자 ──────────────────────────────────────
        $put('patient_name', $patient?->bare_name ?: $rx->patient_name_ocr);

        /* 법정서식이라 주민번호 평문이 필요한 지점이다. 꺼내 쓰면 감사 로그가 남는다(P0-1).
           사유 코드는 위임장과 같은 것을 쓴다 — config/rrn.php 의 그 설명이
           「급여비 지급청구서ㆍ위임장 등 법정서식 출력」이라 이 서식도 그 안이다.
           처방전에 적힌 번호를 먼저 쓰고, 없으면 환자 정보의 번호를 쓴다. */
        $rrn = $rx->residentNoOcrFor('nhis_claim_form')
               ?: $patient?->residentNoFor('nhis_claim_form');
        if ($rrn && preg_match('/^(\d{6})-?(\d{7})$/', preg_replace('/\s/', '', $rrn), $m)) {
            $rrn = $m[1] . '-' . $m[2];
        }
        $put('patient_rrn', $rrn);

        // 자택과 휴대전화는 따로 적는 칸이다 — 우리 쪽 「전화번호1/2」가 그 둘이다
        $put('phone_mobile', $patient?->mobile ?: $rx->mobile_ocr);
        $put('phone_home',   $patient?->phone);

        // 등록결과통보(SMS) — 고르지 않았으면 아무것도 찍지 않는다
        if ($rx->reg_sms_notify !== null) {
            $tick($rx->reg_sms_notify ? 'sms_yes' : 'sms_no');
        }

        // ── ② 요양기관 확인란 — 아는 값만 ──────────────────
        $put('department',     $rx->department);
        $put('diagnosis_date', self::date($rx->diagnosis_date));
        $put('disease_code',   $rx->disease_code);
        $put('disease_name',   $rx->disease_name);
        $put('uro_date',       self::date($rx->uro_date));
        $put('hospital_name',  $rx->hospital_name);
        $put('hospital_code',  $rx->hospital_code);
        $put('doctor_name',    $rx->doctor_name);
        $put('license_no',     $rx->license_no);
        $put('specialty',      $rx->specialty);
        $put('specialist_no',  $rx->specialist_no);

        if ($rx->reg_dx_type) {
            $tick($rx->reg_dx_type);
        }
        foreach ((array) $rx->reg_confirm_items as $key) {
            $tick((string) $key);
        }

        /* 「위에 기록한 사항이 사실임을 확인함」의 날짜ㆍ요양기관 직인ㆍ의사 서명은 비운다.
           우리가 찍을 것이 아니다. */

        // ── ③ 신청인 ──────────────────────────────────────
        $today = now();
        $put('apply_y', $today->format('Y'));
        $put('apply_m', $today->format('n'));
        $put('apply_d', $today->format('j'));

        $relation  = $rx->reg_relation ?: '본인';
        $applicant = $relation === '본인'
            ? ($patient?->bare_name ?: $rx->patient_name_ocr)
            : ($patient?->guardian_name ?: $patient?->bare_name ?: $rx->patient_name_ocr);

        $put('applicant_name',  $applicant);
        $put('relation',        $relation);
        $put('applicant_phone', $patient?->mobile ?: $rx->mobile_ocr);

        /* 신청인 서명 — 위임동의에서 받아 둔 그 서명을 그대로 쓴다. 같은 사람이 같은 건에
           두 번 서명하게 하지 않는다. 아직 서명이 없으면 자리를 비워 인쇄해 받는다. */
        $sig = $rx->consents()->where('status', 'agreed')->latest()->value('signature_data');
        if ($sig) {
            if (preg_match('#^data:image/\w+;base64,(.+)$#s', $sig, $sm)) $sig = $sm[1];
            $bytes = base64_decode($sig, true);
            if ($bytes !== false && $bytes !== '') {
                $pdf->Image(
                    '@' . $bytes,
                    (float) config('registration.signature.x', 151),
                    (float) config('registration.signature.y', 196),
                    (float) config('registration.signature.w', 28),
                    0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false
                );
            }
        }
    }

    private static function date(mixed $v): string
    {
        if (! $v) return '';
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $v;
        }
    }
}
