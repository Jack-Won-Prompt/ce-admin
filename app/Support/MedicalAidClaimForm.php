<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionConsent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 요양비 지급청구서 — 의료급여법 시행규칙 [별지 제12호서식].
 *
 * 기초(의료급여) 대상자의 청구에 위임장과 함께 낸다(2026-09-01 회신). 위임장은
 * 「돈을 우리 계좌로 받겠다」는 뜻이고, 이것은 「얼마를 청구한다」는 서류다 —
 * 둘은 서로를 대신하지 못한다.
 *
 * 서식은 받은 원본 PDF 위에 값을 얹어 그린다(위임장과 같은 방식). 구입업체명과
 * 수령 계좌는 원본에 이미 인쇄되어 있어 우리가 적지 않는다.
 *
 * 자리는 config/medical_aid_claim.php 에 mm 로 적어 두었다. 서식이 개정되면
 * 그 표만 고친다 — 코드에 좌표를 박으면 개정 때마다 여기를 뒤져야 한다.
 */
final class MedicalAidClaimForm
{
    /** 이 서식을 내는 건인가 — 기초(의료급여) 대상자만이다 */
    public static function applies(Order $order): bool
    {
        return ($order->prescription?->benefit_class ?? '') === '기초';
    }

    /**
     * 만들어 첨부문서로 넣는다. 이미 넣어 둔 것이 있으면 그것을 돌려준다.
     *
     * @return PrescriptionAttachment|null 만들지 못했으면 null(까닭은 로그에 남는다)
     */
    public static function attach(Order $order): ?PrescriptionAttachment
    {
        $order->loadMissing(['patient', 'prescription', 'items']);

        if (! $order->prescription_id || ! self::applies($order)) {
            return null;
        }

        $existing = PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->where('doc_type', 'medical_aid_claim')
            ->latest('id')
            ->first();

        /* 줄만 보고 넘기지 않는다 — 파일이 실제로 있어야 낼 수 있다. 다른 서버에서
           만든 줄이 남아 있거나 파일이 지워지면, 줄만 믿다가 청구 묶음에서 영영
           빠진다(그 사실은 공단이 반려한 뒤에야 드러난다). */
        $쓸수있나 = $existing && $existing->file_path
                 && Storage::disk('public')->exists($existing->file_path);

        /* **서명보다 먼저 만든 것은 다시 그린다** (2026-09-10 확인요청 11쪽).

           서식 아래에 청구인 이름과 「(서명 또는 인)」이 있고, 서명은 환자가 동의를
           마칠 때 들어간다. 그런데 청구서는 그보다 먼저 만들어지는 일이 잦다 —
           청구 묶음을 미리 훑거나 서명 화면에서 미리 보여 줄 때다.

           여태는 한 번 만든 것을 그대로 다시 썼다. 그래서 서명 전에 만들어진 건은
           환자가 서명을 마친 뒤에도 서명란이 빈 채로 남았고, 그 파일이 그대로
           청구에 나갔다 — 서명 없는 청구서는 그 자리에서 반려된다.

           서명이 파일보다 나중에 들어왔으면 다시 그린다. 옛 파일은 지운다 — 두 벌이
           남으면 어느 것이 서명본인지 알 수 없다. */
        if ($쓸수있나 && ($서명때 = self::서명시각($order)) && $서명때->gt($existing->created_at)) {
            $쓸수있나 = false;
        }

        if ($쓸수있나) {
            return $existing;
        }

        try {
            $pdf  = self::render($order);
            $name = '요양비지급청구서_' . ($order->patient?->name ?? '') . '_' . $order->order_number . '.pdf';
            $path = 'attachments/' . $order->prescription_id . '/' . uniqid('mac_') . '.pdf';

            Storage::disk('public')->put($path, $pdf);

            /* 다시 그린 것이면 옛 줄과 파일을 걷는다 — 두 벌이 남으면 어느 것이
               서명본인지 알 수 없고, 청구 묶음이 옛것을 집을 수 있다. */
            if ($existing) {
                if ($existing->file_path && $existing->file_path !== $path) {
                    Storage::disk('public')->delete($existing->file_path);
                }
                $existing->delete();
            }

            return PrescriptionAttachment::create([
                'prescription_id'    => $order->prescription_id,
                'file_path'          => $path,
                'file_original_name' => $name,
                'file_mime_type'     => 'application/pdf',
                'file_size'          => strlen($pdf),
                'doc_type'           => 'medical_aid_claim',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[요양비 지급청구서] 만들지 못했습니다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** 원본 서식 위에 값을 얹어 PDF 바이트를 돌려준다 */
    public static function render(Order $order): string
    {
        $order->loadMissing(['patient', 'prescription.billingOffice', 'items']);

        $cfg      = config('medical_aid_claim');
        $template = resource_path($cfg['template']);

        if (! is_file($template)) {
            throw new \RuntimeException('요양비 지급청구서 원본 서식을 찾을 수 없습니다.');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetCreator('CE Admin');
        $pdf->SetTitle('요양비 지급청구서 — ' . $order->order_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pdf->setSourceFile($template);
        $tpl  = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        $font = self::font($pdf);
        $pdf->SetTextColor(0, 0, 0);

        foreach (self::values($order) as $key => $text) {
            $pos = $cfg['fields'][$key] ?? null;
            if (! $pos || $text === null || $text === '') {
                continue;
            }

            $pdf->SetFont($font, '', $pos['size'] ?? $cfg['size']);

            if (($pos['align'] ?? 'L') === 'R') {
                /* 금액ㆍ개수는 오른쪽으로 맞춘다 — 자릿수가 늘면 왼쪽으로 자라야
                   「원」ㆍ「개」 글자를 밀지 않는다 */
                $w = $pdf->GetStringWidth((string) $text);
                $pdf->SetXY($pos['x'] - $w, $pos['y']);
            } else {
                $pdf->SetXY($pos['x'], $pos['y']);
            }

            $pdf->Cell(0, 4, (string) $text, 0, 0, 'L');
        }

        self::signature($pdf, $order, $cfg);

        return $pdf->Output('', 'S');
    }

    /** 서식에 적을 값 — 없는 것은 비워 둔다(지어내지 않는다) */
    private static function values(Order $order): array
    {
        $rx   = $order->prescription;
        $pt   = $order->patient;
        $days = (int) ($rx?->total_days ?? 0);
        $from = $rx?->buy_date ? Carbon::parse($rx->buy_date) : null;
        $now  = now();

        $nhis  = (int) round((float) ($order->items->sum('nhis_amount')   ?? 0));
        $copay = (int) round((float) ($order->items->sum('patient_copay') ?? 0));

        return [
            /* 서류에 적히는 이름에는 (E) 를 두지 않는다 (2026-09-08 확인요청 11쪽).
               사업부가 IC 라는 우리 쪽 표시일 뿐이고, 관청이 보는 종이에는 설 자리가
               없다 — 이 서식의 성명ㆍ청구인 두 칸에 그대로 찍혀 나가고 있었다. */
            'patient_name'     => \App\Models\Patient::bare($pt?->name ?: $rx?->patient_name_ocr),
            'patient_rrn'      => $rx?->resident_no ?: $pt?->residentNoFor('nhis_claim_form'),
            /* 보장기관은 이 건의 관할 지자체다 — 시장ㆍ군수ㆍ구청장에게 내는 서류다 */
            'insurer_name'     => $rx?->billingOffice?->office_name,

            'hospital_name'    => $rx?->hospital_name,
            'hospital_code'    => $rx?->hospital_code,
            'visit_outpatient' => 'V',

            'issued_date'      => $rx?->issued_date ? Carbon::parse($rx->issued_date)->format('Y. m. d.') : null,
            'care_from_y'      => $from?->format('Y'),
            'care_from_m'      => $from?->format('m'),
            'care_from_d'      => $from?->format('d'),
            'care_days'        => $days ?: null,
            'disease_name'     => $rx?->disease_name,
            'disease_code'     => $rx?->disease_code,

            'amount_total'     => number_format($nhis + $copay),
            'supply_days'      => $days ?: null,
            'supply_count'     => number_format((int) ($rx?->total_count ?? $order->items->sum('quantity'))),

            'request_date'     => $now->format('Y.  m.  d.'),
            'amount_decided'   => number_format($nhis + $copay),
            'amount_copay'     => number_format($copay),
            'amount_pay'       => number_format($nhis),

            'claim_y'          => $now->format('Y'),
            'claim_m'          => $now->format('n'),
            'claim_d'          => $now->format('j'),
            /* 청구인은 환자 본인이다 — 서식의 「관계」 칸에 「본인」이 인쇄되어 있다.
               우리 계좌로 받는 것은 수령 계좌 칸이 정하는 별개의 일이다. */
            'claimant_name'    => \App\Models\Patient::bare($pt?->name ?: $rx?->patient_name_ocr),
            'claimant_phone'   => PhoneNo::format($pt?->mobile),
        ];
    }

    /**
     * 이 건의 서명이 언제 들어왔는가 — 없으면 null.
     *
     * 서명을 담는 줄이 갱신된 때를 본다. 동의를 마치면 그 줄에 서명이 실리므로,
     * 그 시각이 곧 「서명이 생긴 때」다.
     */
    private static function 서명시각(Order $order): ?Carbon
    {
        $때 = PrescriptionConsent::where('prescription_id', $order->prescription_id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest('id')
            ->value('updated_at');

        return $때 ? Carbon::parse($때) : null;
    }

    /** 위임동의에서 받아 둔 서명을 그대로 얹는다 — 없으면 비워 둔다 */
    private static function signature(\setasign\Fpdi\Tcpdf\Fpdi $pdf, Order $order, array $cfg): void
    {
        $sig = PrescriptionConsent::where('prescription_id', $order->prescription_id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest('id')
            ->value('signature_data');

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
            Log::warning('[요양비 지급청구서] 서명을 얹지 못했습니다', ['error' => $e->getMessage()]);
        }
    }

    /** 한글이 깨지지 않게 — 등록해 둔 나눔고딕을 쓴다 */
    private static function font(\setasign\Fpdi\Tcpdf\Fpdi $pdf): string
    {
        $ttf = storage_path('fonts/NanumGothic.ttf');

        if (is_file($ttf)) {
            try {
                return \TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 96);
            } catch (\Throwable $e) {
                Log::warning('[요양비 지급청구서] 글꼴을 등록하지 못했습니다', ['error' => $e->getMessage()]);
            }
        }

        return 'cid0kr';
    }
}
