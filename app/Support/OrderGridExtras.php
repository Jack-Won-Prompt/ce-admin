<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Patient;
use App\Models\Prescription;
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
            // 청구처를 아직 고르지 않은 건은 널가 null 이다 — 바로 물으면 PHP 가 나무란다
            'claim_agency'    => ClaimAgency::LABELS[$o?->prescription?->claim_agency ?? ''] ?? '',
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
     * 병원ㆍ처방 정보 탭의 칸을 그대로 목록에 세운다.
     *
     * 그 탭에는 서른아홉 칸이 있는데 목록에는 열여섯만 서 있었다 — 나머지는 한 건씩
     * 열어야 보였다. 무엇을 먼저 처리할지 가리는 일은 목록에서 훑으며 하는 것이라,
     * 열어 보지 않고도 가릴 수 있어야 한다는 요청이다.
     *
     * 「청구처」는 여기 두지 않는다 — 공통 칸(of·ofSample)이 이미 세운다. 같은 값이
     * 두 칸에 서면 어느 쪽이 맞는지 되묻게 된다.
     *
     * 사용 시작일ㆍ급여 종료일 두 가지는 처방전이 아니라 사람에 붙는다(환자의 공단
     * 동의 기간). 그래서 환자를 따로 받는다.
     */
    public function rx(?Prescription $p, ?Patient $pt = null): array
    {
        $d = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '';

        // 0 과 「아직 안 적음」은 다른 말이다 — 숫자 칸은 빈 것을 빈칸으로 둔다
        $n = fn ($v) => ($v === null || $v === '') ? '' : (string) $v;

        return [
            'rx_memo'        => $p?->review_memo ?? '',
            'rx_acc_type'    => match ((string) ($p?->counsel_acc_add_type ?? '')) {
                                    '10' => '처방전', '20' => '처방외', '30' => '처방전 - 원내',
                                    default => '',
                                },
            'rx_hospital'    => $p?->hospital_name ?? '',
            'rx_hosp_code'   => $p?->hospital_code ?? '',
            'rx_diag_date'   => $d($p?->diagnosis_date),
            'rx_dz_grade'    => $p?->disease_grade ?? '',
            'rx_dz_name'     => $p?->disease_name ?? '',
            'rx_uro_date'    => $d($p?->uro_date),
            'rx_uro_find'    => implode(' · ', \App\Support\UroFindings::labels($p?->uro_findings)),
            'rx_purchase'    => $p?->purchase_type ?? '',
            'rx_daily'       => $n($p?->daily_count),
            'rx_days'        => $n($p?->total_days),
            'rx_total'       => $n($p?->total_count),
            'rx_issued'      => $d($p?->issued_date),
            'rx_period'      => $n($p?->rx_use_period),
            'rx_end'         => $d($p?->rx_end_date),
            'rx_specialty'   => $p?->specialty ?? '',
            'rx_doctor'      => $p?->doctor_name ?? '',
            'rx_license'     => $p?->license_no ?? '',
            'rx_reason'      => $p?->reason ?? '',
            'rx_order_mgr'   => $p?->order_manager ?? '',
            'rx_five'        => match ((string) ($p?->five_program ?? '')) {
                                    '05' => 'Five', '06' => 'Six', '00' => 'N/A', default => '',
                                },
            'rx_cath_freq'   => \App\Support\CatheterFrequency::label($p?->diverticulums),
            'rx_five110'     => $p?->five_110days ?? '',
            'rx_benefit'     => $p?->benefit_class ?? '',
            'rx_office'      => $p?->billingOffice?->office_name ?? '',
            'rx_pay_date'    => $d($p?->pay_date),
            'rx_buy_date'    => $d($p?->buy_date),
            'rx_agree_start' => $d($pt?->nhis_agree_start),
            'rx_agree_end'   => $d($pt?->nhis_agree_end),
            'rx_created'     => $p?->created_at?->format('Y-m-d') ?? '',
            'rx_next_repur'  => $d($p?->next_repurchase ?: $p?->repurchase_date),
            'rx_local_gov'   => $p?->local_gov ?? '',
            'rx_repur_date'  => $d($p?->repurchase_date),
            'rx_use_qty'     => $n($p?->daily_use_qty),
            'rx_inmarket'    => $d($p?->inmarket_due),
            'rx_last_qty'    => $n($p?->last_confirmed_qty),
        ];
    }


    /**
     * 위드웍스 판매주문 현황이 세우는 칸 가운데 우리에게 값이 있는 것.
     *
     * 저쪽 화면과 우리 목록의 차례를 맞추자는 요청이다(2026-08-31). 창고ㆍERP 고유
     * 칸(etc SoNoㆍ출고창고ㆍ납품창고 따위 스물하나)은 우리가 만들 수도 받을 수도
     * 없어 세우지 않는다 — 그것은 위드웍스 화면에서 본다.
     *
     * 사람에 붙는 넷(보호자명ㆍ신환master등록일ㆍ소득공제ㆍ현금영수증번호)은 환자를
     * 따로 받는다. 처방전이 아니라 사람의 것이다.
     */
    public function ww(?Order $o, ?Prescription $p, ?Patient $pt, ?OrderReturn $rt = null): array
    {
        $d  = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '';
        $dt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d H:i') : '';

        [$lotNo, $expiry] = $this->lotsOf($o);

        return [
            /* 입고 상태 — 반품에만 있다. 창고가 실물을 받았다고 알려 오면(ro.rcpt_completed)
               그 날을 적어 두므로, 날이 있으면 들어온 것이다. */
            /* 「Withworks 정보」가 곧 판매번호다(요청서 2쪽 회신, 2026-08-31). 예전에는
               번호와 상태를 한 칸에 겹쳐 그렸는데, 그러면 정렬이 번호와 상태가 뒤엉킨
               차례가 되어 쓸모가 없었다 — 요청서 3쪽이 나누라 한 그것이다. */
            'ww_so_no'       => $o?->withworks_so_no ?? '',
            // 창고가 지금 무엇을 하고 있는가 — 주문 목록에만 있던 둘을 네 화면이 함께 쓴다
            'ww_sale_status' => $o?->withworks_status_label ?: '',
            'ww_ship_status' => $o?->withworks_ship_status_label ?: '',
            /* 출고일자 — 창고가 출고를 확정하며 알려 준다. withworks_ship_at 을 쓰지
               않는다. 그것은 우리가 받아 적은 시각이라 웹훅이 늦으면 날이 어긋난다. */
            'ww_ship_date'   => $d($o?->shipped_at),
            'ww_rcpt'       => $rt?->arrived_at ? '입고완료' : '',
            'ww_recipient'  => $o?->shipping_recipient ?? '',
            'ww_due'        => '',                       // 배송요청일자 — 샘플만 있다(화면에서 채운다)
            'ww_ref_no'     => $p?->rx_number ?? '',     // 참조 번호 — 위드웍스에 udf2 로 보내는 그것
            /* 유형ㆍ자격을 고르기 전이면 열쇠가 없다. 그때 resolve 는 「고르면 정해집니다」라는
               안내말을 돌려주는데, 그것은 상세 화면에서 쓸 말이지 표의 한 칸에 설
               것이 아니다 — 서른 줄에 긴 문장이 들어서면 오히려 읽힐 것을 덮는다. */
            'ww_bs_code'    => $bsKey = (\App\Support\BillingStrategy::key(
                                   $p?->counsel_acc_add_type, $p?->benefit_class) ?? ''),
            'ww_bs_name'    => $bsKey === '' ? '' : \App\Support\BillingStrategy::resolve(
                                   $p->counsel_acc_add_type, $p->benefit_class)['label'],
            'ww_ship_no'    => $o?->withworks_ship_no ?? '',
            'ww_remark'     => $p?->admin_note ?? '',
            'ww_tracking'   => $o?->tracking_number ?: ($o?->withworks_tracking_no ?? ''),
            /* 상담 진행 — 상담 창이 쓰는 코드 그대로다(02 등록 · 95 확정 · 99 취소).
               숫자를 그대로 세우면 읽는 사람이 코드표를 외워야 한다. */
            'ww_counsel'    => match ((string) ($p?->counsel_status ?? '')) {
                                   '02' => '등록', '95' => '확정', '99' => '취소',
                                   '10' => '재상담', default => '',
                               },
            'ww_guardian'   => $pt?->guardian_name ?? '',
            'ww_new_master' => $d($pt?->new_patient_date),
            'ww_deduction'  => $pt?->deduction ?? '',
            'ww_cash_no'    => $pt?->cash_receipt_no ?? '',
            /* 요청서 6ㆍ10ㆍ11ㆍ12쪽이 네 쪽에 걸쳐 달라 한 셋. 상담 담당자와 다른
               사람이라 따로 세운다 — 「누구에게 물어야 하나」가 갈려야 한다. */
            'op_manager'    => $o?->operationUser?->name ?? '',
            'op_closed'     => $o?->closing_checked_at ? $d($o->closing_checked_at) : '',
            'op_note'       => $o?->reference_note ?? '',
            'ww_created_at' => $dt($o?->created_at ?? $p?->created_at),
            'ww_updated_at' => $dt($o?->updated_at ?? $p?->updated_at),
            /* Lot 과 유효기간 — 창고가 출고를 확정하며 알려 준다(요청서 2쪽).
               「제품 정보가 든 모든 화면」이라 공통 칸에 둔다. 품목이 여럿이면 쉼표로
               잇는다. 어느 Lot 이 어느 제품의 것인지는 주문 상세에서 본다. */
            'lot_no'        => $lotNo,
            'expiry'        => $expiry,
        ];
    }

    /**
     * 출고한 Lot 과 유효기간을 한 칸씩에 담는다.
     *
     * 관계를 미리 실어 두지 않았으면 묻지 않는다 — 서른 줄에 예순 번을 더 묻느니 빈칸이
     * 낫다. 목록을 만드는 쪽에서 with('items.lots') 를 걸면 값이 선다.
     *
     * @return array{0: string, 1: string} [Lot, 유효기간]
     */
    private function lotsOf(?Order $o): array
    {
        if (!$o || !$o->relationLoaded('items')) {
            return ['', ''];
        }

        $lots = $o->items->flatMap(
            fn ($i) => $i->relationLoaded('lots') ? $i->lots : collect()
        );

        return [
            $lots->pluck('lot_no')->filter()->implode(', '),
            // 차례가 위와 같아야 Lot 과 유효기간의 짝이 읽힌다
            $lots->map(fn ($l) => $l->expiry_date?->format('Y-m-d'))->filter()->implode(', '),
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
