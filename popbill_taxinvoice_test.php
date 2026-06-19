<?php
// 팝빌 세금계산서 즉시발행 테스트
require __DIR__ . '/vendor/autoload.php';

$app    = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Linkhub\Popbill\PopbillTaxinvoice;
use Linkhub\Popbill\Taxinvoice;
use Linkhub\Popbill\TaxinvoiceDetail;

$linkId    = config('popbill.LinkID');
$secretKey = config('popbill.SecretKey');
$isTest    = (bool) config('popbill.IsTest', true);
$corpNum   = config('popbill.test.corp_num');   // 4908601851
$userId    = config('popbill.test.user_id');    // LINKTHELAB

echo "=== 팝빌 세금계산서 즉시발행 테스트 ===\n";
echo "LinkID  : {$linkId}\n";
echo "IsTest  : " . ($isTest ? 'true' : 'false') . "\n";
echo "CorpNum : {$corpNum}\n";
echo "UserID  : {$userId}\n\n";

$api = new PopbillTaxinvoice($linkId, $secretKey);
$api->IsTest($isTest);
$api->IPRestrictOnOff(false);
$api->UseStaticIP(false);
$api->UseLocalTimeYN(true);

// 1. 잔여 포인트
echo "--- 잔여 포인트 ---\n";
try {
    $balance = $api->GetBalance($corpNum);
    echo "  {$balance} 포인트\n\n";
} catch (Exception $e) {
    echo "  조회 실패: " . $e->getMessage() . "\n\n";
}

// 2. 세금계산서 객체 구성
$mgtKey = 'TEST-' . now()->format('YmdHis');   // 관리번호 (중복 불가)

$invoice = new Taxinvoice();

// 작성일자·유형
$invoice->writeDate       = now()->format('Ymd');
$invoice->taxType         = 'ValueAdded';   // 과세
$invoice->issueType       = 'Normal';       // 정발행
$invoice->purposeType     = 'Receipt';      // 영수

// 공급자 (발행자) — 현재 팝빌 계정의 사업자 정보
$invoice->invoicerCorpNum     = $corpNum;
$invoice->invoicerMgtKey      = $mgtKey;
$invoice->invoicerCorpName    = '링크더랩';
$invoice->invoicerCEOName     = '대표자명';
$invoice->invoicerAddr        = '서울시 강남구';
$invoice->invoicerBizType     = '서비스';
$invoice->invoicerBizClass    = '소프트웨어';
$invoice->invoicerContactName = '담당자';
$invoice->invoicerTEL         = '0212345678';
$invoice->invoicerEmail       = 'test@linkthelab.kr';

// 공급받는자 (수신자) — 테스트용 동일 사업자
$invoice->invoiceeType         = 'IND';           // LGT=법인, IND=개인
$invoice->invoiceeCorpNum      = $corpNum;        // 테스트: 동일 사업자
$invoice->invoiceeCorpName     = '테스트거래처';
$invoice->invoiceeCEOName      = '수신대표자';
$invoice->invoiceeAddr         = '서울시 서초구';
$invoice->invoiceeBizType      = '제조';
$invoice->invoiceeBizClass     = '전자';
$invoice->invoiceeContactName1 = '수신담당자';
$invoice->invoiceeTEL1         = '0212345679';
$invoice->invoiceeEmail1       = 'receive@test.kr';

// 금액 (공급가액 10,000 / 세액 1,000 / 합계 11,000)
$invoice->supplyCostTotal = '10000';
$invoice->taxTotal        = '1000';
$invoice->totalAmount     = '11000';
$invoice->remark1         = '팝빌 세금계산서 테스트 발행';

// 품목
$detail              = new TaxinvoiceDetail();
$detail->serialNum   = '1';
$detail->purchaseDT  = now()->format('Ymd');
$detail->itemName    = '테스트상품';
$detail->spec        = '규격없음';
$detail->qty         = '1';
$detail->unitCost    = '10000';
$detail->supplyCost  = '10000';
$detail->tax         = '1000';
$detail->remark      = '';

$invoice->detailList = [$detail];

// 3. 즉시발행
echo "--- 세금계산서 즉시발행 ---\n";
echo "  관리번호: {$mgtKey}\n";
try {
    $result = $api->RegistIssue($corpNum, $invoice, $userId);
    echo "  발행 성공!\n";
    echo "  code    : {$result->code}\n";
    echo "  message : {$result->message}\n";
    if (isset($result->ntsConfirmNum)) {
        echo "  국세청승인번호: {$result->ntsConfirmNum}\n";
    }
    echo "\n";

    // 4. 상태 확인
    echo "--- 발행 상태 확인 ---\n";
    sleep(1);
    try {
        $info = $api->GetInfo($corpNum, 'SELL', $mgtKey);
        echo "  상태코드  : {$info->stateCode}\n";
        echo "  상태메시지: {$info->stateDT}\n";
        if (!empty($info->ntsConfirmNum ?? null)) {
            echo "  국세청승인: {$info->ntsConfirmNum}\n";  // @phpstan-ignore-line
        }
    } catch (Exception $e) {
        echo "  상태 조회 실패: " . $e->getMessage() . "\n";
    }

    // 5. 팝업 URL
    echo "\n--- 세금계산서 팝업 URL ---\n";
    try {
        $url = $api->GetPopUpURL($corpNum, 'SELL', $mgtKey, $userId);
        echo "  {$url}\n";
    } catch (Exception $e) {
        echo "  URL 조회 실패: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "  발행 실패: " . $e->getMessage() . "\n";
}

echo "\n=== 완료 ===\n";
