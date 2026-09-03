<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * 찍어 올린 처방전의 그늘을 걷어 낸다(2026-09-02 요청서 · camscanner 처럼).
 *
 * 휴대폰으로 찍은 종이는 한쪽이 어둡고 종이 바탕이 잿빛으로 나온다. 그대로 공단에
 * 내면 글씨가 묻히고, 담당자가 눈으로 읽어 옮겨 적을 때도 더디다.
 *
 * 하는 일은 셋이다.
 *   ① 회색으로 바꾼다 — 색은 종이에 없다.
 *   ② 둘레의 밝기로 그 자리의 바탕을 재어 나눈다(제산). 한쪽만 어두운 그늘이
 *      이 한 걸음에서 거의 사라진다 — 화면 전체에 같은 문턱을 걸면 어두운 쪽이
 *      통째로 검게 뭉갠다.
 *   ③ 남은 잿빛을 희게, 글씨를 검게 당긴다.
 *
 * Imagick 도 Ghostscript 도 없는 서버라 GD 로만 한다. 그래서 흐림(blur)은 크게 줄인
 * 그림에서 재고 되키운다 — 원본 크기로 상자흐림을 돌리면 한 장에 몇 초가 걸린다.
 *
 * 원본은 건드리지 않는다. 보정이 지나쳤을 때 되돌릴 것이 없으면 다시 받아 오라고
 * 해야 하는데, 환자에게 두 번 청하는 일이다.
 */
final class ScanClean
{
    /** 이보다 크면 줄여서 다룬다 — 넘겨받는 사진이 4000px 을 넘는 일이 흔하다 */
    private const MAX_SIDE = 2400;

    /** 바탕을 재는 창 크기(줄인 그림 기준) — 글씨보다 넉넉히 커야 바탕만 잡힌다 */
    private const BG_SCALE = 24;

    /**
     * 저장해 둔 파일을 그 자리에서 보정한다 — 원본은 옆에 남긴다.
     *
     * 보정본이 본 파일이 되고, 원본은 같은 이름에 `_원본` 을 붙여 둔다. 보정이
     * 지나쳤을 때 되돌릴 것이 없으면 환자에게 다시 청해야 하는데, 두 번 청하는
     * 일이다. 표에 칸을 더하지 않는다 — 이름 규칙으로 찾을 수 있다.
     *
     * @return bool 보정했으면 true. 못 했으면 false 이고 파일은 손대지 않는다.
     */
    public static function applyInPlace(string $disk, string $path, ?string $mime): bool
    {
        if (! self::handles($mime)) {
            return false;
        }

        $fs = \Illuminate\Support\Facades\Storage::disk($disk);

        if (! $fs->exists($path)) {
            return false;
        }

        $bytes = self::clean($fs->path($path));

        if ($bytes === null) {
            return false;
        }

        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $orig = preg_replace('/\.[^.]+$/', '', $path) . '_원본.' . $ext;

        try {
            $fs->copy($path, $orig);
            $fs->put($path, $bytes);
        } catch (\Throwable $e) {
            Log::warning('[스캔 보정] 바꿔 넣지 못했습니다', ['error' => $e->getMessage(), 'path' => $path]);

            return false;
        }

        return true;
    }

    /** 이 꼴만 다룬다 — PDF 는 GD 가 열지 못한다 */
    public static function handles(?string $mime): bool
    {
        return in_array((string) $mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true);
    }

