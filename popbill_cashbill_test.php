<?php
// 팝빌 현금영수증 발행 테스트
require __DIR__ . '/vendor/autoload.php';

$app    = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Linkhub\Popbill\PopbillCashbill;
use Linkhub\Popbill\Cashbill;

$linkId    = config('popbill.LinkID');
$secretKey = config('popbill.SecretKey');
$isTest    = (bool) config('popbill.IsTest', true);
$corp      = config('popbill.test.corp_num');   // 4908601851
$userId    = config('popbill.test.user_id');    // LINKTHELAB

echo "=== 팝빌 현금영수증 발행 테스트 ===\n";
echo "LinkID  : {$linkId}\n";
echo "IsTest  : " . ($isTest ? 'true' : 'false') . "\n";
echo "CorpNum : {$corp}\n";
echo "UserID  : {$userId}\n\n";

$api = new PopbillCashbill($linkId, $secretKey);
$api->IsTest($isTest);
$api->IPRestrictOnOff(false);
$api->UseStaticIP(false);
$api->UseLocalTimeYN(true);

// 1. 잔여 포인트
echo "--- 잔여 포인트 ---\n";
try {
    $balance = $api->GetBalance($corp);
    echo "  {$balance} 포인트\n\n";
} catch (Exception $e) {
    echo "  조회 실패: " . $e->getMessage() . "\n\n";
}

// 2. 현금영수증 객체 구성
$mgtKey = 'CB' . time();

$cb = new Cashbill();
$cb->mgtKey           = $mgtKey;
$cb->tradeType        = '승인거래';
$cb->tradeUsage       = '소득공제용';
$cb->taxationType     = '과세';
$cb->franchiseCorpNum = $corp;
$cb->franchiseCorpName= '링크더랩';
$cb->franchiseCEOName = '최연아';
$cb->franchiseAddr    = '서울시 강남구';
$cb->franchiseTEL     = '0212345678';
$cb->supplyCost       = '9091';
$cb->tax              = '909';
$cb->serviceFee       = '0';
$cb->totalAmount      = '10000';
$cb->identityNum      = '01057990084';
$cb->customerName     = '테스트고객';
$cb->itemName         = '테스트상품';
$cb->hp               = '01057990084';

echo "--- 현금영수증 발행 ---\n";
echo "  관리번호: {$mgtKey}\n";

try {
    $result = $api->RegistIssue($corp, $cb, $userId);
    echo "  발행 성공!\n";
    echo "  code    : {$result->code}\n";
    echo "  message : {$result->message}\n";
    if (isset($result->ntsConfirmNum)) {
        echo "  국세청승인번호: {$result->ntsConfirmNum}\n";
    }
    echo "\n";

    // 3. 상태 확인
    echo "--- 발행 상태 확인 ---\n";
    sleep(1);
    try {
        $info = $api->GetInfo($corp, $mgtKey);
        echo "  상태코드  : {$info->stateCode}\n";
        echo "  상태메시지: {$info->stateDT}\n";
        if (!empty($info->ntsConfirmNum ?? null)) {
            echo "  국세청승인: {$info->ntsConfirmNum}\n";
        }
    } catch (Exception $e) {
        echo "  상태 조회 실패: " . $e->getMessage() . "\n";
    }

    // 4. 팝업 URL
    echo "\n--- 현금영수증 팝업 URL ---\n";
    try {
        $url = $api->GetPopUpURL($corp, $mgtKey, $userId);
        echo "  {$url}\n";
    } catch (Exception $e) {
        echo "  URL 조회 실패: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "  오류: " . $e->getMessage() . "\n";
}

echo "\n=== 완료 ===\n";
