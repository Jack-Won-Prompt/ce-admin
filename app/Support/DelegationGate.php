<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionConsent;
use Illuminate\Support\Carbon;

/**
 * 위임 서명이 끝났는가 — 주문 생성ㆍ위드웍스 연계ㆍ결제 안내의 앞문 (2026-09-14 지시).
 *
 * 「위임 서명이 되어야 주문 생성 및 연계 저장, 결제 메시지 전송」.
 *
 * 여태는 처방전에 달린 동의 줄 가운데 `agreed` 가 **하나라도** 있으면 지나갔다.
 * 그런데 같은 표에 신분증만 다시 받는 줄(kind = id_card)도 쌓이고, 그 줄은 사진만
 * 올라와도 `agreed` 가 된다 — 서명이 없는데도 「위임 완료」로 읽혔다. 화면은 또
 * 가장 최근 줄 하나의 상태만 보았다.
 *
 * 이제 한 곳에서 본다: 위임 줄이고, 동의했고, 서명이 남아 있어야 한다.
 * 서명 제출(ConsentController::submit)이 서명 없는 동의를 받지 않으므로
 * 서명이 있다는 것이 곧 서명을 받았다는 뜻이다.
 *
 * ── 서명은 한 번 받으면 위임기간(기본 5년) 안에서 다시 쓴다 (2026-09-26 지시).
 *
 * 여태 이 문은 **이 처방전의** 동의만 보았다. 그래서 5년 위임기간이 이미 적혀 있는
 * 사람에게도 새 건마다 서명을 다시 받게 했다 — 운영에서 세 사람이 그렇게 두 번씩
 * 서명했다. 시스템의 다른 곳은 모두 사람 단위로 보고 있었다:
 *
 *   · 위임장 PDF 는 위임기간을 서명일 ＋5년 −1일로 찍는다 (ConsentController)
 *   · 위임동의 기간은 거래처에 붙는다 (patients.nhis_agree_start / nhis_agree_end)
 *   · 청구 준비는 거래처의 위임 등록일만 본다 (ClaimReadiness)
 *   · 공단 등록 팩스는 신구매ㆍ재등록일 때만 나간다 (NhisFaxAuto) — 재구매는 위임장을
 *     다시 내지 않는다는 뜻이다
 *
 * 그래서 이 문만 처방전 단위로 서 있던 것이 어긋난 자리였다. 이제 이 건의 서명이
 * 없으면 같은 사람의 지난 서명을 찾고, 위임기간이 남아 있으면 지나보낸다.
 * 기간이 지났으면 막는다 — 그때는 다시 받아야 한다.
 *
 * 위임이 필요 없는 건(산재ㆍ자동차보험ㆍ처방외 — 환자가 직접 청구)은 지나간다.
 * 누가 청구하는지는 청구전략 표가 안다(2026-09-03 결정 그대로).
 */
final class DelegationGate
{
    /** 화면이 알아보는 표시 — 이것이 오면 알림 창으로 띄운다 */
    public const CODE = 'delegation_unsigned';

    public const MESSAGE = '요양비 위임 서명이 완료되지 않았습니다. '
        . '주문 생성, 위드웍스 연계, 결제 안내 발송이 진행되지 않습니다. '
        . '화면 위쪽의 「서명 동의」 버튼으로 위임 서명을 받은 뒤 다시 진행해 주십시오.';

    /* 기초(의료급여)는 서명은 받되 위임장이 아니라 요양비 지급청구서에 들어간다
       (2026-09-21 확정). 그 건에 「위임 서명」이라 적으면, 담당자는 보여 주지도
       않는 위임장을 찾는다. */
    public const MESSAGE_NO_FORM = '서명 동의가 완료되지 않았습니다. '
        . '주문 생성, 위드웍스 연계, 결제 안내 발송이 진행되지 않습니다. '
        . '화면 위쪽의 「서명 동의」 버튼으로 서명을 받은 뒤 다시 진행해 주십시오.';

    /** 위임기간(년) — 위임장 설정에서 온다. 공단 최장은 5년이다. */
    public static function 위임년수(): int
    {
        return min(5, max(1, (int) config('delegation.period_years', 5)));
    }

    /** 이 건의 서명을 무엇이라 부를 것인가 — 「요양비 위임 서명」 또는 「서명 동의」 */
    public static function 서명이름(Prescription $prescription): string
    {
        return BillingStrategy::위임장받나(
            $prescription->counsel_acc_add_type,
            $prescription->benefit_class,
            $prescription->claim_agency,
        ) ? '요양비 위임 서명' : '서명 동의';
    }

    /** 이 건에 위임 서명이 필요한가 */
    public static function needed(Prescription $prescription): bool
    {
        return BillingStrategy::needsDelegation(
            $prescription->counsel_acc_add_type,
            $prescription->benefit_class
        );
    }

