<?php

namespace App\Http\Controllers;

use App\Models\DelegationSetting;
use App\Models\LocalClaimDispatch;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\PrescriptionConsent;
use App\Models\PrescriptionDocument;
use App\Support\ClaimAgency;
use App\Support\ResidentNo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 공단 입력 지원 화면.
 *
 * 공단 요양비 업무는 요양기관정보마당에 사람이 직접 입력한다. 연동 API 가 없다. 지금은
 * 담당자가 우리 화면과 공단 사이트를 번갈아 보며 값을 옮겨 적는데, 항목이 많아 오입력이 난다.
 *
 * 그래서 공단 화면과 같은 순서로 값을 늘어놓고 항목마다 복사 버튼을 둔다. 담당자는 복사 →
 * 공단 사이트 같은 자리에 붙여넣기만 한다. 공단 사이트를 우리가 건드리지 않으므로 사이트가
 * 개편돼도 복사는 계속 동작하고, 최종 입력·제출 책임은 담당자에게 남는다.
 *
 * 주민번호 뒷자리만 예외다. 화면에 미리 내려보내면 P0-1 을 어기므로, 복사 버튼을 누르는
 * 그 순간에만 서버에서 열고 감사로그를 남긴다(revealRrn).
 */
class NhisAssistController extends Controller
{
    private const PORTAL_URL = 'https://medicare.nhis.or.kr/portal/index.do';

    /**
     * 요양비지급청구서등록(2221) 입력 지원.
     *
     * 위임 등록과 달리 우리가 아직 갖고 있지 않은 값이 섞여 있다. 없는 것을 그럴듯하게 채우면
     * 담당자가 그대로 옮겨 적어 오청구가 되므로, 값이 없는 항목은 「확인 필요」로 세워 두고
     * 무엇을 확인해야 하는지 함께 적는다. 채우는 것은 사람의 몫이다.
     */
    public function claim(Order $order, Request $request): View
    {
        DelegationSetting::applyToConfig();

        $order->load(['patient', 'prescription', 'items']);
        $prescription = $order->prescription;

        // 지자체 청구 건은 공단 서식이 아니라 등기로 간다. 공단 화면을 열면 엉뚱한 곳에
        // 옮겨 적게 되므로 여기서 멈추고 무엇을 해야 하는지 알린다.
        if (($prescription?->claim_agency ?? null) !== null
            && $prescription->claim_agency !== ClaimAgency::NHIS) {
            return view('nhis.assist.claim_local', [
                'order'        => $order,
                'prescription' => $prescription,
                'agency'       => $prescription->claim_agency,
                'agencyLabel'  => ClaimAgency::LABELS[$prescription->claim_agency] ?? $prescription->claim_agency,
                'documents'    => $this->localDocuments($order, $prescription),
                'dispatches'   => $order->localDispatches()->latest('id')->get(),
            ]);
        }

        // 좌우 프레임 — 왼쪽에 이 화면을, 오른쪽에 공단 사이트를 나란히 놓는다.
        // 값을 대신 넣어 주는 것이 아니라, 복사와 붙여넣기를 한 창에서 하게 하는 것이다.
        if ($request->boolean('split')) {
            return view('nhis.assist.claim_split', [
                'order'     => $order,
                'soloUrl'   => route('nhis.assist.claim', $order),
                'portalUrl' => self::PORTAL_URL,
            ]);
        }

        $fields = $this->claimFields($order, $prescription);

        return view('nhis.assist.claim', [
            'order'        => $order,
            'prescription' => $prescription,
            'f'            => $fields,
            'taxRows'      => $this->taxRows($order),
            'documents'    => $this->claimDocuments($order, $prescription),
            'delegated'    => $this->hasDelegation($prescription),
            'missing'      => $this->countMissing([$fields]),
            'storeKey'     => 'nhis-claim:' . $order->order_number,
            'revealUrl'    => $prescription ? route('nhis.assist.rrn', $prescription) : null,
            'splitUrl'     => route('nhis.assist.claim', $order) . '?split=1',
            'portalUrl'    => self::PORTAL_URL,
        ]);
    }