    /**
     * 보정해 JPEG 바이트로 돌려준다 — 못 하면 null(까닭은 로그에 남는다).
     *
     * null 을 받은 자리는 원본만 쓴다. 보정은 있으면 좋은 것이지 없으면 안 되는
     * 것이 아니다 — 한 장 실패했다고 업로드 자체를 막지 않는다.
     */
    public static function clean(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring((string) file_get_contents($path));
            if ($src === false) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            /* 너무 크면 줄인다. 종이 글씨는 2400px 이면 넉넉히 읽히고, 그 위로는
               파일만 커진다. */
            if (max($w, $h) > self::MAX_SIDE) {
                $r  = self::MAX_SIDE / max($w, $h);
                $nw = max(1, (int) round($w * $r));
                $nh = max(1, (int) round($h * $r));
                $tmp = imagescale($src, $nw, $nh, IMG_BICUBIC);
                if ($tmp !== false) {
                    /* 옛 그림은 놓아 준다. imagedestroy 는 PHP 8.0 부터 하는 일이
                       없고 8.5 에서 경고를 내므로 부르지 않는다 — 쓰레기 치우기가
                       알아서 거둔다. */
                    $src = $tmp;
                    $w = $nw; $h = $nh;
                }
            }

            imagefilter($src, IMG_FILTER_GRAYSCALE);

            /* 바탕 그림 — 칸마다 「가장 밝은 값」을 잡는다.

               평균으로 잡았더니 도장이나 검은 띠 같은 넓고 진한 자리가 바탕으로
               읽혀, 그 둘레의 글씨(요양기관명 줄)와 띠 위의 흰 글씨가 함께
               지워졌다. 바탕은 종이다 — 그 칸에서 가장 밝은 것이 종이에 가깝다.

               칸이 작아(1/24) 셈은 금방 끝나고, 되키우는 것은 GD 가 한다. */
            $bw = max(1, (int) round($w / self::BG_SCALE));
            $bh = max(1, (int) round($h / self::BG_SCALE));
            $bg = imagecreatetruecolor($bw, $bh);
            if ($bg === false) {
                return null;
            }

            $cell = (int) self::BG_SCALE;
            for ($by = 0; $by < $bh; $by++) {
                $y0 = $by * $cell;
                $y1 = min($h, $y0 + $cell);
                for ($bx = 0; $bx < $bw; $bx++) {
                    $x0  = $bx * $cell;
                    $x1  = min($w, $x0 + $cell);
                    $max = 0;
                    for ($y = $y0; $y < $y1; $y++) {
                        for ($x = $x0; $x < $x1; $x++) {
                            $v = imagecolorat($src, $x, $y) & 0xFF;
                            if ($v > $max) { $max = $v; }
                        }
                    }
                    imagesetpixel($bg, $bx, $by, imagecolorallocate($bg, $max, $max, $max));
                }
            }
            $bgUp = imagescale($bg, $w, $h, IMG_BICUBIC);
            if ($bgUp === false) {
                return null;
            }

            $out = imagecreatetruecolor($w, $h);

            /* 회색 256 칸을 미리 만들어 둔다 — 점마다 imagecolorallocate 를 부르면
               팔레트가 아닌 트루컬러라도 눈에 띄게 느리다. */
            $gray = [];
            for ($v = 0; $v < 256; $v++) {
                $gray[$v] = imagecolorallocate($out, $v, $v, $v);
            }

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $p = imagecolorat($src,  $x, $y) & 0xFF;
                    $b = imagecolorat($bgUp, $x, $y) & 0xFF;

                    /* 그 자리의 바탕으로 나눈다. 바탕이 어두운 곳(그늘)에서도 글씨와
                       종이의 사이가 그대로 벌어진다. */
                    $v = $b > 0 ? (int) round($p * 255 / $b) : 255;
                    if ($v > 255) { $v = 255; }

                    /* 남은 잿빛을 희게, 글씨를 검게 당긴다. 문턱을 하나로 잘라
                       흑백으로 만들지 않는다 — 도장과 흐린 손글씨가 통째로 사라진다. */
                    if ($v >= 235)      { $v = 255; }
                    elseif ($v <= 110)  { $v = 0; }
                    else                { $v = (int) round(($v - 110) * 255 / 125); }

                    imagesetpixel($out, $x, $y, $gray[$v]);
                }
            }


            ob_start();
            imagejpeg($out, null, 88);
            $bytes = (string) ob_get_clean();

            return $bytes !== '' ? $bytes : null;
        } catch (\Throwable $e) {
            Log::warning('[스캔 보정] 하지 못했습니다', ['error' => $e->getMessage(), 'path' => $path]);

            return null;
        }
    }
}
