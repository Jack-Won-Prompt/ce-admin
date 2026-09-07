<?php
/**
 * 앱 아이콘을 icon/ 의 원본 한 장에서 전부 다시 뽑는다.
 *
 * 원본은 둘레에 흰 여백을 두르고 있다. 그대로 줄이면 아이콘이 한 겹 작아 보이므로
 * 그림 자리만 잘라 꽉 채운다. 둥근 모서리 바깥의 흰 자리는 아이콘 바탕색으로
 * 메운다 — 안드로이드가 제 모양대로 깎을 때 흰 조각이 삐져나오지 않게.
 *
 * 안드로이드 8 부터는 적응형 아이콘을 쓴다. 앞판은 그림을 안전 영역(108 중 72)에
 * 담고, 뒤판은 같은 바탕색을 깐다 — 색이 같아 앞판 네모의 경계가 보이지 않는다.
 */
$srcPath = 'icon/ceadmin_app_icon_coloplast.png';

// 앞서 재어 둔 그림 자리와 바탕색
[$cx, $cy, $cs] = [24, 24, 1870];
$bg = [3, 12, 30];

$src = imagecreatefrompng($srcPath);

/** 흰 여백을 걷고 모서리를 바탕색으로 메운 정사각 그림. 모든 크기의 어미가 된다. */
function flatSquare($src, int $cx, int $cy, int $cs, array $bg, int $size): \GdImage
{
    $im   = imagecreatetruecolor($size, $size);
    $fill = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($im, 0, 0, $size, $size, $fill);
    imagealphablending($im, true);
    imagecopyresampled($im, $src, 0, 0, $cx, $cy, $size, $size, $cs, $cs);

    // 둥근 모서리 바깥의 흰 자리
    $r = (int) round($size * 0.24);
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (! (($x < $r || $x >= $size - $r) && ($y < $r || $y >= $size - $r))) continue;
            $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
            if ($c['red'] > 200 && $c['green'] > 200 && $c['blue'] > 200) {
                imagesetpixel($im, $x, $y, $fill);
            }
        }
    }
    return $im;
}

function save(\GdImage $im, string $path): void
{
    if (! is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
    imagepng($im, $path, 9);
}

$made = [];

// ── ① 안드로이드 런처 아이콘 (밀도별) ──────────────────
$densities = ['mdpi' => 48, 'hdpi' => 72, 'xhdpi' => 96, 'xxhdpi' => 144, 'xxxhdpi' => 192];
foreach ($densities as $d => $px) {
    $p = "android/app/src/main/res/mipmap-$d/ic_launcher.png";
    save(flatSquare($src, $cx, $cy, $cs, $bg, $px), $p);
    $made[] = $p;
}

// ── ② 적응형 아이콘 앞판 (108dp 판에 72dp 안전 영역) ───
foreach ($densities as $d => $px) {
    $canvas = (int) round($px * 108 / 48);        // mdpi 48 → 108
    $art    = (int) round($canvas * 72 / 108);    // 안전 영역

    $fgIm = imagecreatetruecolor($canvas, $canvas);
    imagealphablending($fgIm, false);
    imagesavealpha($fgIm, true);
    imagefilledrectangle($fgIm, 0, 0, $canvas, $canvas,
        imagecolorallocatealpha($fgIm, 0, 0, 0, 127));
    imagealphablending($fgIm, true);

    $a = flatSquare($src, $cx, $cy, $cs, $bg, $art);
    imagecopy($fgIm, $a, (int) (($canvas - $art) / 2), (int) (($canvas - $art) / 2), 0, 0, $art, $art);

    $p = "android/app/src/main/res/mipmap-$d/ic_launcher_foreground.png";
    save($fgIm, $p);
    $made[] = $p;
}

// ── ③ iOS 아이콘 (있는 크기를 그대로 다시 그린다) ──────
foreach (glob('ios/Runner/Assets.xcassets/AppIcon.appiconset/*.png') as $p) {
    $s = getimagesize($p);
    save(flatSquare($src, $cx, $cy, $cs, $bg, $s[0]), $p);
    $made[] = $p;
}

// ── ④ 웹 아이콘 ────────────────────────────────────────
foreach (array_merge(glob('web/icons/*.png'), glob('web/*.png')) as $p) {
    $s = getimagesize($p);
    save(flatSquare($src, $cx, $cy, $cs, $bg, $s[0]), $p);
    $made[] = $p;
}

// ── ⑤ 플레이 스토어 아이콘 ─────────────────────────────
$p = 'playstore/store-icon-512.png';
save(flatSquare($src, $cx, $cy, $cs, $bg, 512), $p);
$made[] = $p;

printf("%d개 파일을 다시 그렸습니다.\n", count($made));
