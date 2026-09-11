<?php

namespace App\Support;

use App\Models\PrescriptionConsent;

/**
 * 전자서명 한 번으로 채워지는 서류들 (2026-09-10 「서명 동의」 요청).
 *
 * 예전에는 위임장 한 장만 놓고 서명을 받았다. 실제로는 그 서명이 청구서에도
 * 등록신청서에도 같이 들어가는데, 환자는 무엇에 서명하는지 모른 채 한 장만 보았다.
 * 그래서 서명이 닿는 서류를 모두 펼쳐 보이고, 그 뒤에 한 번 서명하게 한다.
 *
 * **그 건에 해당하는 것만 세운다**(2026-09-10 확정). 기초(의료급여)가 아닌 사람에게
 * 요양비 지급청구서를 보여 주면, 내지도 않을 서류에 서명하는 것으로 읽힌다.
 */
final class SignDocs
{
    public const 개인정보   = 'privacy';
    public const 위임장     = 'delegation';
    public const 청구서     = 'aid_claim';
    public const 등록신청서 = 'registration';

    /** 서류 이름 — 화면과 서명 직전 문구가 같은 말을 쓴다 */
    public const 이름 = [
        self::개인정보   => '개인정보 수집·이용 동의서',
        self::위임장     => '요양비 청구 위임장',
        self::청구서     => '요양비 지급청구서',
        self::등록신청서 => '요양비 등록 신청서',
    ];

    /**
     * 이 건에서 확인받을 서류.
     *
     * @return array<int, array{key:string, name:string, how:string, note:string}>
     *         how — inline 은 화면에 그대로 펼치고, pdf 는 서식을 그려 보여 준다
     */
    public static function 목록(PrescriptionConsent $consent, bool $privacyDone = false): array
    {
        $rx = $consent->prescription;
        $rx?->loadMissing('patient');
        $pt = $rx?->patient;

        $목록 = [];

        /* 개인정보 동의서 — 아직 받지 않은 사람에게만. 동의는 사람에게 한 번 받으면
           족하다(서명 화면이 그 영역을 세우는 잣대와 같다). */
        if (! $privacyDone) {
            $목록[] = self::줄(self::개인정보, 'inline',
                '수집·이용 목적과 항목, 제3자 제공을 확인하고 항목마다 동의합니다.');
        }

        /* 위임장 — 우리가 대신 청구하는 건에만 보여 준다.

           지자체 건에는 위임 절차가 아예 없고(2026-08-31 회신), 처방외ㆍ산재ㆍ
           자동차보험은 환자가 보험사ㆍ근로복지공단에 직접 내므로 위임할 일이 없다
           (2026-09-11 바로잡음). 여태는 청구처만 보아, 처방외 건에도 위임장과
           등록신청서를 내밀었다 — 받을 수 없는 동의를 청하는 꼴이었다.

           누가 내는지는 청구전략 표가 안다. 주문 화면의 gateConsent 와 같은 잣대다. */
        $위임필요 = BillingStrategy::needsDelegation(
            $rx?->counsel_acc_add_type, $rx?->benefit_class
        );
        $청구처 = $rx?->claim_agency ?: ClaimAgency::fromBenefitClass($rx?->benefit_class);
        if ($위임필요 && ! in_array($청구처, [ClaimAgency::LOCAL, ClaimAgency::NONE], true)) {
            $목록[] = self::줄(self::위임장, 'pdf',
                '급여비용을 콜로플라스트 코리아가 대신 청구하고 받는 것에 대한 위임입니다.');
        }

        /* 요양비 지급청구서 — 기초(의료급여) 대상자만 낸다(2026-09-01 회신) */
        if (trim((string) ($rx?->benefit_class ?? '')) === '기초') {
            $목록[] = self::줄(self::청구서, 'pdf',
                '얼마를 청구하는지 적어 시군구청에 내는 서류입니다.');
        }

        /* 등록 신청서 — 공단 등록ㆍ재등록을 진행 중인 건만.
           위임이 필요 없는 갈래(처방외ㆍ산재ㆍ자동차보험)는 공단에 낼 일이 없으므로
           이 서류도 보여 주지 않는다(2026-09-11 바로잡음). */
        if ($위임필요
            && in_array((string) ($pt?->nhis_reg_status ?? ''), ['신규 등록 진행중', '재등록 진행중'], true)) {
            $목록[] = self::줄(self::등록신청서, 'pdf',
                '자가도뇨 소모성 재료 급여 대상자로 등록하기 위해 공단에 내는 서류입니다.');
        }

        return $목록;
    }

    /** 서명이 닿는 서류 이름만 — 서명 직전 문구가 읽는다 */
    public static function 이름들(PrescriptionConsent $consent, bool $privacyDone = false): array
    {
        return array_column(self::목록($consent, $privacyDone), 'name');
    }

    /** 이 갈래를 이 건에서 보여 줘도 되는가 — 미리보기 주소가 되묻는 자리다 */
    public static function 열수있나(PrescriptionConsent $consent, string $key): bool
    {
        return in_array($key, array_column(self::목록($consent), 'key'), true);
    }

    private static function 줄(string $key, string $how, string $note): array
    {
        return ['key' => $key, 'name' => self::이름[$key], 'how' => $how, 'note' => $note];
    }
}
