<?php

namespace App\Support;

/**
 * Figma DS 아이콘 인라인 출력.
 *
 * public/vendor/ds-icons/*.svg 는 Figma 파일에서 그대로 내려받은 것이다.
 * 색만 currentColor 로 바꿔 두었으므로 CSS 의 color 가 그대로 아이콘 색이 된다.
 * (아이콘을 <img> 로 넣으면 hover·active 색을 줄 수 없어 인라인으로 박는다)
 *
 * 이름은 Figma 레이어 이름과 1:1 이다 — home-04, file-edit-02, coin-hand …
 */
final class DsIcon
{
    private const DIR = 'vendor/ds-icons';

    /** @var array<string,string> 요청 단위 캐시 — 같은 아이콘이 여러 번 나와도 파일은 한 번만 읽는다 */
    private static array $cache = [];

    public static function svg(string $name, string $class = 'ds-icon'): string
    {
        $svg = self::load($name);
        if ($svg === null) {
            // 없는 아이콘을 조용히 삼키면 화면에서 빈칸으로만 보여 원인을 찾기 어렵다.
            // 자리는 차지하되 눈에 띄게 비워 둔다.
            return '<span class="' . e($class) . '" data-missing-icon="' . e($name) . '"'
                 . ' style="display:inline-block;width:1em;height:1em;outline:1px dashed currentColor;"></span>';
        }

        // width/height 를 떼어 CSS 가 크기를 정하게 한다(viewBox 는 남긴다)
        $svg = preg_replace('/\s(width|height)="[^"]*"/', '', $svg, 2);

        return preg_replace(
            '/<svg\b/',
            '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"',
            $svg,
            1
        );
    }

    private static function load(string $name): ?string
    {
        // 경로 조작 차단 — 파일명에 쓸 수 있는 문자만 통과시킨다
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name)) {
            return null;
        }
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        $path = public_path(self::DIR . '/' . $name . '.svg');

        return self::$cache[$name] = is_file($path) ? trim(file_get_contents($path)) : null;
    }
}
