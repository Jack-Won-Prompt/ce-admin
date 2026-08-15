<?php

namespace App\Http\Controllers;

use App\Models\DelegationSetting;
use App\Models\Prescription;
use App\Models\PrescriptionConsent;
use App\Support\ResidentNo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
    /** 요양비청구위임내역등록(2225) 입력 지원 */
    public function delegation(Prescription $prescription): View
    {
        DelegationSetting::applyToConfig();

        $prescription->load('patient');
        $consent = PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            ->latest()
            ->first();

        return view('nhis.assist.delegation', [
            'prescription' => $prescription,
            'consent'      => $consent,
            'groups'       => $this->delegationGroups($prescription, $consent),
            'portalUrl'    => 'https://medicare.nhis.or.kr/portal/index.do',
        ]);
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
