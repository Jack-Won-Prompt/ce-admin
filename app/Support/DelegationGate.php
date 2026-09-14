<?php

namespace App\Support;

use App\Models\Prescription;
use App\Models\PrescriptionConsent;

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

    /** 이 건에 위임 서명이 필요한가 */
    public static function needed(Prescription $prescription): bool
    {
        return BillingStrategy::needsDelegation(
            $prescription->counsel_acc_add_type,
            $prescription->benefit_class
        );
    }

    /** 이 처방전의 위임 서명을 받았는가 */
    public static function signed(Prescription $prescription): bool
    {
        if (! $prescription->id) {
            return false;
        }

        return PrescriptionConsent::where('prescription_id', $prescription->id)
            ->where('status', 'agreed')
            // 신분증만 받는 줄은 서명이 아니다
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'id_card'))
            ->whereNotNull('signature_data')
            ->where('signature_data', '!=', '')
            ->exists();
    }

    /** 막아야 하면 그 말을, 지나가도 되면 null */
    public static function block(Prescription $prescription): ?string
    {
        if (! self::needed($prescription) || self::signed($prescription)) {
            return null;
        }

        return self::MESSAGE;
    }
}
