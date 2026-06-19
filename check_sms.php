<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Linkhub\Popbill\PopbillMessaging;

$linkId    = config('popbill.LinkID');
$secretKey = config('popbill.SecretKey');
$isTest    = (bool) config('popbill.IsTest', true);
$corpNum   = config('popbill.test.corp_num');
$userId    = config('popbill.test.user_id');
$senderNum = config('popbill.test.sender_num');

$msgSvc = new PopbillMessaging($linkId, $secretKey);
$msgSvc->IsTest($isTest);
$msgSvc->IPRestrictOnOff(false);
$msgSvc->UseStaticIP(false);
$msgSvc->UseLocalTimeYN(true);

echo "IsTest={$isTest}  CorpNum={$corpNum}  UserId={$userId}  SenderNum={$senderNum}\n\n";

echo ">> 등록된 발신번호 목록\n";
try {
    $list = $msgSvc->GetSenderNumberList($corpNum);
    if (empty($list)) {
        echo "   (없음) — 팝빌 테스트 포털에서 발신번호를 등록해야 합니다.\n";
    } else {
        foreach ($list as $item) {
            $num   = is_object($item) ? ($item->Number ?? $item->number ?? json_encode($item)) : $item;
            $state = is_object($item) ? ($item->State ?? '') : '';
            echo "   번호: {$num}  상태: {$state}\n";
        }
    }
} catch (\Exception $e) {
    echo "   오류: " . $e->getMessage() . "\n";
}

echo "\n>> 발신번호 관리 URL (이 링크에서 01057990084 등록)\n";
try {
    $url = $msgSvc->GetSenderNumberMgtURL($corpNum, $userId);
    echo "   {$url}\n";
} catch (\Exception $e) {
    echo "   오류: " . $e->getMessage() . "\n";
}