    /**
     * 지자체 등기 발송을 기록한다.
     *
     * 보냈다는 증거가 남아야 나중에 「안 왔다」는 말을 받았을 때 댈 것이 있다. 등기번호와
     * 발송 영수증이 그것이라, 영수증은 파일로 함께 받아 둔다.
     */
    public function storeLocalDispatch(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'registered_no' => 'nullable|string|max:50',
            'sent_date'     => 'required|date',
            'memo'          => 'nullable|string|max:500',
            'receipt'       => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $path = $name = null;
        if ($file = $request->file('receipt')) {
            // 발송 영수증은 개인정보가 실린 서류가 아니라 우편 증빙이라 서류 표에 넣지 않는다
            $path = $file->store('local_claims/' . $order->id);
            $name = $file->getClientOriginalName();
        }

        LocalClaimDispatch::create([
            'order_id'      => $order->id,
            'local_gov'     => $order->prescription?->local_gov,
            'registered_no' => $data['registered_no'] ?? null,
            'sent_date'     => $data['sent_date'],
            'receipt_path'  => $path,
            'receipt_name'  => $name,
            'memo'          => $data['memo'] ?? null,
            'created_by'    => Auth::id(),
        ]);

        // 보냈으면 청구한 것이다. 공단 건과 같은 칸을 쓰되 지자체라는 것은 처방전이 안다.
        $order->update([
            'nhis_claim_status' => 'submitted',
            'nhis_submitted_at' => $data['sent_date'],
        ]);

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log('지자체 등기 발송 기록: ' . ($data['registered_no'] ?: '등기번호 미기재'));

        return back()->with('status', '등기 발송을 기록했습니다.');
    }

    /** 발송 영수증 내려받기 — 저장 경로를 그대로 노출하지 않는다 */
    public function localReceipt(LocalClaimDispatch $dispatch): StreamedResponse
    {
        abort_unless($dispatch->receipt_path && Storage::exists($dispatch->receipt_path), 404);

        return Storage::download($dispatch->receipt_path, $dispatch->receipt_name ?: '발송영수증');
    }

    /** 요양비청구위임내역등록(2225) 입력 지원 */
    public function delegation(Prescription $prescription): View
    {
        DelegationSetting::applyToConfig();

        $prescription->load('patient');
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->first();

        $groups = $this->delegationGroups($prescription, $consent);

        return view('nhis.assist.delegation', [
            'prescription' => $prescription,
            'consent'      => $consent,
            'groups'       => $groups,
            'missing'      => $this->countMissing($groups),
            'storeKey'     => 'nhis-delegation:' . $prescription->rx_number,
            'revealUrl'    => route('nhis.assist.rrn', $prescription),
            'portalUrl'    => self::PORTAL_URL,
        ]);
    }

