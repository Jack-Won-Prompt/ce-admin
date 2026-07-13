<?php
// scripts/install-tcpdf-fonts.php
// composer post-autoload-dump 훅: 미리 생성해 커밋한 나눔고딕 TCPDF 폰트를
// vendor 폰트 디렉터리로 복사한다. (composer install 은 배포 사용자 권한으로 실행되므로
// vendor 에 쓸 수 있음 → 런타임에는 vendor 쓰기 없이 위임장 PDF 가 동작)

$src = __DIR__ . '/../resources/fonts/tcpdf/';
$dst = __DIR__ . '/../vendor/tecnickcom/tcpdf/fonts/';

if (!is_dir($dst)) {
    // TCPDF 미설치 상태면 조용히 종료
    fwrite(STDERR, "[tcpdf-fonts] TCPDF fonts dir not found — skip.\n");
    exit(0);
}

$files  = ['nanumgothic.php', 'nanumgothic.z', 'nanumgothic.ctg.z'];
$copied = 0;
foreach ($files as $f) {
    if (!is_file($src . $f)) {
        continue;
    }
    if (!is_file($dst . $f) || filesize($dst . $f) !== filesize($src . $f)) {
        if (@copy($src . $f, $dst . $f)) {
            $copied++;
        }
    }
}
echo "[tcpdf-fonts] NanumGothic 폰트 설치 완료 (복사 {$copied}개)\n";