    /** 서명이 실제로 담긴 동의만 — 신분증만 받는 줄은 서명이 아니다 */
    private static function 서명담긴동의()
    {
        return PrescriptionConsent::query()
            ->where('status', 'agreed')
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'id_card'))
            ->whereNotNull('signature_data')
            ->where('signature_data', '!=', '');
    }

    /** 이 처방전에서 직접 받은 서명 — 없으면 null */
    public static function 이건서명(Prescription $prescription): ?PrescriptionConsent
    {
        if (! $prescription->id) {
            return null;
        }

        return self::서명담긴동의()
            ->where('prescription_id', $prescription->id)
            ->latest('id')
            ->first();
    }

    /**
     * 같은 사람의 지난 서명 가운데 위임기간이 남은 것 — 없으면 null.
     *
     * 이 건의 서명이 없을 때만 뜻이 있다. 기간이 지난 것은 돌려주지 않는다 —
     * 그때는 다시 받아야 하고, 문도 막혀야 한다.
     */
    public static function 지난서명(Prescription $prescription): ?PrescriptionConsent
    {
        if (! $prescription->patient_id) {
            return null;
        }

        $consent = self::서명담긴동의()
            ->whereHas('prescription', fn ($q) => $q
                ->where('patient_id', $prescription->patient_id)
                ->where('id', '!=', (int) $prescription->id))
            ->with('prescription:id,rx_number,patient_id')
            ->orderByDesc('responded_at')
            ->orderByDesc('id')
            ->first();

        if (! $consent) {
            return null;
        }

        $끝 = self::유효기간($consent, $prescription->patient);

        return ($끝 && $끝->gte(now())) ? $consent : null;
    }

    /**
     * 이 건에 쓸 서명 — 이 건의 것이 없으면 기간이 남은 지난 서명.
     *
     * 문(signed)만이 아니라 서명이 들어가는 서류도 이것을 본다 —
     * 요양비 지급청구서(MedicalAidClaimForm), 법정대리인 신분증(공단 팩스).
     * 문만 열고 서류를 그대로 두면 서명란이 빈 청구서가 나가 그 자리에서 반려된다.
     */
    public static function 쓸서명(Prescription $prescription): ?PrescriptionConsent
    {
        return self::이건서명($prescription) ?? self::지난서명($prescription);
    }

    /**
     * 이 서명을 언제까지 쓸 수 있는가 — 알 수 없으면 null.
     *
     * 거래처에 적어 둔 건보위임동의 종료일이 있으면 그것이 기준이다(공단에 등록한
     * 기간이므로 그쪽이 정본이다). 없으면 서명일 ＋위임기간 −1일로 센다 —
     * 위임장 PDF 가 찍는 값과 같은 잣대다.
     */
    public static function 유효기간(PrescriptionConsent $consent, ?Patient $patient = null): ?Carbon
    {
        $patient ??= $consent->prescription?->patient;

        if ($patient?->nhis_agree_end) {
            try {
                return Carbon::parse($patient->nhis_agree_end)->endOfDay();
            } catch (\Throwable) {
                // 값이 날짜가 아니면 아래 서명일 기준으로 센다
            }
        }

        $서명일 = $consent->responded_at ?? $consent->updated_at ?? $consent->created_at;

        if (! $서명일) {
            return null;
        }

        try {
            return Carbon::parse($서명일)->addYears(self::위임년수())->subDay()->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** 이 건이 미성년자의 것인가 — 보호자 신분증을 함께 내야 하는가 */
    public static function 미성년인가(Prescription $prescription): bool
    {
        if ($prescription->id && PrescriptionConsent::where('prescription_id', $prescription->id)
                ->where('is_minor', true)->exists()) {
            return true;
        }

        return (bool) self::지난서명($prescription)?->is_minor;
    }

    /**
     * 이 건에 쓸 법정대리인 신분증 — 없으면 null.
     *
     * 지난 서명을 다시 쓰는 건에는 이 처방전에 딸린 파일이 없다. 그때는 그 서명을
     * 받을 때 함께 올린 것을 쓴다 — 공단은 미성년 건에 보호자 신분증을 요구한다.
     */
    public static function 보호자신분증(Prescription $prescription): ?string
    {
        $path = $prescription->id
            ? PrescriptionConsent::where('prescription_id', $prescription->id)
                ->whereNotNull('guardian_id_path')->latest('id')->value('guardian_id_path')
            : null;

        return $path ?: self::지난서명($prescription)?->guardian_id_path;
    }

    /** 위임 서명을 받았는가 — 지난 서명을 다시 쓰는 건도 받은 것으로 본다 */
    public static function signed(Prescription $prescription): bool
    {
        return self::쓸서명($prescription) !== null;
    }

    /** 막아야 하면 그 말을, 지나가도 되면 null */
    public static function block(Prescription $prescription): ?string
    {
        if (! self::needed($prescription) || self::signed($prescription)) {
            return null;
        }

        return self::서명이름($prescription) === '요양비 위임 서명'
            ? self::MESSAGE
            : self::MESSAGE_NO_FORM;
    }
}
