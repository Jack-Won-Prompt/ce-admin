<?php
// app/Support/Asset.php
// 정적 자산 URL에 파일 수정시각을 캐시 버스터로 붙인다.
//
// 손으로 ?v=1,2,3… 을 올리던 방식은 파일만 고치고 버전을 깜빡하면 브라우저가 캐시된
// 옛 파일을 계속 써서 "고쳤는데 안 바뀐다"가 된다(실제로 겪음). 수정시각을 쓰면
// 파일을 고치는 순간 URL이 저절로 바뀌므로 그 실수가 구조적으로 사라진다.

namespace App\Support;

class Asset
{
    /** 요청당 filemtime 결과 캐시 (같은 자산이 여러 화면·여러 번 렌더될 수 있다) */
    private static array $cache = [];

    /**
     * public/ 기준 상대 경로를 받아 버전이 붙은 URL을 돌려준다.
     *   Asset::versioned('vendor/wwgrid/wwGrid.css')
     *   → https://host/vendor/wwgrid/wwGrid.css?v=1753900000
     *
     * 파일이 없으면 버전 없이 그대로 돌려준다(배포 누락 시에도 화면이 죽지 않게).
     */
    public static function versioned(string $path): string
    {
        $path = ltrim($path, '/');

        if (!array_key_exists($path, self::$cache)) {
            $full = public_path($path);
            self::$cache[$path] = is_file($full) ? (string) filemtime($full) : null;
        }

        $v = self::$cache[$path];

        return $v === null ? asset($path) : asset($path) . '?v=' . $v;
    }
}