    /**
     * 공단 2221 화면의 칸마다 값을 하나씩 맞춰 놓는다.
     *
     * 화면이 공단 서식과 같은 자리에 같은 이름으로 칸을 그리므로, 여기서는 칸 이름(키)에
     * 값을 붙이는 일만 한다. 국세청자료는 행이 여럿이라 따로 뺐다(taxRows).
     *
     * 각 칸의 모양
     *   value  붙여넣을 값. null 이면 값이 없다는 뜻이라 붉게 서고 복사가 잠긴다
     *   blank  값이 없을 때 칸에 대신 적을 말
     *   note   칸 아래 작게 붙는 설명 (계산 근거·선택 안내)
     *   warn   복사는 되지만 담당자가 확인해야 하는 것
     *   ask    공단 규격·계산식을 몰라 우리가 값을 만들지 않은 칸
     *   fixed  회사 고정값이라 건마다 바뀌지 않는 칸
     *   reveal 누를 때 서버에서 열어 오는 칸 (주민번호 뒷자리)
     */
    private function claimFields(Order $order, ?Prescription $p): array
    {
        $patient = $order->patient ?? $p?->patient;
        $account = config('delegation.account');

        $rrnMasked = $patient?->resident_no_masked ?? $p?->resident_no_ocr_masked;
        $rrnFront  = preg_match('/^(\d{6})/', (string) $rrnMasked, $m) ? $m[1] : null;

        // 처방총계는 계산이 원칙이나 검수에서 손으로 고친 값이 따로 있다. 어긋나면 알려 준다.
        $rxTotal   = ($p?->daily_count && $p?->total_days) ? $p->daily_count * $p->total_days : null;
        $rxTotWarn = ($rxTotal !== null && $p?->total_count && (int) $p->total_count !== $rxTotal)
            ? "저장된 총계 {$p->total_count} 와 다릅니다 — 어느 쪽이 맞는지 확인하십시오"
            : null;

        // 공단이 계산식을 명시한 두 칸. 손으로 계산하다 틀리는 자리라 반드시 계산해 준다.
        $buyQty   = (int) $order->items->sum('quantity') ?: (int) $order->quantity;
        $days     = (int) ($p?->total_days ?? 0);
        $dailyPay = ($buyQty && $days) ? number_format($buyQty / $days, 1, '.', '') : null;
        $payTotal = ($rxTotal !== null && $buyQty) ? min($rxTotal, $buyQty) : null;

        // 구입금액과 부담금 합이 어긋난 채로 넣으면 공단이 반려한다. 고치지는 못해도 알려는 준다.
        $amount  = (int) $order->total_amount;
        $shares  = (int) $order->nhis_amount + (int) $order->patient_copay;
        $sumWarn = ($amount && $shares && $amount !== $shares)
            ? '본인부담금 + 공단부담금 = ' . number_format($shares) . ' 원으로 구입금액과 다릅니다 — 어느 쪽이 맞는지 확인하십시오'
            : null;

        $phone = $this->digits(config('delegation.provider.phone'));

        return [
            /* 수진자 정보 */
            'kind'      => ['value' => '자가도뇨카테터', 'fixed' => true],
            'rrn_front' => ['value' => $rrnFront],
            'rrn_back'  => ['value' => $rrnMasked ? '●●●●●●●' : null, 'reveal' => (bool) $p,
                            'note' => '누르면 그때 열립니다 · 열람 기록이 남습니다'],
            'name'      => ['value' => $patient?->name ?: $p?->patient_name_ocr],
            'branch'    => ['value' => null, 'copy' => false, 'blank' => '공단이 자동 표시',
                            'note' => '입력하지 않습니다'],
            'temporary' => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '체크 기준 미확인',
                            'note' => '어떤 경우에 체크하는지 확인이 필요합니다 (C-Q-02)'],
            'state'     => ['value' => null, 'copy' => false, 'blank' => '공단이 자동 표시'],

            /* 처방정보 */
            'rx_reg_no'    => ['value' => $p?->registration_no ?: null, 'note' => '전자처방전에 한합니다'],
            'rx_issued'    => ['value' => $this->date($p?->issued_date)],
            'disease_cls'  => ['value' => $p?->disease_class ?: null, 'note' => '공단 목록에서 같은 문구를 고르십시오'],
            'daily_count'  => ['value' => $this->num($p?->daily_count)],
            'total_days'   => ['value' => $this->num($p?->total_days)],
            'rx_total'     => ['value' => $this->num($rxTotal), 'note' => '1일처방개수 × 총처방기간',
                               'warn' => $rxTotWarn],
            'license_no'   => ['value' => $p?->license_no ?: null],
            'doctor_name'  => ['value' => $p?->doctor_name ?: null],
            'hospital'     => ['value' => $p?->hospital_code ?: ($p?->hospital_name ?: null),
                               'note' => ($p?->hospital_code && $p?->hospital_name) ? $p->hospital_name : null],
            'specialist_no' => ['value' => $p?->specialist_no ?: null],
            'specialty'    => ['value' => $p?->specialty ?: null, 'note' => '공단 목록에서 같은 문구를 고르십시오'],
            'disease_code' => ['value' => $p?->disease_code ?: null],

            /* 구입정보 */
            'buy_date'   => ['value' => $this->date($p?->buy_date)],
            'use_start'  => ['value' => null, 'copy' => false, 'blank' => '보관하지 않는 항목',
                             'note' => '처방전을 보고 입력하십시오'],
            'daily_pay'  => ['value' => $dailyPay,
                             'note' => $dailyPay ? "구입수량 {$buyQty} ÷ 총처방기간 {$days}" : null],
            'pay_total'  => ['value' => $this->num($payTotal),
                             'note' => $payTotal !== null ? "처방총계 {$rxTotal} 와 구입수량 {$buyQty} 중 작은 값" : null],
            'biz_no'     => ['value' => $this->digits(config('nhis.institution.biz_no')), 'fixed' => true],
            'biz_name'   => ['value' => config('popbill.company.corp_name') ?: null, 'fixed' => true],
            'buy_amount' => ['value' => $this->num($order->total_amount), 'warn' => $sumWarn],
            'buy_qty'    => ['value' => $this->num($buyQty ?: null)],
            'pay_end'    => ['value' => null, 'copy' => false, 'blank' => '보관하지 않는 항목',
                             'note' => '처방전을 보고 입력하십시오'],
            'pay_days'   => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '계산식 미확인',
                             'note' => '공단 산정 방법 확인 필요 (C-Q-05)'],
            'base_daily' => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '고시 기준표 없음',
                             'note' => '공단 고시 기준금액표가 있어야 합니다 (C-Q-05)'],
            'base_calc'  => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '계산식 미확인',
                             'note' => '기준금액(일) × 실지급일수로 추정되나 확인 전입니다'],
            'copay'      => ['value' => $this->num($order->patient_copay), 'note' => '주문에 저장된 값',
                             'warn' => '공단 자격별 부담 비율표가 아직 없어 자격이 바뀐 건은 다를 수 있습니다'],
            'copay_real' => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '계산식 미확인',
                             'note' => '공단 산정 방법 확인 필요 (C-Q-05)'],
            'nhis_pay'   => ['value' => $this->num($order->nhis_amount), 'note' => '주문에 저장된 값',
                             'warn' => '본인부담금과 같은 제약이 있습니다'],
            'base_amt'   => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '계산식 미확인',
                             'note' => '공단 산정 방법 확인 필요 (C-Q-05)'],

            /* 계좌 정보 */
            'acc_receiver' => ['value' => $account['receiver'] ?? null, 'fixed' => true],
            'acc_bank'     => ['value' => $account['bank'] ?? null, 'fixed' => true],
            'acc_no'       => ['value' => $this->digits($account['number'] ?? null), 'fixed' => true],
            'acc_relation' => ['value' => '기타', 'fixed' => true,
                               'note' => '수령인이 판매업자이므로 기타를 고릅니다'],
            'acc_biz_no'   => ['value' => $this->digits(config('nhis.institution.biz_no')), 'fixed' => true],
            'acc_holder'   => ['value' => $account['holder'] ?? null, 'fixed' => true],
            'acc_protect'  => ['value' => null, 'copy' => false, 'blank' => '체크하지 않습니다', 'fixed' => true],
            'clm_relation' => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '선택 문구 미확인',
                               'note' => '공단 선택 목록 확인 필요 (C-Q-06)'],
            'clm_biz_no'   => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '확인 필요',
                               'note' => '사업자번호와 같은 값인지 확인 필요 (C-Q-06)'],
            'clm_name'     => ['value' => null, 'copy' => false, 'ask' => true, 'blank' => '확인 필요',
                               'note' => '업체명과 같은 값인지 확인 필요 (C-Q-06)'],
            'sms_agree'    => ['value' => 'Y', 'fixed' => true],
            'sms_no1'      => ['value' => $this->phonePart($phone, 0), 'fixed' => true],
            'sms_no2'      => ['value' => $this->phonePart($phone, 1), 'fixed' => true],
            'sms_no3'      => ['value' => $this->phonePart($phone, 2), 'fixed' => true],
            'card_no'      => ['value' => null, 'copy' => false, 'blank' => '카드 결제 건 없음',
                               'note' => '결제는 전부 가상계좌라 카드 승인번호가 생기지 않습니다'],
        ];
    }

    /**
     * 국세청자료 — 발행된 문서만 행이 된다.
     *
     * 발행 정보가 주문 컬럼에 직접 박혀 있어 세금계산서·현금영수증 각 1건이 한계다.
     * 재발행이 생기면 행이 모자라므로 구조를 바꿔야 한다.
     */
    private function taxRows(Order $order): array
    {
        $rows = [];

        if ($order->tax_invoice_no && $order->tax_invoice_status !== 'cancelled') {
            $rows[] = [
                'kind'   => '세금계산서',
                'date'   => $this->date($order->tax_invoice_issued_at),
                'no'     => $order->tax_invoice_no,
                'amount' => $this->num((int) $order->tax_invoice_supply + (int) $order->tax_invoice_vat),
            ];
        }

        if ($order->cash_receipt_no && $order->cash_receipt_status !== 'cancelled') {
            $rows[] = [
                'kind'   => '현금영수증',
                'date'   => $this->date($order->cash_receipt_issued_at),
                'no'     => $order->cash_receipt_no,
                'amount' => $this->num($order->cash_receipt_amount),
            ];
        }

        return $rows;
    }

    /**
     * 첨부해야 할 서류와 우리 보유 현황.
     *
     * 공단은 저장을 마친 뒤 파일을 올린다. 담당자가 내려받아 그대로 올리면 되도록
     * 목록과 내려받기 주소를 함께 준다.
     */
    private function claimDocuments(Order $order, ?Prescription $prescription): array
    {
        $docs = $prescription
            ? PrescriptionDocument::where('prescription_id', $prescription->id)->latest('id')->get()
            : collect();

        $byType = fn (string $type) => $docs->firstWhere('type', $type);

        $rxImage = $prescription?->image_path
            ? route('files.prescription-image', $prescription)
            : null;

        $tax  = $byType('tax_invoice');
        $cash = $byType('cash_receipt');

        return [
            ['name' => '자가도뇨 소모성재료 처방전', 'url' => $rxImage,
             'note' => $rxImage ? null : '처방전 이미지가 없습니다'],
            ['name' => '현금영수증 또는 신용카드 매출전표',
             'url'  => $cash ? route('documents.download', $cash) : null,
             'note' => $cash ? null : ($order->cash_receipt_no ? '발행됐으나 서류가 없습니다' : '발행 내역이 없습니다')],
            ['name' => '세금계산서',
             'url'  => $tax ? route('documents.download', $tax) : null,
             'note' => $tax ? null : ($order->tax_invoice_no ? '발행됐으나 서류가 없습니다' : '발행 내역이 없습니다')],
            ['name' => '거래명세서', 'url' => null,
             'note' => '세금계산서·현금영수증으로 품목·수량·금액이 확인되지 않을 때만 필요합니다 — 만드는 기능이 없습니다'],
        ];
    }

    /**
     * 지자체에 등기로 보낼 서류.
     *
     * 공단과 목록이 다르다. 위임 절차가 없어 위임장이 빠지고, 대신 의료용품구입확인서
     * (지자체용)가 들어간다. 현금영수증도 목록에 없다 — 지자체는 세금계산서로 본다.
     */
    private function localDocuments(Order $order, ?Prescription $prescription): array
    {
        $docs = $prescription
            ? PrescriptionDocument::where('prescription_id', $prescription->id)->latest('id')->get()
            : collect();

        $tax = $docs->firstWhere('type', 'tax_invoice');

        return [
            ['name' => '처방전',
             'url'  => $prescription?->image_path ? route('files.prescription-image', $prescription) : null,
             'note' => $prescription?->image_path ? null : '처방전 이미지가 없습니다'],
            ['name' => '거래명세서', 'url' => null,
             'note' => '만드는 기능이 없습니다 — 따로 준비하십시오'],
            ['name' => '전자세금계산서 (주민등록번호)',
             'url'  => $tax ? route('documents.download', $tax) : null,
             'note' => $tax ? null : ($order->tax_invoice_no ? '발행됐으나 서류가 없습니다' : '발행 내역이 없습니다')],
            ['name' => '의료용품구입확인서 (지자체용)', 'url' => null,
             'note' => '서식을 아직 받지 못해 만들지 못합니다'],
        ];
    }

    /** 위임 등록을 마쳤는가 — 안 했으면 청구가 반려된다 */
    private function hasDelegation(?Prescription $prescription): bool
    {
        return (bool) $prescription?->patient?->nhis_agree_start;
    }

    private function digits(?string $v): ?string
    {
        $d = preg_replace('/\D/', '', (string) $v);

        return $d !== '' ? $d : null;
    }

    private function num($v): ?string
    {
        return ($v === null || $v === '' || (int) $v === 0) ? null : (string) (int) $v;
    }

    private function date($v): ?string
    {
        if (!$v) {
            return null;
        }

        return $v instanceof \DateTimeInterface
            ? $v->format('Y-m-d')
            : (\Carbon\Carbon::parse($v)->format('Y-m-d') ?: null);
    }

    /** 값이 없어 복사할 수 없는 항목 수 — 담당자가 채우고 와야 하는 만큼이다 */
    private function countMissing(array $groups): int
    {
        $n = 0;
        foreach ($groups as $rows) {
            foreach ($rows as $r) {
                if (($r['copy'] ?? true) && ($r['value'] ?? null) === null) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /**
     * 주민번호 뒷자리를 그 순간에만 연다.
     *
     * 법정서식 제출을 위한 열람이라 사유 코드가 남는다(P0-1). 앞 6자리는 마스킹에 이미 있어
     * 이 경로를 타지 않는다.
     */
    public function revealRrn(Prescription $prescription): JsonResponse
    {
        $rrn = $prescription->patient?->residentNoFor('nhis_claim_form')
            ?? $prescription->residentNoOcrFor('nhis_claim_form');

        $digits = preg_replace('/\D/', '', (string) $rrn);

        if (strlen($digits) !== 13) {
            return response()->json(['ok' => false, 'message' => '주민등록번호가 저장돼 있지 않습니다.'], 422);
        }

        return response()->json(['ok' => true, 'back' => substr($digits, 6)]);
    }

    /**
     * 공단 2225 화면 순서대로 묶는다.
     *
     * 각 항목은 아래 모양이다.
     *   label  공단 화면에 적힌 이름 (좌우로 대조하며 찾는다)
     *   value  복사될 값. null 이면 값이 없다는 뜻이라 복사 버튼을 잠근다
     *   note   값 대신 보여 줄 안내 (공단에서 직접 고르는 항목 등)
     *   copy   false 면 복사 대상이 아니다
     *   reveal true 면 누를 때 서버에서 값을 받아 온다 (주민번호 뒷자리)
     *   warn   경고 문구 — 복사는 되지만 담당자가 확인해야 한다
     */
    private function delegationGroups(Prescription $prescription, ?PrescriptionConsent $consent): array
    {
        $patient  = $prescription->patient;
        $provider = config('delegation.provider');
        $account  = config('delegation.account');

        $rrnMasked = $patient?->resident_no_masked ?? $prescription->resident_no_ocr_masked;
        $rrnFront  = preg_match('/^(\d{6})/', (string) $rrnMasked, $m) ? $m[1] : null;

        // 미성년이면 보호자가 위임한다 — 수진자와 위임자가 다르다
        $isMinor = (bool) ($consent?->is_minor);
        $sameAsPatient = !$isMinor;

        $phone = preg_replace('/\D/', '', (string) ($patient?->mobile ?? $prescription->mobile_ocr ?? ''));
        $tel   = preg_replace('/\D/', '', (string) ($provider['phone'] ?? ''));

        return [
            '1. 위임자 정보' => [
                ['label' => '수진자 주민등록번호 (앞 6자리)', 'value' => $rrnFront],
                ['label' => '수진자 주민등록번호 (뒤 7자리)', 'value' => $rrnMasked ? '●●●●●●●' : null,
                 'reveal' => true, 'note' => '누르면 그때 열립니다 · 열람 기록이 남습니다'],
                ['label' => '수진자 성명', 'value' => $consent?->patient_name ?: ($patient?->name ?: $prescription->patient_name_ocr)],
                ['label' => '위임자와 수진자 동일인', 'value' => $sameAsPatient ? 'Y' : 'N', 'copy' => false,
                 'note' => $sameAsPatient ? '성년 — 본인이 위임했습니다' : '미성년 — 법정대리인이 위임했습니다'],
                ['label' => '위임자 생년월일',
                 'value' => $sameAsPatient
                     ? ($patient?->birth_date?->format('Y-m-d') ?: ResidentNo::birthDateFromMasked($rrnMasked)?->format('Y-m-d'))
                     : $consent?->guardian_birth_date?->format('Y-m-d')],
                ['label' => '위임자 성명',
                 'value' => $sameAsPatient ? ($patient?->name ?: $prescription->patient_name_ocr) : $consent?->guardian_name],
                ['label' => '수진자와의 관계',
                 'value' => $sameAsPatient ? '본인' : $consent?->guardian_relation,
                 'note'  => '공단 목록에서 같은 문구를 고르십시오'],
                ['label' => 'SMS 수신동의', 'value' => 'Y', 'fixed' => true],
                ['label' => '전화번호 (앞)',   'value' => $this->phonePart($phone, 0)],
                ['label' => '전화번호 (가운데)', 'value' => $this->phonePart($phone, 1)],
                ['label' => '전화번호 (뒤)',   'value' => $this->phonePart($phone, 2)],
            ],

            '2. 위임받는자' => [
                ['label' => '위임기관구분', 'value' => '업체', 'fixed' => true],
                ['label' => '사업자등록번호', 'value' => preg_replace('/\D/', '', (string) ($provider['biz_no'] ?? '')), 'fixed' => true],
                ['label' => '업체명', 'value' => $provider['name'] ?? null, 'fixed' => true],
                ['label' => '위임받는자 관계', 'value' => '기타', 'fixed' => true],
                ['label' => '연락처 (앞)',    'value' => $this->phonePart($tel, 0), 'fixed' => true],
                ['label' => '연락처 (가운데)', 'value' => $this->phonePart($tel, 1), 'fixed' => true],
                ['label' => '연락처 (뒤)',    'value' => $this->phonePart($tel, 2), 'fixed' => true],
            ],

            '3. 수령계좌' => [
                ['label' => '수령인', 'value' => $account['receiver'] ?? null, 'fixed' => true],
                ['label' => '금융기관', 'value' => $account['bank'] ?? null, 'fixed' => true],
                ['label' => '계좌번호', 'value' => preg_replace('/\D/', '', (string) ($account['number'] ?? '')), 'fixed' => true],
                ['label' => '예금주관계', 'value' => '기타', 'fixed' => true],
                ['label' => '예금주 사업자번호', 'value' => preg_replace('/\D/', '', (string) config('nhis.institution.biz_no')), 'fixed' => true],
                ['label' => '예금주명', 'value' => $account['holder'] ?? null, 'fixed' => true],
                ['label' => '압류방지통장', 'value' => '미체크', 'copy' => false, 'fixed' => true],
            ],

            '4. 위임사항' => [
                ['label' => '자가도뇨 소모성 재료', 'value' => '체크', 'copy' => false, 'fixed' => true,
                 'note' => '13개 항목 중 이것 하나만 체크합니다'],
            ],

            '5. 위임기간' => $this->periodRows($patient, $consent),
        ];
    }

    /**
     * 위임기간 — 최장 5년을 넘기면 공단이 받지 않는다. 복사 전에 알려 준다.
     *
     * 위임기간은 지금 공단에 등록하며 정하는 값이라, 우리 DB 에 아직 없는 것이 정상이다.
     * 그럴 때는 서명일부터 5년을 제안값으로 내려보내고 제안임을 밝힌다 — 담당자가 빈칸을
     * 보고 임의로 채우는 것보다 낫다.
     */
    private function periodRows($patient, ?PrescriptionConsent $consent): array
    {
        $start = $patient?->nhis_agree_start ?: null;
        $end   = $patient?->nhis_agree_end ?: null;
        $note  = null;

        if (!$start && !$end) {
            $base  = $consent?->responded_at ?? now();
            $start = $base->format('Y-m-d');
            $end   = $base->copy()->addYears(5)->subDay()->format('Y-m-d');
            $note  = '저장된 위임기간이 없어 서명일 기준 5년으로 제안합니다';
        }

        $warn = null;
        if ($start && $end) {
            try {
                $limit = \Carbon\Carbon::parse($start)->addYears(5)->subDay();
                if (\Carbon\Carbon::parse($end)->gt($limit)) {
                    $warn = '위임기간이 5년을 넘습니다 — 공단 최장 기간은 5년입니다';
                }
            } catch (\Throwable) {
            }
        }

        return [
            ['label' => '위임 시작일', 'value' => $start, 'note' => $note],
            ['label' => '위임 종료일', 'value' => $end, 'note' => $note, 'warn' => $warn],
        ];
    }

    /** 공단 화면은 전화번호를 세 칸으로 나눠 받는다 */
    private function phonePart(?string $digits, int $index): ?string
    {
        $digits = (string) $digits;
        if ($digits === '') {
            return null;
        }

        // 02 로 시작하는 지역번호는 두 자리다
        $head = str_starts_with($digits, '02') ? 2 : 3;
        $rest = substr($digits, $head);
        $tail = 4;
        $mid  = max(0, strlen($rest) - $tail);

        return match ($index) {
            0 => substr($digits, 0, $head),
            1 => substr($rest, 0, $mid) ?: null,
            2 => substr($rest, $mid) ?: null,
        };
    }
}
