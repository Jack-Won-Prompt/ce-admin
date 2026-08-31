<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PrescriptionConsent;
use App\Models\PrivacyConsent;
use App\Models\SampleOrder;
use Illuminate\Support\Collection;

/**
 * 목록 그리드의 공통 칸.
 *
 * 주문 목록ㆍ주문관리ㆍ교환/반품/취소ㆍCE 샘플, 네 화면이 같은 값을 같은 이름으로 같은
 * 자리에 세운다. 화면마다 따로 만들면 「세금계산서」가 어디서는 「발행완료」, 어디서는
 * 「○」가 되고, 담당자는 화면을 옮길 때마다 다시 읽는 법을 배운다.
 *
 * 표기 규칙 — 여부는 말로 적는다(발행완료ㆍ미발행). ○/× 는 인쇄와 엑셀에서 뜻을 잃는다.
 * 없는 값은 빈칸으로 둔다. 「-」를 채우면 정렬이 어지러워지고 엑셀에서 빈칸과 섞인다.
 *
 * 동의 두 가지는 사람에 붙는다(처방전이 아니라). 그래서 줄마다 물으면 서른일곱 줄에
 * 일흔넷을 더 묻는다 — 목록을 만들기 전에 한 번에 모아 두고 그 표에서 꺼내 쓴다.
 */
class OrderGridExtras
{
    /** 사람 id => 개인정보동의 있음 */
    private array $privacy = [];

    /** 사람 id => 위임동의 상태(agreed·pending·expired) */
    private array $consent = [];

    /**
     * 이 목록에 나오는 사람들의 동의를 한 번에 모아 둔다.
     *
     * @param Collection<int, int|null> $patientIds
     */
    public static function forPatients(Collection $patientIds): self
    {
        $self = new self();
        $ids  = $patientIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $self;
        }

        $self->loadPrivacy($ids);

        /* 위임동의는 처방전에 달리고 처방전이 사람에 달린다. 사람마다 「가장 최근 하나」를
           본다 — 동의를 받았으면 그것이 사실이고, 없으면 마지막으로 보낸 것이 어떻게
           됐는지가 궁금하다. */
        $rows = PrescriptionConsent::query()
            ->join('prescriptions', 'prescriptions.id', '=', 'prescription_consents.prescription_id')
            ->whereIn('prescriptions.patient_id', $ids)
            ->orderByDesc('prescription_consents.id')
            ->get(['prescriptions.patient_id', 'prescription_consents.status',
                   'prescription_consents.expires_at']);

        foreach ($rows as $r) {
            $pid = (int) $r->patient_id;

            // 이미 「동의 완료」를 찾았으면 그것이 이긴다 — 뒤에 보낸 것이 만료돼도 사실은 그대로다
            if (($self->consent[$pid] ?? null) === 'agreed') {
                continue;
            }

            $status = $r->status;
            if ($status === 'pending' && $r->expires_at && $r->expires_at->isPast()) {
                $status = 'expired';
            }

            if (!isset($self->consent[$pid]) || $status === 'agreed') {
                $self->consent[$pid] = $status;
            }
        }

