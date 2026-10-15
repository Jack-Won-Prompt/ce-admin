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
    /**
     * 이 서식을 내는 자격 — **기초(의료급여)뿐이다** (2026-10-10 지시 · SR #116).
     *
     * 2026-09-17 에는 차상위경감도 넣었다. 「본인부담이 없고 기관이 전액을 낸다」가
     * 그 근거였는데, 그것은 **누가 내는가**이지 **어느 서식인가**가 아니었다.
     *
     * 이 서식은 **의료급여법** 시행규칙 [별지 제12호서식]이고 지자체(시군구청)에 낸다.
     * 차상위경감은 **국민건강보험** 자격이라 청구처가 건강보험공단이다
     * (ClaimAgency::fromBenefitClass — 차상위경감 → nhis, 기초 → local). 같은 건
     * 안에서 청구처는 공단으로 정해 두고 서류는 지자체 것을 만들고 있었다.
     *
     * 운영에서 차상위경감 8건에 이 서식이 붙어 있었고(2026-10-10 조회) 다행히 여덟 건
     * 모두 아직 팩스로 나가지 않았다. 붙은 것은 지우지 않는다 — 여기서 자격이 빠지면
     * 청구 묶음ㆍ공단 창ㆍ서명 화면에서 함께 빠진다.
     *
     * 차상위경감용 **국민건강보험 요양비 지급청구서** 원본을 받으면 그때 따로 만든다.
     */
    public const 자격 = ['기초'];

    /**
     * 이 서식을 내는 건인가.
     *
     * **유형을 먼저 본다** (2026-10-01 CASE 6 점검에서 드러남). 여태 자격만 보았다.
     * 처방외는 처방전 없이 제 돈으로 사는 건이라 요양비 청구가 애초에 없는데,
     * 처방전으로 시작한 뒤 유형만 처방외로 바꾸면 자격이 그대로 남는다 —
     * 화면에 유형이 처방외일 때 자격을 비우거나 막는 자리가 없다.
     *
     * 그러면 입금이 확인되는 순간 DepositAutoIssue 가 요양비 지급청구서를 그려
     * 주문에 붙인다. 그 서류는 서류 관리에 남아 나중에 공단ㆍ시군구청으로 나갈 수
     * 있다 — 낼 일이 없는 청구서다.
     *
     * 같은 성격의 다른 판정(BillingStrategy::resolveㆍneedsDelegationㆍ
     * RepurchaseWindow::blockㆍPaymentLinkService)은 모두 유형을 먼저 본다.
     * 이 하나만 보지 않았다.
     */
    public static function applies(Order $order): bool
    {
        $유형 = (string) ($order->prescription?->counsel_acc_add_type ?? '');

        if ($유형 === BillingStrategy::TYPE_NONRX) {
            return false;
        }

        return in_array(trim((string) ($order->prescription?->benefit_class ?? '')), self::자격, true);
    }

    /**
     * 만들어 첨부문서로 넣는다. 이미 넣어 둔 것이 있으면 그것을 돌려준다.
     *
     * @return PrescriptionAttachment|null 만들지 못했으면 null(까닭은 로그에 남는다)
     */
    public static function attach(Order $order, bool $다시그린다 = false): ?PrescriptionAttachment
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

        /* **금액이 정해진 뒤에는 다시 그린다** (2026-09-21 시험에서 찾음).

           서명은 제품을 담기 전에 받는다. 그래서 서명 뒤에 만들어진 청구서라도
           그때는 주문 줄이 비어 있어 금액 칸이 모두 0 으로 찍혔다 — 금액이 0 인
           청구서는 공단ㆍ지자체가 그 자리에서 되돌려 보낸다. 증빙을 낼 때
           (DepositAutoIssue) 이 자리를 다시 불러 금액이 든 것으로 갈아 준다. */
        if ($다시그린다) {
            $쓸수있나 = false;
        }

        if ($쓸수있나) {
            return $existing;
        }

        try {
            $pdf  = self::render($order);
            $name = '요양비지급청구서_' . \App\Models\Patient::실명($order->patient?->name) . '_' . $order->order_number . '.pdf';
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

            return PrescriptionAttachment::한장만만들기([
                'prescription_id'    => $order->prescription_id,
                'file_path'          => $path,
                'file_original_name' => $name,
                'file_mime_type'     => 'application/pdf',
                'file_size'          => strlen($pdf),
                'doc_type'           => 'medical_aid_claim',
            ], $path);
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

        /* 화면(설정 ▸ 지급청구서 설정)에서 고친 자리를 먼저 얹는다(2026-09-21).
           담아 둔 것이 없으면 설정 파일 기본값 그대로다. */
        \App\Models\MedicalAidClaimSetting::applyToConfig();

        return self::그리기(self::values($order), $order, '요양비 지급청구서 — ' . $order->order_number);
    }

    /**
     * 설정 화면의 **미리보기** — 지금 자리로 한 장 그려 본다 (2026-10-15 지시).
     *
     * 자리를 고치고 나면 그것이 종이에 어떻게 앉는지 바로 보아야 한다. 여태는
     * 고친 뒤 실제 건을 다시 만들어야 알 수 있었고, **이미 만들어 둔 청구서는
     * 설정을 바꿔도 그대로**라 「고쳐도 그대로다」로 읽혔다.
     *
     * **실제 환자 자료를 쓰지 않는다.** 미리보기는 화면에 띄우는 것이라 거기에
     * 남의 주민번호가 실릴 까닭이 없다. 대신 **칸마다 긴 보기**를 넣는다 — 짧은
     * 글로는 칸을 넘치는지 알 수 없다.
     *
     * 서명은 받아 둔 그림이 없으므로 **자리를 네모로** 보인다.
     */
    public static function 미리보기(?array $덮을자리 = null): string
    {
        \App\Models\MedicalAidClaimSetting::applyToConfig();

        /* 아직 담지 않은 자리로도 볼 수 있다 — 설정 화면이 입력한 그대로 넘긴다 */
        if ($덮을자리) {
            $바탕 = config('medical_aid_claim.fields', []);

            foreach ($덮을자리 as $키 => $값) {
                if (! isset($바탕[$키]) || ! is_array($값)) {
                    continue;
                }
                foreach (['x', 'y', 'size'] as $칸) {
                    if (isset($값[$칸]) && is_numeric($값[$칸])) {
                        $바탕[$키][$칸] = (float) $값[$칸];
                    }
                }
                if (isset($값['align']) && in_array($값['align'], ['L', 'R', 'C'], true)) {
                    $바탕[$키]['align'] = $값['align'];
                }
            }

            config(['medical_aid_claim.fields' => $바탕]);
        }

        return self::그리기(self::보기값(), null, '요양비 지급청구서 — 미리보기');
    }

    /** 미리보기에 넣는 보기 값 — 칸을 넘치는지 보이게 **긴 것**으로 둔다 */
    private static function 보기값(): array
    {
        return [
            'patient_name'   => '홍길동',
            'patient_rrn'    => '880101-1234567',
            'insurer_name'   => '서울특별시 영등포구청',
            'hospital_name'  => '(의)인길의료재단길병원',
            'hospital_code'  => '31100309',
            'issued_date'    => '2026. 10. 15.',
            'care_from_y'    => '2026',
            'care_from_m'    => '10',
            'care_from_d'    => '15',
            'care_days'      => '90',
            'disease_name'   => '달리 분류되지 않은 이완성 신경병성 방광',
            'amount_total'   => '1,234,567',
            'supply_days'    => '90',
            'supply_count'   => '540',
            'claim_y'        => '2026',
            'claim_m'        => '10',
            'claim_d'        => '15',
            'claimant_name'  => '홍길동',
            'claimant_phone' => '010-1234-5678',
            'relation'       => '본인',
        ];
    }

    /**
     * 원본 서식 위에 값을 얹어 PDF 바이트를 돌려준다 — **그리는 자리는 여기 하나**다.
     *
     * 실제 발행과 미리보기가 같은 자리를 쓴다. 두 곳이 따로 그리면 미리보기에서
     * 맞춰 둔 자리가 종이에서는 다르게 앉는다.
     *
     * `$order` 가 없으면 미리보기다 — 서명은 받아 둔 그림 대신 자리만 네모로 보인다.
     */
    private static function 그리기(array $값들, ?Order $order, string $제목): string
    {
        $cfg      = config('medical_aid_claim');
        $template = resource_path($cfg['template']);

        if (! is_file($template)) {
            throw new \RuntimeException('요양비 지급청구서 원본 서식을 찾을 수 없습니다.');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetCreator('CE Admin');
        $pdf->SetTitle($제목);
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

        foreach ($값들 as $key => $text) {
            $pos = $cfg['fields'][$key] ?? null;
            if (! $pos || $text === null || $text === '') {
                continue;
            }

            $크기 = (float) ($pos['size'] ?? $cfg['size']);
            $pdf->SetFont($font, '', $크기);

            /* **칸을 넘치면 글자를 줄인다** (2026-10-15).

               상병명은 길이가 제각이다 — 「방광염」처럼 짧은 것도 있고
               「달리 분류되지 않은 이완성 신경병성 방광」처럼 스무두 자인 것도 있다.
               운영 65건 가운데 서른여덟이 열네 자를 넘어, 그대로 찍으면 옆 칸(회색
               「상병코드」)을 침범한다.

               칸 폭(w)을 적어 둔 칸은 그 폭에 들어갈 때까지 줄인다. 잘라 버리지
               않는다 — 관청에 내는 서류라 말이 반토막 나면 읽히지 않는다. */
            if (($칸폭 = (float) ($pos['w'] ?? 0)) > 0) {
                while ($크기 > 4.5 && $pdf->GetStringWidth((string) $text) > $칸폭) {
                    $크기 -= 0.25;
                    $pdf->SetFont($font, '', $크기);
                }
            }

            $맞춤 = $pos['align'] ?? 'L';

            if ($맞춤 === 'R') {
                /* 금액ㆍ개수는 오른쪽으로 맞춘다 — 자릿수가 늘면 왼쪽으로 자라야
                   「원」ㆍ「개」 글자를 밀지 않는다 */
                $w = $pdf->GetStringWidth((string) $text);
                $pdf->SetXY($pos['x'] - $w, $pos['y']);
            } elseif ($맞춤 === 'C') {
                /* 칸 한가운데에 놓는다 (2026-10-12 · SR #162). 이때 x 는 왼쪽 끝이
                   아니라 **칸의 가운데**다 — 설정에서 그 자리를 찍는다. */
                $w = $pdf->GetStringWidth((string) $text);
                $pdf->SetXY($pos['x'] - $w / 2, $pos['y']);
            } else {
                $pdf->SetXY($pos['x'], $pos['y']);
            }

            $pdf->Cell(0, 4, (string) $text, 0, 0, 'L');
        }

        if ($order) {
            self::signature($pdf, $order, $cfg);
        } else {
            /* 미리보기 — 받아 둔 서명이 없으므로 **자리만** 보인다 */
            $sig = $cfg['signature'] ?? null;

            if ($sig) {
                $pdf->SetDrawColor(0, 150, 0);
                $pdf->SetLineWidth(0.3);
                $pdf->Rect((float) $sig['x'], (float) $sig['y'], (float) $sig['w'], 5);
                $pdf->SetFont($font, '', 5);
                $pdf->SetTextColor(0, 150, 0);
                $pdf->SetXY((float) $sig['x'], (float) $sig['y'] - 3);
                $pdf->Cell((float) $sig['w'], 3, '서명 자리', 0, 0, 'C');
                $pdf->SetTextColor(0, 0, 0);
            }
        }

        return $pdf->Output('', 'S');
    }

    /** 서식에 적을 값 — 없는 것은 비워 둔다(지어내지 않는다) */
    private static function values(Order $order): array
    {
        $rx   = $order->prescription;
        $pt   = $order->patient;
        $days = (int) ($rx?->total_days ?? 0);
        /* ⑤ 진료기간은 **실제 구매한 날**이다 (2026-10-15 지시).

           처방전 발행일(④)과는 다른 날이다 — 처방을 받고 며칠 뒤에 사기도 한다.

           2026-10-12 에는 주문 확정 날짜(거래명세서 발행일)를 쓰게 했다. 처방전의
           구매일(buy_date)이 대개 비어 있어 진료기간이 통째로 공란으로 나갔기
           때문이다. 그 까닭은 여전해서 **구매일을 먼저 보고, 없을 때만** 거래명세서
           발행일로 물러난다 — 비는 것보다는 가까운 날이라도 서는 편이 낫다. */
        $from = $rx?->buy_date
            ? Carbon::parse($rx->buy_date)
            : ($order->statement_date ? Carbon::parse($order->statement_date) : null);
        $now  = now();

        $nhis  = (int) round((float) ($order->items->sum('nhis_amount')   ?? 0));
        $copay = (int) round((float) ($order->items->sum('patient_copay') ?? 0));

        return [
            /* 서류에 적히는 이름에는 (E) 를 두지 않는다 (2026-09-08 확인요청 11쪽).
               사업부가 IC 라는 우리 쪽 표시일 뿐이고, 관청이 보는 종이에는 설 자리가
               없다 — 이 서식의 성명ㆍ청구인 두 칸에 그대로 찍혀 나가고 있었다. */
            /* 공단에 내는 서류의 성명 — 동명이인 꼬리는 우리 표시다 (2026-10-06 · SR #90) */
            'patient_name'     => \App\Models\Patient::실명($pt?->name ?: $rx?->patient_name_ocr),
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

            'amount_total'     => number_format($nhis + $copay),
            'supply_days'      => $days ?: null,
            /* ⑩ 개수는 **실제로 산 수량**이다 (2026-10-15 지시).

               여태 처방전의 총계(total_count)를 먼저 보았다. 그것은 처방에 적힌
               수이지 이번에 산 수가 아니다 — 나누어 사면 갈린다. 운영에서 처방 총계
               540 개인데 이번 주문은 360 개인 건이 있었고, 청구서에는 540 이 찍혔다.

               주문에 담긴 품목의 수량을 먼저 보고, 그것이 없을 때만 처방 총계로
               물러난다. */
            'supply_count'     => number_format((int) ($order->items->sum('quantity')
                                                       ?: ($rx?->total_count ?? 0))),

            'request_date'     => $now->format('Y.  m.  d.'),
            'amount_decided'   => number_format($nhis + $copay),
            'amount_copay'     => number_format($copay),
            'amount_pay'       => number_format($nhis),

            'claim_y'          => $now->format('Y'),
            'claim_m'          => $now->format('n'),
            'claim_d'          => $now->format('j'),
            /* 청구인은 환자 본인이다 — 우리 계좌로 받는 것은 수령 계좌 칸이
               정하는 별개의 일이다.

               옇 서식에는 「관계」 칸에 「본인」이 인쇄되어 있었는데, **새 서식에는 없다**
               (2026-10-15). 비워 두면 「진료받은 사람의 (    )」이 빈채로 나간다. */
            'claimant_name'    => \App\Models\Patient::실명($pt?->name ?: $rx?->patient_name_ocr),
            'claimant_phone'   => PhoneNo::format($pt?->mobile),
            'relation'         => '본인',
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
        $때 = self::쓸동의($order)?->updated_at;

        return $때 ? Carbon::parse($때) : null;
    }

    /**
     * 이 건에 쓸 서명 기록 — 없으면 null.
     *
     * **이 처방전의 것만 찾지 않는다** (2026-09-26 지시). 서명은 한 번 받으면
     * 위임기간(기본 5년) 안에서 다시 쓴다. 문(DelegationGate)이 지난 서명으로
     * 지나보내는데 이 서식이 이 건의 동의만 찾으면, 서명란이 빈 청구서가 나가
     * 그 자리에서 반려된다. 잣대는 한 곳에 둔다.
     */
    private static function 쓸동의(Order $order): ?PrescriptionConsent
    {
        $rx = $order->prescription ?: ($order->prescription_id
            ? \App\Models\Prescription::find($order->prescription_id)
            : null);

        return $rx ? \App\Support\DelegationGate::쓸서명($rx) : null;
    }

    /** 위임동의에서 받아 둔 서명을 그대로 얹는다 — 없으면 비워 둔다 */
    private static function signature(\setasign\Fpdi\Tcpdf\Fpdi $pdf, Order $order, array $cfg): void
    {
        $sig = self::쓸동의($order)?->signature_data;

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
