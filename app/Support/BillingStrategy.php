<?php

namespace App\Support;

/**
 * 청구전략 — 유형 × 자격이 정하는 부담 비율과 세무 발행 방식.
 *
 * 이 표가 금액 계산과 세무 발행의 근간이다(회신 20p, CR-ORI-12). 지금까지 비율은
 * 품목마다 붙은 급여구분(90/50/0%)에서 나왔는데, 그것은 「누가 얼마를 내는가」를
 * 담지 못한다 — 같은 90%라도 공단이 내는 것과 지자체가 내는 것은 이후 절차가
 * 통째로 다르다. 그래서 유형ㆍ자격에서 한 번에 읽는다.
 *
 * 산재ㆍ자동차보험 두 줄은 아직 확정되지 않았다(Q-10ㆍQ-11). 자리만 만들어 두고
 * 「확인중」으로 두되, 확정되면 이 표만 고치면 화면ㆍ계산이 함께 따라온다.
 */
class BillingStrategy
{
    /** 유형 코드 — 화면의 「유형」 select 와 같다 */
    public const TYPE_OUT   = '10';   // 처방전-원외
    public const TYPE_NONRX = '20';   // 처방외
    public const TYPE_IN    = '30';   // 처방전-원내

    /**
     * 유형ㆍ자격으로 청구전략을 읽는다.
     *
     * @return array{label:string, self_rate:int, payer:string, payer_rate:int,
     *               cash_receipt:?int, tax_invoice:?int, pending:bool, note:string}
     */
    public static function resolve(?string $accAddType, ?string $benefitClass): array
    {
        // 처방외는 자격을 보지 않는다 — 처방이 아니니 전액 본인이 낸다
        if ((string) $accAddType === self::TYPE_NONRX) {
            return self::row('처방외 · 처방 아님', 100, '—', 0, 100, 0, false, '');
        }

        if ($accAddType === null || $accAddType === '' || $benefitClass === null || $benefitClass === '') {
            return self::row('유형ㆍ자격을 고르면 청구전략이 정해집니다', 0, '—', 0, null, null, true, '미선택');
        }

        $type = (string) $accAddType === self::TYPE_IN ? '처방전-원내' : '처방전-원외';

        return match ($benefitClass) {
            '일반'       => self::row("{$type} · 일반", 10, '건강보험공단', 90, 10, 90, false, ''),
            '차상위경감' => self::row("{$type} · 차상위경감", 0, '건강보험공단', 100, 0, 100, false, ''),
            '기초'       => self::row("{$type} · 기초", 0, '지자체(시군구청)', 100, 0, 100, false, ''),
            // ── 아래 두 줄은 자리만 ──
            '산재'       => self::row("{$type} · 산재", 100, '—', 0, null, null, true, '부가세 발행 방식 확인중'),
            '자동차보험' => self::row("{$type} · 자동차보험", 0, '—', 0, null, null, true, '비율 확인중'),
            default      => self::row("{$type} · {$benefitClass}", 0, '—', 0, null, null, true, '정해진 전략 없음'),
        };
    }

    /** 확정되지 않아 주문을 진행하면 안 되는 자격인가 */
    public static function isPending(?string $accAddType, ?string $benefitClass): bool
    {
        return self::resolve($accAddType, $benefitClass)['pending'];
    }

    /** 화면(JS)이 같은 표를 쓰도록 통째로 넘긴다 — 두 곳에 적으면 언젠가 어긋난다 */
    public static function table(): array
    {
        $out = [];
        foreach ([self::TYPE_IN, self::TYPE_OUT] as $t) {
            foreach (['일반', '차상위경감', '기초', '산재', '자동차보험'] as $b) {
                $out["{$t}|{$b}"] = self::resolve($t, $b);
            }
        }
        $out[self::TYPE_NONRX . '|'] = self::resolve(self::TYPE_NONRX, null);

        return $out;
    }

    private static function row(string $label, int $self, string $payer, int $payerRate,
                                ?int $cash, ?int $tax, bool $pending, string $note): array
    {
        return [
            'label'        => $label,
            'self_rate'    => $self,
            'payer'        => $payer,
            'payer_rate'   => $payerRate,
            'cash_receipt' => $cash,
            'tax_invoice'  => $tax,
            'pending'      => $pending,
            'note'         => $note,
        ];
    }
}
