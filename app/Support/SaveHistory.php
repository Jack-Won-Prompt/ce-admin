<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;

/**
 * 저장 이력 — 한 번 저장을 한 줄로 세우고, 무엇이 무엇으로 바뀌었는지 함께 담는다.
 *
 * 주문 등록의 「저장 이력」 탭이 쓰던 셈을 여기로 옮겼다(2026-09-09). 거래처 관리에도
 * 같은 것이 필요해졌는데, 컨트롤러 안에 있으면 두 벌이 되고 한쪽만 고쳐진다.
 *
 * 무엇을 보여 주고 무엇을 감추는지는 App\Support\ChangeLog 가 정한다 — 주민등록번호
 * 원문처럼 감춰 둔 값은 이력 표에도 서지 않는다.
 */
class SaveHistory
{
    /** 어느 표에서 온 줄인지 화면에 적는 이름 */
    private const 어디 = [
        \App\Models\Prescription::class => '처방전',
        \App\Models\Order::class        => '주문',
        \App\Models\Patient::class      => '거래처',
    ];

    /**
     * @param  array<int, array{0: class-string, 1: int}>  $대상  [[모델, id], …]
     * @return array<int, array<string, mixed>>
     */
    public static function rows(array $대상, int $limit = 500): array
    {
        if (! $대상) {
            return [];
        }

        $activities = Activity::with('causer')
            ->where(function ($q) use ($대상) {
                foreach ($대상 as [$type, $id]) {
                    $q->orWhere(fn ($w) => $w->where('subject_type', $type)->where('subject_id', $id));
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return self::묶어세우기($activities);
    }

    private static function 보임($v): string
    {
        if ($v === null || $v === '') return '';
        if (is_bool($v))  return $v ? 'Y' : 'N';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);

        return (string) $v;
    }

    /* 자동으로 남는 줄의 설명은 'created'ㆍ'updated'ㆍ'deleted' 라는 영어 한 낱말이다.
       표에 그대로 세우면 이 화면에서 이 낱말만 영어라 눈에 걸린다. */
    private static function 설명(?string $d): string
    {
        return match ($d) {
            'created' => '등록',
            'updated' => '수정',
            'deleted' => '삭제',
            /* 지난 줄에 쌓인 옛 말. 이 칸들이 OCR 로 채워지던 시절의 이름이라 읽는
               사람에게 아무것도 알려 주지 않는다 — 지금 쓰는 말로 옮겨 세운다.
               쌓인 것을 고쳐 쓰지는 않는다. 그때 남은 자취는 그대로 둔다. */
            'OCR 필드 수정' => '주문 등록 저장',
            default   => (string) $d,
        };
    }

    /**
     * ── 한 번 저장을 한 줄로 ──────────────────────────────────────
     *
     * 한 번 저장하면 줄이 둘 남는다. 하나는 우리가 손으로 적어 온 것(무슨 일이었는지
     * 말하는 줄)이고, 하나는 바뀐 칸을 담은 자동 줄이다. 그대로 세우면 저장 한 번이
     * 두 줄로 흩어져, 「저장 단위 목록」이라는 말이 무색해진다.
     *
     * 같은 것을 같은 초에 건드린 줄들을 묶어, 바뀐 칸을 담은 줄에 그때 적어 둔 말을
     * 얹는다. 바뀐 칸이 없는 줄들만 있는 묶음(팩스 전송ㆍ접수 안내처럼 저장이 아닌
     * 일)은 각자 제 줄로 남는다 — 서로 다른 일이다.
     */
    private static function 묶어세우기($activities): array
    {
        $묶음 = [];

        foreach ($activities as $a) {
            $열쇠 = $a->subject_type . '#' . $a->subject_id . '@'
                  . ($a->created_at?->format('Y-m-d H:i:s') ?? '');
            $묶음[$열쇠][] = $a;
        }

        $세울줄 = [];

        foreach ($묶음 as $한묶음) {
            $바뀐것 = array_values(array_filter(
                $한묶음,
                fn ($a) => ! empty($a->properties['attributes']) || ! empty($a->properties['old']),
            ));

            /* 바뀐 칸을 담은 줄이 딱 하나면, 나머지 줄의 말을 그 줄에 얹어 한 줄로 만든다 */
            if (count($바뀐것) === 1) {
                $적어둔말 = array_values(array_filter(array_map(
                    fn ($a) => $a->description,
                    array_filter($한묶음, fn ($a) => $a !== $바뀐것[0]),
                )));

                $세울줄[] = [$바뀐것[0], $적어둔말];

                continue;
            }

            foreach ($한묶음 as $a) {
                $세울줄[] = [$a, []];
            }
        }

        $out = [];
        $no  = 0;

        foreach ($세울줄 as [$a, $적어둔말]) {
            $new = (array) ($a->properties['attributes'] ?? []);
            $old = (array) ($a->properties['old'] ?? []);

            /* 바뀐 칸을 모은다. 값이 같으면 바뀐 것이 아니다 — 저장할 때마다 함께
               따라오는 칸이 있어, 이것을 거르지 않으면 「바뀐 것 없는 저장」이 열 칸
               바뀐 것처럼 보인다. */
            $칸들 = [];

            foreach (array_keys($new + $old) as $칸) {
                if (! ChangeLog::남기나($칸)) continue;

                $전 = self::보임($old[$칸] ?? null);
                $후 = self::보임($new[$칸] ?? null);
                if ($전 === $후) continue;

                $칸들[] = [
                    'field'  => ChangeLog::이름($칸),
                    'key'    => $칸,
                    'before' => $전,
                    'after'  => $후,
                ];
            }

            /* 목록에서 훑을 때는 무엇이 바뀌었는지 이름만 보면 된다. 셋까지 적고
               나머지는 수로 접는다 — 여덟 이름을 다 적으면 줄이 옆으로 넘친다. */
            $이름들 = array_column($칸들, 'field');
            $요약   = match (true) {
                count($이름들) === 0 => '',
                count($이름들) <= 3  => implode(', ', $이름들),
                default              => implode(', ', array_slice($이름들, 0, 3))
                                        . ' 외 ' . (count($이름들) - 3),
            };

            $out[] = [
                'no'      => ++$no,
                'at'      => $a->created_at?->format('Y-m-d H:i:s') ?? '',
                'who'     => $a->causer?->name ?? '시스템',
                'where'   => self::어디[$a->subject_type] ?? class_basename((string) $a->subject_type),
                'summary' => $요약,
                'count'   => count($칸들),
                /* 그때 적어 둔 말이 있으면 그것을 세운다 — 「수정」보다 「주문 등록 저장」이
                   무슨 일이었는지 말해 준다. 없으면 자동으로 남은 낱말을 쓴다. */
                'note'    => $적어둔말
                                ? implode(' · ', array_map([self::class, '설명'], $적어둔말))
                                : self::설명($a->description),
                'fields'  => $칸들,
            ];
        }

        return $out;
    }
}
