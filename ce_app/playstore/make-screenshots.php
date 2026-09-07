<?php
/**
 * 폰에서 찍은 화면을 플레이 규격에 맞춘다.
 *
 * 찍힌 그대로는 1080x2640(2.44:1)이라 플레이가 받지 않는다 — 상한이 2:1 이다.
 * 화면을 잘라 비율을 맞추면 앱 내용이 사라지므로, 아래쪽 검은 내비게이션 띠만
 * 걷고(앱이 그린 것이 아니다) 좌우를 채워 9:16 으로 만든다. 9:16 은 상한 안에
 * 들면서 스토어가 목록에 크게 보여 주는 비율이기도 하다.
 */
$shots = [
    'KakaoTalk_20260906_164252613_01.jpg' => 'screenshot-1-upload.png',   // 처방전 업로드
    'KakaoTalk_20260906_164252613_02.jpg' => 'screenshot-2-list.png',     // 내 처방전
    'KakaoTalk_20260906_164252613.jpg'    => 'screenshot-3-chat.png',     // 채팅
];

// 화면 머리띠에서 뜬 짙은 남색 — 채우는 자리가 앱과 한 몸으로 보이게 한다
$bg = [10, 26, 49];

foreach ($shots as $src => $dst) {
    $im = imagecreatefromjpeg($src);
    $w  = imagesx($im);
    $h  = imagesy($im);

    // 아래쪽 검은 내비게이션 띠를 찾아 걷는다
    $navTop = $h;
    for ($y = $h - 1; $y > $h - 400; $y--) {
        $dark = 0;
        for ($x = 0; $x < $w; $x += 20) {
            $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
            if ($c['red'] < 60 && $c['green'] < 60 && $c['blue'] < 60) $dark++;
        }
        if ($dark < 45) { $navTop = $y + 1; break; }
    }

    $ch = $navTop;                       // 걷고 남은 높이
    $cw = (int) round($ch * 9 / 16);     // 9:16 이 되는 너비
    if ($cw < $w) $cw = $w;              // 원본보다 좁아지면 잘리므로 그럴 땐 그대로

    $out  = imagecreatetruecolor($cw, $ch);
    $fill = imagecolorallocate($out, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($out, 0, 0, $cw, $ch, $fill);
    imagecopy($out, $im, (int) (($cw - $w) / 2), 0, 0, 0, $w, $ch);

    imagepng($out, $dst, 9);

    $s = getimagesize($dst);
    printf("%-26s %dx%d  비율 %.3f  %.0fKB\n", $dst, $s[0], $s[1], $s[1] / $s[0], filesize($dst) / 1024);
}
