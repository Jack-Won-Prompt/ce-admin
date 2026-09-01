<?php

namespace App\Support;

/**
 * 전화번호를 사람이 읽는 꼴로 편다.
 *
 * 번호는 여러 길로 들어온다 — 담당자가 손으로 치고, 상담 창이 옮겨 적고, 팝빌ㆍ위드웍스가
 * 붙임표 없는 숫자로 준다. 저장할 때 한 가지 꼴로 맞춰 두어도 예전에 쌓인 것은 그대로라,
 * 목록에서 어떤 줄은 010-1234-5678 이고 어떤 줄은 01012345678 로 보인다.
 *
 * 그래서 「적을 때」가 아니라 「보일 때」 편다. 저장된 값은 건드리지 않는다 —
 * 되돌릴 수 없는 손질을 옛 자료에 하지 않기 위해서다.
 */
class PhoneNo
{
    /**
     * 010-1234-5678 꼴로 편다.
     *
     * 숫자가 아닌 것은 다 떼고 자릿수로 가른다. 우리가 아는 꼴이 아니면 원래 값을
     * 그대로 돌려준다 — 모르는 번호를 억지로 잘라 붙이면 없는 번호가 된다.
     */
    public static function format(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $d = preg_replace('/\D/', '', $raw);

        /* 이미 붙임표가 제자리에 있으면 그대로 둔다 — 02-123-4567 처럼 우리가
           다시 나눴을 때 달라지는 번호가 있다(서울 국번은 세 자리도 네 자리도 된다). */
        if (preg_match('/^\d{2,4}-\d{3,4}-\d{4}$/', $raw)) {
            return $raw;
        }

        return match (true) {
            // 휴대폰ㆍ대표번호 — 010ㆍ011ㆍ070ㆍ050 따위
            strlen($d) === 11 && str_starts_with($d, '0')  => substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7),
            strlen($d) === 10 && str_starts_with($d, '02') => '02-' . substr($d, 2, 4) . '-' . substr($d, 6),
            strlen($d) === 10 && str_starts_with($d, '0')  => substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6),
            strlen($d) === 9  && str_starts_with($d, '02') => '02-' . substr($d, 2, 3) . '-' . substr($d, 5),
            // 1588-7866 같은 여덟 자리 대표번호
            strlen($d) === 8                               => substr($d, 0, 4) . '-' . substr($d, 4),
            default                                        => $raw,
        };
    }
}
