<?php
// 팝빌 발신번호 관리 URL 확인
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

$msgSvc = new PopbillMessaging($linkId, $secretKey);
$msgSvc->IsTest($isTest); $msgSvc->IPRestrictOnOff(false);
$msgSvc->UseStaticIP(false); $msgSvc->UseLocalTimeYN(true);

echo "IsTest={$isTest}  CorpNum={$corpNum}  UserId={$userId}\n\n";

echo ">> 발신번호 관리 URL (이 주소에서 등록해야 합니다)\n";
try {
    $url = $msgSvc->GetSenderNumberMgtURL($corpNum, $userId);
    echo "   {$url}\n";
} catch (\Exception $e) {
    echo "   오류: " . $e->getMessage() . "\n";
}
