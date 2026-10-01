<?php

namespace App\Support;

/**
 * Quill 이 낸 글을 담기 전에 걸러낸다 (2026-10-01 지시 — SR 등록ㆍ답변에 Quill 을 쓴다).
 *
 * **왜 거르는가.** 담은 글은 화면에 `innerHTML` 로 선다. 글자를 그대로 내놓으면
 * 꾸밈이 사라지므로 그 길밖에 없는데, 그러면 글 안의 `<script>` 나 `onerror=` 가
 * 같은 화면을 보는 다른 담당자의 브라우저에서 돈다. 적는 사람이 모두 직원이라도
 * 한 사람의 계정이 넘어가면 그것이 다음 사람에게 옮는다.
 *
 * **잣대는 허용 목록이다.** 막을 것을 세는 쪽은 새로운 수법이 나올 때마다 뚫린다.
 * Quill 이 실제로 내놓는 꼴만 통과시키고 나머지는 글자로 떨어뜨린다.
 *
 * 비어 있는지 가릴 때는 `빈가()` 를 쓴다 — `<p><br></p>` 는 사람 눈에 빈 칸인데
 * 글자로 세면 11자다. 그것을 「입력했다」로 받으면 빈 답변이 담긴다.
 */
final class RichText
{
    /**
     * 통과시킬 이름표 — Quill 1.3.7 이 저 도구막대(굵게ㆍ기울임ㆍ밑줄ㆍ목록ㆍ링크ㆍ
     * 그림ㆍ지우기)로 내놓는 것과 붙여넣기로 섞여 드는 흔한 것까지다.
     */
    private const 이름표 = [
        'p', 'br', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ol', 'ul', 'li',
        'a', 'img',
        'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** 남겨 둘 속성 — 그 밖의 것은 모두 떼어 낸다 */
    private const 속성 = ['href', 'src', 'alt', 'title', 'class', 'style', 'width', 'height', 'target', 'rel'];

    /** 담기 전에 거른다 */
    public static function 정리(?string $html): string
    {
        $글 = (string) $html;

        if (trim($글) === '') {
            return '';
        }

        /* 1) 이름표를 허용 목록으로 좁힌다. strip_tags 는 속성을 그대로 남기므로
              이것만으로는 끝나지 않는다 — 아래에서 속성을 다시 훑는다. */
        $글 = strip_tags($글, '<' . implode('><', self::이름표) . '>');

        /* 2) 여는 이름표마다 속성을 다시 짓는다. 허용 목록에 없는 속성(on* 가 모두
              여기서 떨어진다)과 위험한 주소는 담지 않는다. */
        $글 = (string) preg_replace_callback(
            '~<([a-zA-Z][a-zA-Z0-9]*)((?:\s[^<>]*)?)(/?)>~',
            static fn (array $m) => '<' . strtolower($m[1]) . self::속성정리($m[2]) . $m[3] . '>',
            $글
        );

        return trim($글);
    }

    /**
     * 사람 눈에 비어 있는가.
     *
     * Quill 은 빈 칸을 `<p><br></p>` 로 내놓는다. 글자 수로 세면 비지 않았으므로
     * 「내용을 입력해 주십시오」가 지나가고 빈 글이 담긴다.
     */
    public static function 빈가(?string $html): bool
    {
        $글 = (string) $html;

        /* 그림은 글자가 없어도 내용이다 — 그림만 붙인 답변을 빈 것으로 보면 안 된다 */
        if (stripos($글, '<img') !== false) {
            return false;
        }

        $글 = str_ireplace(['&nbsp;', '<br>', '<br/>', '<br />'], ' ', $글);

        return trim(strip_tags($글)) === '';
    }

    /**
     * 글자만 — 목록ㆍ알림처럼 한 줄로 보여 줄 자리에서 쓴다.
     *
     * 문단ㆍ줄바꿈ㆍ목록 자리에는 빈칸을 넣고서 이름표를 걷는다. 그러지 않으면
     * 「첫 줄」과 「둘째 줄」이 「첫 줄둘째 줄」로 붙어 한 낱말처럼 읽힌다.
     */
    public static function 글자만(?string $html, int $길이 = 0): string
    {
        $글 = preg_replace('~<(?:br|/p|/div|/li|/h[1-6]|/tr|/blockquote|/pre)\s*/?>~i', ' ',
            (string) $html);

        $글 = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags($글), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $길이 > 0 ? mb_strimwidth($글, 0, $길이, '…') : $글;
    }

    /** 이름표 하나의 속성을 허용 목록으로 다시 짓는다 */
    private static function 속성정리(string $원본): string
    {
        if (trim($원본) === '') {
            return '';
        }

        preg_match_all('~([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'<>`]+))~',
            $원본, $찾은것, PREG_SET_ORDER);

        $둘것 = [];

        foreach ($찾은것 as $것) {
            $이름 = strtolower($것[1]);
            $값   = $것[2] ?? '';

            if (($값 === '') && isset($것[3]) && $것[3] !== '') { $값 = $것[3]; }
            if (($값 === '') && isset($것[4]) && $것[4] !== '') { $값 = $것[4]; }

            if (! in_array($이름, self::속성, true)) {
                continue;                       // on* ㆍ srcset ㆍ formaction … 전부 여기서 떨어진다
            }

            if (in_array($이름, ['href', 'src'], true) && ! self::주소쓸만한가($값)) {
                continue;
            }

            /* style 은 모양만 받는다. url() ㆍ expression() 은 글을 실어 오는 길이다. */
            if ($이름 === 'style' && preg_match('~(expression|url\s*\(|javascript:)~i', $값)) {
                continue;
            }

            $둘것[] = $이름 . '="' . htmlspecialchars($값, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
        }

        return $둘것 ? ' ' . implode(' ', $둘것) : '';
    }

    /**
     * 이 주소를 그대로 둘 수 있는가.
     *
     * `javascript:` ㆍ `data:text/html` 은 누르는 순간 글이 돈다. 그림은 Quill 이
     * 붙여넣기로 `data:image/…` 를 만들므로 그 갈래만 열어 둔다.
     */
    private static function 주소쓸만한가(string $값): bool
    {
        $값 = trim(html_entity_decode($값, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($값 === '') {
            return false;
        }

        /* 앞에 보이지 않는 글자를 끼워 `java\0script:` 로 지나가는 수법을 막는다 */
        $낱말 = strtolower(preg_replace('/[\x00-\x20]/', '', $값));

        if (preg_match('~^(javascript|vbscript|file):~', $낱말)) {
            return false;
        }

        if (str_starts_with($낱말, 'data:')) {
            return (bool) preg_match('~^data:image/(png|jpe?g|gif|webp|bmp);base64,~', $낱말);
        }

        return true;
    }
}
