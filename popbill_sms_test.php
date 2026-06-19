<?php
// 팝빌 SMS 발송 테스트 (독립 실행)
require __DIR__ . '/vendor/autoload.php';

$app    = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Linkhub\Popbill\PopbillMessaging;

$linkId    = config('popbill.LinkID');
$secretKey = config('popbill.SecretKey');
$isTest    = (bool) config('popbill.IsTest', true);
$corpNum   = config('popbill.test.corp_num');
$userId    = config('popbill.test.user_id');
$sender    = preg_replace('/\D/', '', config('popbill.test.sender_num', '01057990084'));
$receiver  = '01057990084';

echo "=== 팝빌 SMS 테스트 ===\n";
echo "LinkID  : {$linkId}\n";
echo "IsTest  : " . ($isTest ? 'true' : 'false') . "\n";
echo "CorpNum : {$corpNum}\n";
echo "UserID  : {$userId}\n";
echo "발신번호: {$sender}\n";
echo "수신번호: {$receiver}\n\n";

$api = new PopbillMessaging($linkId, $secretKey);
$api->IsTest($isTest);
$api->IPRestrictOnOff(false);
$api->UseStaticIP(false);
$api->UseLocalTimeYN(true);

// 1. 잔여 포인트 확인
echo "--- 잔여 포인트 ---\n";
try {
    $balance = $api->GetBalance($corpNum);
    echo "  {$balance} 포인트\n\n";
} catch (Exception $e) {
    echo "  조회 실패: " . $e->getMessage() . "\n\n";
}

// 2. 등록 발신번호 목록
echo "--- 등록 발신번호 목록 ---\n";
try {
    $list = $api->GetSenderNumberList($corpNum);
    if (empty($list)) {
        echo "  (등록된 발신번호 없음)\n";
    } else {
        foreach ($list as $item) {
            $state = match((int)($item->state ?? -1)) {
                0 => '대기', 1 => '승인', 2 => '거절', default => "알수없음({$item->state})"
            };
            echo "  번호:{$item->number}  상태:{$state}  대표:{$item->representYN}\n";
        }
    }
} catch (Exception $e) {
    echo "  목록 조회 실패: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. SMS 발송
echo "--- SMS 발송 시도 ---\n";
$content  = '[콜로플라스트] 팝빌 SMS 연동 테스트 메시지입니다.';
$messages = [['rcv' => $receiver, 'rcvnm' => '테스트수신자', 'msg' => $content]];

try {
    $receipt = $api->SendXMS($corpNum, $sender, '', $content, $messages, null, false, null, null, null);
    echo "  발송 성공! 접수번호: {$receipt}\n\n";

    // 4. 발송 결과 확인 (2초 대기)
    echo "--- 발송 결과 확인 ---\n";
    sleep(2);
    try {
        $detail = $api->GetMessages($corpNum, $receipt, $userId);
        if (!empty($detail)) {
            foreach ((array)$detail as $item) {
                if (is_object($item)) {
                    echo "  수신번호:{$item->rcv}  상태:{$item->state}  결과:{$item->result}\n";
                }
            }
        } else {
            echo "  (결과 대기 중)\n";
        }
    } catch (Exception $e) {
        echo "  결과 조회 실패: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "  발송 실패: " . $e->getMessage() . "\n";
}

echo "\n=== 완료 ===\n";