        return $self;
    }

    /** 주문 한 줄의 공통 칸 — 네 화면이 같은 열쇠를 쓴다 */
    public function of(?Order $o, ?int $patientId = null): array
    {
        $pid = $patientId ?? $o?->patient_id;

        return [
            'privacy_consent' => $this->privacyLabel($pid),
            'nhis_consent'    => $this->consentLabel($pid),
            'claim_agency'    => ClaimAgency::LABELS[$o?->prescription?->claim_agency] ?? '',
            'claim_ready'     => $o === null ? '' : ($o->claim_ready ? '준비' : '미비'),
            'nhis_claim'      => $this->nhisClaimLabel($o),
            'tax_invoice'     => $this->issueLabel($o?->tax_invoice_status),
            'cash_receipt'    => $this->issueLabel($o?->cash_receipt_status),
            'pay_method'      => $o?->pay_method
                                    ? (\App\Models\PaymentLink::METHODS[$o->pay_method] ?? $o->pay_method)
                                    : '',
            'deposit_at'      => $o?->deposit_confirmed_at?->format('Y-m-d') ?? '',
            'total_amount'    => (int) ($o?->total_amount ?? 0),
            'copay'           => (int) ($o?->patient_copay ?? 0),
            'nhis_amount'     => (int) ($o?->nhis_amount ?? 0),
        ];
    }

    /**
     * 샘플 주문의 공통 칸.
     *
     * 샘플은 무상이라 동의ㆍ청구ㆍ본인부담이 없다. 그래도 같은 열쇠를 같은 차례로 준다 —
     * 네 화면의 칸이 어긋나면 담당자가 화면마다 다시 읽는 법을 배워야 한다. 없는 것은
     * 빈칸으로 서고, 그 빈칸 자체가 「샘플에는 없는 일」이라는 말이 된다.
     */
    public function ofSample(SampleOrder $s): array
    {
        return [
            'privacy_consent' => $this->privacyLabel($s->patient_id),
            'nhis_consent'    => $this->consentLabel($s->patient_id),
            'claim_agency'    => '',
            'claim_ready'     => '',
            'nhis_claim'      => '',
            'tax_invoice'     => '',
            'cash_receipt'    => '',
            'pay_method'      => '',
            'deposit_at'      => '',
            'total_amount'    => (int) $s->total_amount,
            'copay'           => 0,
            'nhis_amount'     => 0,
        ];
    }

    /**
     * 개인정보동의를 모은다 — 사람 번호로, 그리고 이름ㆍ연락처로.
     *
     * 그 동의서는 밖에서 환자가 직접 적는 폼이라 환자 번호가 비어 있는 것이 대부분이다.
     * 번호로만 찾으면 받아 둔 동의가 거의 모두 「없음」으로 서는데, 그것은 사실이 아니라
     * 우리가 못 이은 것이다. 개인정보동의 팝오버가 쓰는 잣대와 같게 맞춘다
     * (PrivacyConsent::findFor).
     *
     * 전화번호는 적는 사람마다 하이픈이 있고 없고가 달라 숫자만 견준다. 이름 앞의
     * 「(E)」는 우리가 붙인 사업부 표시라 동의서에는 없다.
     */
    private function loadPrivacy(Collection $ids): void
    {
        $people = \App\Models\Patient::whereIn('id', $ids)->get(['id', 'name', 'mobile', 'phone']);
        $rows   = PrivacyConsent::get(['patient_id', 'name', 'phone']);

        $digits = fn ($v) => preg_replace('/\D/', '', (string) $v);
        $bare   = fn ($v) => trim(preg_replace('/^\s*\(E\)\s*/u', '', (string) $v));

        // 이름+번호 => 있음
        $byName = [];
        foreach ($rows as $r) {
            if ($r->patient_id) {
                $this->privacy[(int) $r->patient_id] = true;
            }
            $d = $digits($r->phone);
            if ($r->name && $d !== '') {
                $byName[$bare($r->name) . '|' . $d] = true;
            }
        }

        foreach ($people as $p) {
            if ($this->privacy[$p->id] ?? false) continue;

            foreach ([$p->mobile, $p->phone] as $tel) {
                $d = $digits($tel);
                if ($d !== '' && ($byName[$bare($p->name) . '|' . $d] ?? false)) {
                    $this->privacy[$p->id] = true;
                    break;
                }
            }
        }
    }

    private function privacyLabel(?int $pid): string
    {
        return $pid && ($this->privacy[$pid] ?? false) ? '완료' : '';
    }

    private function consentLabel(?int $pid): string
    {
        return match ($pid ? ($this->consent[$pid] ?? null) : null) {
            'agreed'  => '완료',
            'pending' => '대기',
            'expired' => '만료',
            default   => '',
        };
    }

    /** 발행 여부 — 세금계산서ㆍ현금영수증이 같은 말을 쓴다 */
    private function issueLabel(?string $status): string
    {
        return match ($status) {
            'issued'    => '발행완료',
            'cancelled' => '취소됨',
            'not_issued' => '미발행',
            default     => '',
        };
    }

    private function nhisClaimLabel(?Order $o): string
    {
        return match ($o?->nhis_claim_status) {
            'submitted' => '제출',
            'approved'  => '승인',
            'rejected'  => '반려',
            'cancelled' => '취소',
            'pending'   => '대기',
            default     => '',
        };
    }
}
