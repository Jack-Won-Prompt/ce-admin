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

    /**
     * 처방전-원내. 청구전략에서는 원외와 가르지 않는다.
     *
     * 부담 비율도 발행 방식도 둘이 한 글자도 다르지 않아, 열한 줄 가운데 다섯 쌍이
     * 열쇠쇠만 다른 같은 줄이었다. 표를 둘로 두면 한쪽만 고치는 날이 온다.
     * 원내냐 원외냐는 옆의 「유형」 칸이 따로 말한다.
     *
     * 이미 30 으로 적힌 건이 있어 코드 자체는 남긴다 — key() 가 10 으로 모아
     * 같은 줄을 가리키게 한다.
     */
    public const TYPE_IN    = '30';

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
            return self::row('처방외', 100, '—', 0, 100, 0, false, '');
        }

        if ($accAddType === null || $accAddType === '' || $benefitClass === null || $benefitClass === '') {
            return self::row('유형ㆍ자격을 고르면 청구전략이 정해집니다', 0, '—', 0, null, null, true, '미선택');
        }

        /* 이름에 원내ㆍ원외를 적지 않는다. 부담 비율과 발행 방식은 둘이 같아서,
           전략 목록에 열한 줄이 서고 그 가운데 다섯 쌍이 글자만 다른 같은 줄이었다.
           원내냐 원외냐는 옆의 「유형」 칸이 따로 말한다. */
        $type = '처방전';

        /* 발행 규칙(2026-08-24 확정 · 08-31 다듬음).

           가르는 잣대는 「누가 내는가」다. 기관(공단ㆍ지자체)이 내면 세금계산서로 가고,
           개인이 전액을 내면 현금영수증으로 간다. 그래서 처방외ㆍ자동차보험ㆍ산재 셋이
           현금영수증 100% 로 같다 — 보험사도 근로복지공단도 환자가 따로 받는다.

           본인부담이 섞여 있는 일반(10/90)은 예외다. 그 10% 몫으로 현금영수증을 따로
           내지 않고 세금계산서 90% 하나로 끝낸다. */
        return match ($benefitClass) {
            '일반'       => self::row("{$type} · 일반", 10, '건강보험공단', 90, 0, 90, false, ''),
            '차상위경감' => self::row("{$type} · 차상위경감", 0, '건강보험공단', 100, 0, 100, false, ''),
            '기초'       => self::row("{$type} · 기초", 0, '지자체(시군구청)', 100, 0, 100, false, ''),
            /* 산재도 전액 본인이 낸다 — 근로복지공단에게는 환자가 따로 받는다.
               발행은 현금영수증 100%(2026-08-31 고침) — 전에는 세금계산서 100% 로
               적어 두었는데, 낸 사람이 개인인데 세금계산서를 내고 있었다. */
            '산재'       => self::row("{$type} · 산재", 100, '—', 0, 100, 0, false, ''),
            /* 자동차보험은 전액 본인이 낸다 — 보험사에게는 환자가 따로 받는다.
               그래서 발행은 현금영수증 100% 다(2026-08-31 확정) — 모양은 처방외와 같으나
               이름을 갈라 둔다. 목록에서 자동차보험을 처방외과 가릴 수 있어야 한다. */
            '자동차보험' => self::row("{$type} · 자동차보험", 100, '—', 0, 100, 0, false, ''),
            default      => self::row("{$type} · {$benefitClass}", 0, '—', 0, null, null, true, '정해진 전략 없음'),
        };
    }

    /**
     * 기관이 내는 몫(0~1). 전략이 정해지지 않았거나 비율이 확인중이면 null.
     *
     * null 을 받은 자리는 예전 규칙(품목의 급여 구분 90/50/0%)으로 셈한다 — 전략을
     * 넣기 전에 만든 건의 금액이 저 혼자 달라지면 안 된다.
     */
    public static function payerRate(?string $accAddType, ?string $benefitClass): ?float
    {
        $r = self::resolve($accAddType, $benefitClass);

        return $r['pending'] ? null : $r['payer_rate'] / 100;
    }

    /**
     * 요양비 위임 동의를 받아야 하는 건인가(2026-09-03 확정).
     *
     * 잣대는 「우리가 대신 청구하는가」다. 기관(공단ㆍ지자체)이 내는 몫이 있는 건은
     * 우리가 그 기관에 대신 낸다 — 대신 내려면 위임이 있어야 한다. 처방외ㆍ산재ㆍ
     * 자동차보험은 환자가 보험사ㆍ근로복지공단에 직접 내므로 위임할 일이 없다.
     *
     * 그래서 표를 다시 적지 않고 payer_rate 를 읽는다. 자격이 늘어도 이 판정은
     * 저절로 따라온다 — 누가 내는지만 표에 적으면 된다.
     *
     * 유형ㆍ자격이 아직 없으면 받는 쪽으로 둔다. 무엇을 낼지 모르는 건을 위임 없이
     * 내보냈다가 나중에 공단 건으로 밝혀지면 받을 길이 없다.
     */
    public static function needsDelegation(?string $accAddType, ?string $benefitClass): bool
    {
        $r = self::resolve($accAddType, $benefitClass);

        return $r['pending'] ? true : $r['payer_rate'] > 0;
    }

    /** 건에 적어 두는 열쇠 — 「유형|자격」. 화면ㆍ표가 쓰는 것과 같다. */
    public static function key(?string $accAddType, ?string $benefitClass): ?string
    {
        $t = (string) $accAddType;
        if ($t === self::TYPE_NONRX) {
            return self::TYPE_NONRX . '|';
        }

        // 원내도 원외와 같은 줄이다 — 열쇠를 하나로 모은다
        if ($t === self::TYPE_IN) {
            $t = self::TYPE_OUT;
        }

        return ($t !== '' && (string) $benefitClass !== '') ? $t . '|' . $benefitClass : null;
    }

    /* 적어 두는 칸은 서버마다 있을 수도, 없을 수도 있다(마이그레이션 대기).
       없는 곳에 쓰려 들면 질의가 깨지므로 한 번만 물어 기억해 둔다. */
    private static ?bool $hasColumn = null;

    public static function hasColumn(): bool
    {
        return self::$hasColumn ??= \Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'billing_strategy');
    }

    /** 확정되지 않아 주문을 진행하면 안 되는 자격인가 */
    public static function isPending(?string $accAddType, ?string $benefitClass): bool
    {
        return self::resolve($accAddType, $benefitClass)['pending'];
    }

    /** 자격 목록 — 화면의 「자격」 칸과 전략 목록이 같은 순서를 쓴다 */
    public const CLASSES = ['일반', '차상위경감', '기초', '산재', '자동차보험'];

    /* 고를 수 있는 전략 목록(options)은 두지 않는다. 주문 제품 탭의 전략 고르개를
       걷었고, 유형ㆍ자격은 각자 제자리에서 고른다 — 부르는 곳이 없는 표는 언젠가
       실제와 어긋난 채로 남는다. 이름은 아래 resolve() 가 짓는다. */

    /** 화면(JS)이 같은 표를 쓰도록 통째로 넘긴다 — 두 곳에 적으면 언젠가 어긋난다 */
    public static function table(): array
    {
        $out = [];
        foreach (self::CLASSES as $b) {
            $out[self::TYPE_OUT . "|{$b}"] = self::resolve(self::TYPE_OUT, $b)
                + ['needs_delegation' => self::needsDelegation(self::TYPE_OUT, $b)];
        }
        $out[self::TYPE_NONRX . '|'] = self::resolve(self::TYPE_NONRX, null)
            + ['needs_delegation' => self::needsDelegation(self::TYPE_NONRX, null)];

        return $out;
    }

    /**
     * 그 서류를 내야 하는 주문만 남긴다.
     *
     * 「누가 내는가」로 이미 표(resolve)가 갈라 놓았다 — 기관이 내면 세금계산서로,
     * 개인이 전액을 내면 현금영수증으로 간다. 그 결과를 SQL 에 다시 적으면 두 벌이
     * 되어 한쪽만 고치는 날이 오므로, 여기서 표를 읽어 자격 값을 뽑아 건다.
     *
     * 유형ㆍ자격이 비어 있는 건은 어느 쪽에도 걸리지 않는다. 전략이 「미선택」이라
     * 무엇을 낼지 아직 정해지지 않은 건이고, 그런 건을 발행 대기에 세우면 담당자가
     * 낼 수 없는 것을 붙들고 있게 된다.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Order 질의
     * @param  'cash_receipt'|'tax_invoice'           $document
     */
    public static function targets($query, string $document)
    {
        $classes = [];
        foreach (self::CLASSES as $class) {
            if ((int) (self::resolve(self::TYPE_OUT, $class)[$document] ?? 0) > 0) {
                $classes[] = $class;
            }
        }
        $nonRx = (int) (self::resolve(self::TYPE_NONRX, null)[$document] ?? 0) > 0;

        return $query->whereHas('prescription', function ($p) use ($nonRx, $classes) {
            $p->where(function ($x) use ($nonRx, $classes) {
                if ($nonRx) {
                    $x->orWhere('counsel_acc_add_type', self::TYPE_NONRX);
                }
                if ($classes) {
                    /* 처방외를 뺀 나머지가 자격으로 갈린다. NULL 은 !== 로 걸러지지
                       않으므로 유형이 비어 있는 건도 여기서 함께 빠진다 — 그래도 된다.
                       유형이 없으면 전략도 없다. */
                    $x->orWhere(fn ($y) => $y->where('counsel_acc_add_type', '!=', self::TYPE_NONRX)
                                             ->whereIn('benefit_class', $classes));
                }
                if (!$nonRx && ! $classes) {
                    $x->whereRaw('1 = 0');
                }
            });
        });
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
