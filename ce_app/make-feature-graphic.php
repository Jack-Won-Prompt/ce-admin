<?php
/**
 * 플레이 스토어 그래픽 이미지(1024x500)를 아이콘 원본에서 뽑는다.
 * 아이콘이 바뀌면 이 그림도 함께 바뀌어야 한다 — 스토어에서 나란히 보이는 둘이
 * 서로 다른 앱처럼 보이면 안 된다.
 *
 * 아이콘 크기별 파일은 make-icons.php 가 맡는다. 여기서는 띠 한 장만 만든다.
 */
$srcPath = 'icon/ceadmin_app_icon_coloplast.png';
$font    = realpath('../storage/fonts/NanumGothic.ttf');   // GD 는 상대 경로로 폰트를 못 찾는다
$out     = 'playstore/feature-graphic-1024x500.png';

[$cx, $cy, $cs] = [24, 24, 1870];     // 원본에서 흰 여백을 걷은 자리
$top = [3, 12, 30];                   // 아이콘 바탕색
$bot = [7, 40, 58];                   // 아래로 갈수록 살짝 트인 색

$src = imagecreatefrompng($srcPath);

$rowColor = fn (float $t) => [
    (int) round($top[0] + ($bot[0] - $top[0]) * $t),
    (int) round($top[1] + ($bot[1] - $top[1]) * $t),
    (int) round($top[2] + ($bot[2] - $top[2]) * $t),
];

$fg = imagecreatetruecolor(1024, 500);
for ($y = 0; $y < 500; $y++) {
    [$r, $g, $b] = $rowColor($y / 499);
    imageline($fg, 0, $y, 1023, $y, imagecolorallocate($fg, $r, $g, $b));
}

// 아이콘을 왼쪽에 앉힌다
$mark = 260;
$mx   = 90;
$my   = (int) ((500 - $mark) / 2);
imagealphablending($fg, true);
imagecopyresampled($fg, $src, $mx, $my, $cx, $cy, $mark, $mark, $cs, $cs);

/* 원본은 둥근 모서리 바깥이 흰색이다. 그 자리만 띠 바탕색으로 바꾼다 —
   그대로 두면 띠 위에 흰 네모가 앉는다. */
$r = (int) round($mark * 0.24);
for ($y = 0; $y < $mark; $y++) {
    [$rr, $gg, $bb] = $rowColor(($my + $y) / 499);
    $fill = imagecolorallocate($fg, $rr, $gg, $bb);
    for ($x = 0; $x < $mark; $x++) {
        if (! (($x < $r || $x >= $mark - $r) && ($y < $r || $y >= $mark - $r))) continue;
        $c = imagecolorsforindex($fg, imagecolorat($fg, $mx + $x, $my + $y));
        if ($c['red'] > 200 && $c['green'] > 200 && $c['blue'] > 200) {
            imagesetpixel($fg, $mx + $x, $my + $y, $fill);
        }
    }
}

$white = imagecolorallocate($fg, 255, 255, 255);
$cyan  = imagecolorallocate($fg, 132, 229, 229);   // 아이콘 무늬에서 뜬 색

imagettftext($fg, 62, 0, 410, 250, $white, $font, 'CE Admin');
imagettftext($fg, 21, 0, 414, 300, $cyan,  $font, '콜로플라스트 코리아 임직원용 업무 앱');

imagepng($fg, $out, 9);

$s = getimagesize($out);
printf("%-32s %dx%d  %.0fKB\n", $out, $s[0], $s[1], filesize($out) / 1024);
