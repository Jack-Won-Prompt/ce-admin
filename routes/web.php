<?php
// routes/web.php
// 어드민 웹 인터페이스 라우트

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\UserActivityLogController;
use App\Http\Controllers\DispatchHistoryController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationSettingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NhisController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PrivacyConsentController;
use App\Http\Controllers\PrivacyConsentAdminController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RepurchaseController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\TossWebhookController;
use App\Http\Controllers\ShopMonitoringController;
use App\Http\Controllers\ShopOrderController;
use App\Http\Controllers\ShopOrderWebhookController;
use App\Http\Controllers\CashbillPageController;
use App\Http\Controllers\FaxPageController;
use App\Http\Controllers\TaxinvoicePageController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminInvitationController;
use App\Http\Controllers\InstitutionalNoticeController;
use App\Http\Controllers\PrescriptionDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — CE Admin
|--------------------------------------------------------------------------
*/

// 루트 & 웰컴 페이지 (비로그인 → welcome, 로그인 → 워크스페이스)
Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) return redirect()->route('workspace');
    return view('welcome');
})->name('welcome');

Route::get('/welcome', function () {
    if (\Illuminate\Support\Facades\Auth::check()) return redirect()->route('workspace');
    return view('welcome');
});

// 인증 미들웨어로 보호
Route::middleware(['auth'])->group(function () {

    // MDI 워크스페이스(메뉴 클릭 시 화면 내 탭으로 열림)
    Route::view('/workspace', 'workspace')->name('workspace');

    // 대시보드
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 관리자 관리
    Route::get('/admin/users',           [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users',          [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}',    [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/invite',              [AdminInvitationController::class, 'store'])->name('admin.users.invite');
    Route::get('/admin/users/invitations',          [AdminInvitationController::class, 'list'])->name('admin.users.invitations');
    Route::post('/admin/users/invitations/{id}/resend', [AdminInvitationController::class, 'resend'])->name('admin.users.invitations.resend');
    Route::delete('/admin/users/invitations/{id}',  [AdminInvitationController::class, 'destroy'])->name('admin.users.invitations.destroy');

    // 처방전 업로드 & 검수
    Route::prefix('prescriptions')->name('prescriptions.')->group(function () {
        Route::get('/',              [PrescriptionController::class, 'index'])->name('index');
        Route::get('/upload',        [PrescriptionController::class, 'uploadPage'])->name('upload');
        Route::post('/',             [PrescriptionController::class, 'store'])->name('store');
        Route::get('/memos/pinned',  [PrescriptionController::class, 'pinnedMemos'])->name('memos.pinned');
        // 검수 화면 '환자 조회' — 이름/연락처 검색 + 선택 환자의 과거 상담이력
        // ('/{prescription}' 보다 먼저 등록해야 고정 경로가 처방번호로 해석되지 않는다)
        // 빈 검수·등록 화면 (메뉴 '처방전 관리') — 초안 1건을 잡아 검수 화면으로 보낸다
        // ('/{prescription}' 보다 먼저 등록해야 'create' 가 처방번호로 해석되지 않는다)
        Route::get('/create',                            [PrescriptionController::class, 'create'])->name('create');
        Route::get('/patient-search',                    [PrescriptionController::class, 'patientSearch'])->name('patientSearch');
        Route::get('/patients/{patient}/counselings',     [PrescriptionController::class, 'patientCounselings'])->name('patientCounselings');
        Route::get('/{prescription}', [PrescriptionController::class, 'show'])->name('show');

        // 주민번호는 쓰기 전용이다. 원문을 되돌려 주던 resident-no 조회는 없앴다 —
        // 화면에서 읽을 방법이 없어야 복호화가 일어날 일도 없다.

        // OCR 미리보기 (임시 저장, DB 저장 없음)
        // OCR 확인 후 최종 업로드
        // AJAX 액션
        Route::post('/{prescription}/ocr',       [PrescriptionController::class, 'updateOcr'])->name('updateOcr');
        // 담당자가 다 적었다는 신호 
        Route::post('/{prescription}/request-review', [PrescriptionController::class, 'requestReview'])->name('request-review');
        Route::post('/{prescription}/approve',       [PrescriptionController::class, 'approve'])->name('approve');
        Route::post('/{prescription}/reject',        [PrescriptionController::class, 'reject'])->name('reject');
        Route::post('/{prescription}/kakao-send',    [PrescriptionController::class, 'sendKakao'])->name('kakaoSend');
        Route::get('/{prescription}/kakao-preview',  [PrescriptionController::class, 'kakaoPreview'])->name('kakaoPreview');
        Route::post('/{prescription}/sms-send',      [PrescriptionController::class, 'sendSms'])->name('smsSend');
        Route::post('/{prescription}/fax-send',      [PrescriptionController::class, 'sendFax'])->name('faxSend');
        Route::get( '/{prescription}/authorization', [PrescriptionController::class, 'authorization'])->name('authorization');
        Route::get( '/{prescription}/fax-pdf',      [PrescriptionController::class, 'downloadFaxPdf'])->name('faxPdf');
        Route::post('/{prescription}/fax-regenerate', [PrescriptionController::class, 'regenerateFax'])->name('faxRegenerate');
        Route::get( '/{prescription}/generated-docs', [PrescriptionController::class, 'generatedDocs'])->name('generatedDocs');
        Route::patch('/{prescription}/assign',       [PrescriptionController::class, 'assignUser'])->name('assign');
        Route::post(  '/{prescription}/withworks-order', [PrescriptionController::class, 'createWithworksOrder'])->name('withworksOrder');
        Route::put(   '/{prescription}/withworks-order', [PrescriptionController::class, 'updateWithworksOrder'])->name('withworksOrderUpdate');
        Route::delete('/{prescription}/withworks-order', [PrescriptionController::class, 'deleteWithworksOrder'])->name('withworksOrderDelete');
        // 메모 CRUD
        Route::post('/{prescription}/consent-sms',    [PrescriptionController::class, 'sendConsentSms'])->name('consentSms');
        Route::get( '/{prescription}/consent-status', [ConsentController::class,     'statusCheck'])->name('consentStatus');
        Route::get( '/{prescription}/consent-pdf',    [ConsentController::class,     'downloadPdf'])->name('consentPdf');
        Route::get( '/{prescription}/consent-signature', [ConsentController::class,  'downloadSignature'])->name('consentSignature');
        Route::get( '/{prescription}/delegation-pdf', [ConsentController::class,     'downloadDelegationPdf'])->name('delegationPdf');
        Route::get( '/{prescription}/delegation-pdf-original', [ConsentController::class, 'downloadDelegationOverlayPdf'])->name('delegationPdfOriginal');
        Route::post('/{prescription}/delegation-regenerate', [ConsentController::class, 'regenerateDelegation'])->name('delegationRegenerate');
        Route::post('/{prescription}/counsel-no',     [PrescriptionController::class, 'generateCounselNo'])->name('counselNo');
        Route::post('/{prescription}/memos',                [PrescriptionController::class, 'storeMemo'])->name('memos.store');
        Route::patch('/{prescription}/memos/{memo}',        [PrescriptionController::class, 'updateMemo'])->name('memos.update');
        Route::delete('/{prescription}/memos/{memo}',       [PrescriptionController::class, 'destroyMemo'])->name('memos.destroy');
        Route::patch('/{prescription}/memos/{memo}/pin',    [PrescriptionController::class, 'toggleMemoPin'])->name('memos.pin');
        // 첨부 파일 추가 / 삭제
        Route::post('/{prescription}/attachments',                [PrescriptionController::class, 'storeAttachment'])->name('attachments.store');
        Route::delete('/{prescription}/attachments/{attachment}', [PrescriptionController::class, 'destroyAttachment'])->name('attachments.destroy');
    });
    // 처방전 이미지·첨부 서류 — 로그인·권한을 확인하고 내보낸다.
    // 예전에는 /storage/prescriptions/... 로 주소만 알면 로그인 없이 열렸다.
    Route::get('/files/prescriptions/{prescription}/image',  [\App\Http\Controllers\SecureFileController::class, 'prescriptionImage'])->name('files.prescription-image');
    Route::get('/files/prescriptions/attachments/{attachment}', [\App\Http\Controllers\SecureFileController::class, 'attachment'])->name('files.prescription-attachment');
    Route::get('/files/prescriptions/temp/{name}',              [\App\Http\Controllers\SecureFileController::class, 'tempImage'])->name('files.prescription-temp');
    Route::get('/files/consents/{consent}/guardian-id',         [\App\Http\Controllers\SecureFileController::class, 'consentGuardianId'])->name('files.consent-guardian-id');

    // 전역 메모 API (레이아웃에서 prescription 컨텍스트 없이 사용)
    Route::patch('/prescriptions/memos/{memo}/pin-global',    [PrescriptionController::class, 'pinMemoGlobal'])->name('prescriptions.memos.pin-global');
    Route::patch('/prescriptions/memos/{memo}/update-global', [PrescriptionController::class, 'updateMemoGlobal'])->name('prescriptions.memos.update-global');
    Route::patch('/prescriptions/memos/{memo}/unpin',         [PrescriptionController::class, 'unpinMemo'])->name('prescriptions.memos.unpin');

    // 재구매 관리
    /* 교환·반품·취소 — 주문을 무르거나 바꾸는 일. 지금까지는 주문 상태만 cancelled 로
       바뀌고 왜·무엇이 오갔는지는 남지 않았다. */
    Route::prefix('order-returns')->name('order-returns.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\OrderReturnController::class, 'index'])->name('index');
        Route::get('/create',          [\App\Http\Controllers\OrderReturnController::class, 'create'])->name('create');
        Route::get('/order-search',    [\App\Http\Controllers\OrderReturnController::class, 'orderSearch'])->name('orderSearch');
        Route::get('/patient-search',  [\App\Http\Controllers\OrderReturnController::class, 'patientSearch'])->name('patientSearch');
        Route::post('/',               [\App\Http\Controllers\OrderReturnController::class, 'store'])->name('store');
        Route::get('/{orderReturn}',   [\App\Http\Controllers\OrderReturnController::class, 'show'])->name('show');
        Route::post('/{orderReturn}/advance', [\App\Http\Controllers\OrderReturnController::class, 'advance'])->name('advance');
        Route::post('/{orderReturn}/resend',  [\App\Http\Controllers\OrderReturnController::class, 'resend'])->name('resend');
    });

    // CE 샘플판매주문 — 목록·상세·신규를 한 화면의 탭으로 둔다
    Route::prefix('sample-orders')->name('sample-orders.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\SampleOrderController::class, 'index'])->name('index');
        Route::post('/',               [\App\Http\Controllers\SampleOrderController::class, 'store'])->name('store');
        Route::get('/customer-search', [\App\Http\Controllers\SampleOrderController::class, 'customerSearch'])->name('customerSearch');
        // 요청자(담당자) 조회 — 「/{sampleOrder}」 보다 앞에 둔다. 뒤에 두면 샘플번호로 읽힌다
        Route::get('/user-search',     [\App\Http\Controllers\SampleOrderController::class, 'userSearch'])->name('userSearch');
        Route::get('/{sampleOrder}',   [\App\Http\Controllers\SampleOrderController::class, 'show'])->name('show');
        Route::post('/{sampleOrder}/resend', [\App\Http\Controllers\SampleOrderController::class, 'resend'])->name('resend');
    });

    Route::get('/repurchase',      [RepurchaseController::class, 'index'])->name('repurchase.index');
    Route::get('/repurchase/day',  [RepurchaseController::class, 'dayItems'])->name('repurchase.day');

    // 환자 관리
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/',              [PatientController::class, 'index'])->name('index');
        Route::post('/',             [PatientController::class, 'store'])->name('store');
        Route::get('/{patient}/histories', [PatientController::class, 'histories'])->name('histories');
        // 통화 내용을 그 자리에서 적어 둔다(거래처 관리 › 상담내역 › 상담하기)
        Route::post('/{patient}/counsels', [PatientController::class, 'storeCounsel'])->name('counsels.store');
        // 상담을 어느 주문에 이을지 고르는 자리 — 처방으로 산 것과 처방 없이 산 것이 함께 온다
        Route::get('/{patient}/orders',     [PatientController::class, 'orders'])->name('orders');
        Route::get('/{patient}',     [PatientController::class, 'show'])->name('show');
        Route::put('/{patient}',     [PatientController::class, 'update'])->name('update');
        Route::delete('/{patient}',  [PatientController::class, 'destroy'])->name('destroy');
    });

    // 이어 둔 주문 고치기 — 상담 한 건의 연결만 바꾼다
    Route::patch('/counsels/{prescription}/order', [PatientController::class, 'updateCounselOrder'])->name('counsels.order');

    // 제품 검색 / 재고 조회 (Demoworks API 프록시)
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/stock',  [ProductController::class, 'stock'])->name('products.stock');

    // 정산/회계
    Route::get('/settlement',                                        [SettlementController::class, 'index'])->name('settlement.index');
    Route::get('/settlement/prescriptions/{prescription}',           [SettlementController::class, 'prescriptionDetail'])->name('settlement.prescription-detail');
    Route::get('/settlement/orders/{order}/detail',                  [SettlementController::class, 'orderDetail'])->name('settlement.order-detail');
    Route::post('/settlement/orders/{order}/virtual-account',        [SettlementController::class, 'issueVirtualAccount'])->name('settlement.issue-va');
    Route::get('/settlement/orders/{order}/payment-status',          [SettlementController::class, 'checkPaymentStatus'])->name('settlement.check-status');

    /* 결제 전송 — 만들어 보내고, 무엇을 보냈는지 보고, 잘못 보낸 것은 닫는다.
       환자가 여는 결제 페이지는 로그인 밖에 따로 둔다(아래 pay.*). */
    Route::post('/orders/{order}/payment-links',    [\App\Http\Controllers\PaymentLinkController::class, 'store'])->name('payment-links.store');
    Route::get( '/orders/{order}/payment-links',    [\App\Http\Controllers\PaymentLinkController::class, 'index'])->name('payment-links.index');
    Route::post('/payment-links/{paymentLink}/cancel', [\App\Http\Controllers\PaymentLinkController::class, 'cancel'])->name('payment-links.cancel');
    Route::post('/settlement/orders/{order}/resend-va-sms',          [SettlementController::class, 'resendVirtualAccountSms'])->name('settlement.resend-va-sms');

    // 발송/발행 내역 관리
    Route::get('/dispatch',              [DispatchHistoryController::class, 'index'])->name('dispatch.index');
    Route::get('/dispatch/{type}/{id}',  [DispatchHistoryController::class, 'show'])->name('dispatch.show');

    // 팩스 발송
    Route::get('/fax', [FaxPageController::class, 'index'])->name('fax.index');

    // 현금영수증 발행
    Route::get('/cashbill', [CashbillPageController::class, 'index'])->name('cashbill.index');

    // 전자세금계산서 발행
    Route::get('/taxinvoice', [TaxinvoicePageController::class, 'index'])->name('taxinvoice.index');

    // 계산서 발행 관리
    Route::get('/invoice', [InvoiceController::class, 'index'])->name('invoice.index');

    // NHIS 청구 관리
    Route::prefix('nhis')->name('nhis.')->group(function () {
        // 공단 청구는 사이트에 직접 입력·업로드한다. 팩스로 보내던 경로는 걷어냈다.
        Route::get('/',                          [NhisController::class, 'index'])->name('index');

        /* 공단 사이트에 옮겨 적는 것을 돕는 화면. 값을 늘어놓고 항목마다 복사 버튼을 둔다.
           공단 사이트에 자동으로 넣어 주지 않는다 — 최종 입력·제출은 담당자가 한다. */
        Route::get('/assist/claim/{order}',
            [\App\Http\Controllers\NhisAssistController::class, 'claim'])->name('assist.claim');
        // 지자체는 등기로 보낸다. 보냈다는 증거(등기번호·영수증)를 여기 남긴다.
        Route::post('/assist/claim/{order}/local-dispatch',
            [\App\Http\Controllers\NhisAssistController::class, 'storeLocalDispatch'])->name('assist.localDispatch');
        Route::get('/assist/local-receipt/{dispatch}',
            [\App\Http\Controllers\NhisAssistController::class, 'localReceipt'])->name('assist.localReceipt');
        Route::get('/assist/delegation/{prescription}',
            [\App\Http\Controllers\NhisAssistController::class, 'delegation'])->name('assist.delegation');
        Route::post('/assist/delegation/{prescription}/rrn',
            [\App\Http\Controllers\NhisAssistController::class, 'revealRrn'])->name('assist.rrn');
        Route::post('/{order}/record-result',    [NhisController::class, 'recordResult'])->name('recordResult');
        Route::get('/{order}/preview',           [NhisController::class, 'previewDocument'])->name('preview');
    });

    // 기관 공지사항 (MOHW / HIRA / NHIS)
    Route::prefix('institutional-notices')->name('institutional-notices.')->group(function () {
        Route::get('/',            [InstitutionalNoticeController::class, 'index'])->name('index');
        Route::get('/list',        [InstitutionalNoticeController::class, 'list'])->name('list');
        Route::get('/crawl',       [InstitutionalNoticeController::class, 'crawl'])->name('crawl');
        Route::get('/check-today', [InstitutionalNoticeController::class, 'checkToday'])->name('checkToday');
        Route::get('/{id}',        [InstitutionalNoticeController::class, 'show'])->name('show');
    });

    // 공지사항 패널 API (AJAX — 순서 중요: prefix 보다 앞에)
    Route::get('/panel/notices',             [NoticeController::class,  'panelList'])->name('panel.notices.list');
    Route::get('/panel/notices/{notice}',    [NoticeController::class,  'panelShow'])->name('panel.notices.show');
    Route::get('/panel/inquiries',                          [InquiryController::class, 'panelList'])->name('panel.inquiries.list');
    Route::get('/panel/inquiries/{inquiry}',                [InquiryController::class, 'panelShow'])->name('panel.inquiries.show');
    Route::post('/panel/inquiries',                         [InquiryController::class, 'panelStore'])->name('panel.inquiries.store');
    Route::post('/panel/inquiries/{inquiry}/messages',      [InquiryController::class, 'panelAddMessage'])->name('panel.inquiries.addMessage');
    Route::delete('/panel/inquiries/{inquiry}',             [InquiryController::class, 'panelDestroy'])->name('panel.inquiries.destroy');

    // 공지사항
    Route::prefix('notices')->name('notices.')->group(function () {
        Route::get('/',              [NoticeController::class, 'index'])->name('index');
        Route::get('/create',        [NoticeController::class, 'create'])->name('create');
        Route::post('/',             [NoticeController::class, 'store'])->name('store');
        Route::get('/{notice}',      [NoticeController::class, 'show'])->name('show');
        Route::get('/{notice}/edit', [NoticeController::class, 'edit'])->name('edit');
        Route::put('/{notice}',      [NoticeController::class, 'update'])->name('update');
        Route::delete('/{notice}',   [NoticeController::class, 'destroy'])->name('destroy');
    });

    // 문의하기
    Route::prefix('inquiries')->name('inquiries.')->group(function () {
        Route::get('/',                    [InquiryController::class, 'index'])->name('index');
        Route::get('/create',              [InquiryController::class, 'create'])->name('create');
        Route::post('/',                   [InquiryController::class, 'store'])->name('store');
        Route::get('/{inquiry}',           [InquiryController::class, 'show'])->name('show');
        Route::post('/{inquiry}/reply',    [InquiryController::class, 'reply'])->name('reply');
        Route::delete('/{inquiry}',        [InquiryController::class, 'destroy'])->name('destroy');
    });

    // 사용자 활동 로그 (관리자 전용 — admin@ce-admin.co.kr)
    Route::get('/user-logs', [UserActivityLogController::class, 'index'])->name('user-logs.index');

    // 투어 완료 플래그 저장 (사용자별 DB 저장)
    Route::post('/tour/done', function (\Illuminate\Http\Request $r) {
        $page = trim($r->input('page', ''));
        if ($page && strlen($page) <= 120) {
            $r->user()->markPageTouredIfNew($page);
        }
        return response()->json(['ok' => true]);
    })->name('tour.done');

    // SR(Service Request) — 상단 패널 + 사이드바 전용 화면 공용
    Route::prefix('sr')->name('sr.')->group(function () {
        Route::get(   '/',                 [\App\Http\Controllers\ServiceRequestController::class, 'index'])->name('index');
        Route::get(   '/list',             [\App\Http\Controllers\ServiceRequestController::class, 'list'])->name('list');
        Route::post(  '/',                 [\App\Http\Controllers\ServiceRequestController::class, 'store'])->name('store');
        Route::put(   '/{serviceRequest}/answer', [\App\Http\Controllers\ServiceRequestController::class, 'answer'])->name('answer');
        Route::put(   '/{serviceRequest}/status', [\App\Http\Controllers\ServiceRequestController::class, 'updateStatus'])->name('status');
        Route::delete('/{serviceRequest}',  [\App\Http\Controllers\ServiceRequestController::class, 'destroy'])->name('destroy');
    });

    // 채팅
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/rooms',                         [ChatController::class, 'rooms'])->name('rooms');
        Route::post('/rooms',                        [ChatController::class, 'createRoom'])->name('createRoom');
        Route::get('/rooms/{room}/messages',         [ChatController::class, 'messages'])->name('messages');
        Route::post('/rooms/{room}/messages',        [ChatController::class, 'sendMessage'])->name('sendMessage');
        Route::post('/rooms/{room}/read',            [ChatController::class, 'markRead'])->name('markRead');
        // 수정·삭제는 방이 아니라 메시지에 건다 — 본인 것만 허용하는 판단이 메시지에 있다.
        Route::put('/messages/{message}',            [ChatController::class, 'updateMessage'])->name('updateMessage');
        Route::delete('/messages/{message}',         [ChatController::class, 'deleteMessage'])->name('deleteMessage');
    });

    // CE샵 모니터링
    Route::get('shop-monitoring', [ShopMonitoringController::class, 'index'])->name('shop-monitoring.index');

    // CE샵 주문 관리
    Route::prefix('shop-orders')->name('shop-orders.')->group(function () {
        Route::get('/',                          [ShopOrderController::class, 'index'])->name('index');
        Route::get('/{shopOrder}',               [ShopOrderController::class, 'show'])->name('show');
        Route::post('/{shopOrder}/status',       [ShopOrderController::class, 'updateStatus'])->name('updateStatus');
        Route::post('/{shopOrder}/memo',         [ShopOrderController::class, 'updateMemo'])->name('updateMemo');
    });

    // 주문 관리
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/',                         [OrderController::class, 'index'])->name('index');
        Route::get('/{order}',                  [OrderController::class, 'show'])->name('show');
        Route::post(  '/',         [OrderController::class, 'store'])  ->name('store');
        Route::put(   '/{order}',  [OrderController::class, 'update']) ->name('update');
        Route::delete('/{order}',  [OrderController::class, 'destroy'])->name('destroy');
        Route::post('/{order}/status',          [OrderController::class, 'updateStatus'])->name('updateStatus');
        Route::post('/{order}/tracking',        [OrderController::class, 'updateTracking'])->name('updateTracking');
        Route::post('/{order}/tax-invoice',     [OrderController::class, 'issueTaxInvoice'])->name('issueTaxInvoice');
        Route::delete('/{order}/tax-invoice',   [OrderController::class, 'cancelTaxInvoice'])->name('cancelTaxInvoice');
        Route::post('/{order}/cash-receipt',        [OrderController::class, 'issueCashReceipt'])->name('issueCashReceipt');
        Route::delete('/{order}/cash-receipt',      [OrderController::class, 'cancelCashReceipt'])->name('cancelCashReceipt');
        Route::get('/{order}/cash-receipt-pdf',     [OrderController::class, 'downloadCashReceiptPdf'])->name('cashReceiptPdf');
        Route::post('/{order}/withworks-status',   [OrderController::class, 'fetchWithworksStatus'])->name('fetchWithworksStatus');
    });

    // 서류 관리
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/',                                   [PrescriptionDocumentController::class, 'index'])->name('index');
        // 서류 등록 탭: 처방전별 전체 서류 조회 / 파일 업로드 등록
        Route::get('/by-prescription/{prescription}',     [PrescriptionDocumentController::class, 'byPrescription'])->name('byPrescription');
        Route::post('/',                                  [PrescriptionDocumentController::class, 'store'])->name('store');
        Route::get('/{document}/download',                [PrescriptionDocumentController::class, 'download'])->name('download');
        Route::get('/{document}/preview',                 [PrescriptionDocumentController::class, 'preview'])->name('preview');
    });

    // 요양비 위임장 설정
    /* 위드웍스 연동 — 테스트(데모웍스)·운영(위드웍스) 전환과 주소·토큰·거래처를 화면에서
       관리한다. 예전에는 .env 라 서버에 들어가야 바꿀 수 있었다. */
    Route::get('/settings/withworks',       [\App\Http\Controllers\WithworksSettingController::class, 'edit'])->name('withworks-settings.edit');
    Route::put('/settings/withworks',       [\App\Http\Controllers\WithworksSettingController::class, 'update'])->name('withworks-settings.update');
    Route::post('/settings/withworks/test', [\App\Http\Controllers\WithworksSettingController::class, 'test'])->name('withworks-settings.test');

    Route::get('/settings/delegation',  [DelegationSettingController::class, 'edit'])->name('delegation-settings.edit');
    Route::put('/settings/delegation',  [DelegationSettingController::class, 'update'])->name('delegation-settings.update');

    // 처방전 OCR 공급자 설정
    Route::get('/settings/ocr',  [\App\Http\Controllers\OcrSettingController::class, 'edit'])->name('ocr-settings.edit');

    // 권한 그룹 (관리자 전용 — CheckPagePermission 이 admin_only 로 차단)
    Route::get(   '/settings/permission-groups',                  [\App\Http\Controllers\PermissionGroupController::class, 'index'])->name('permission-groups.index');
    Route::get(   '/settings/permission-groups/{group}',          [\App\Http\Controllers\PermissionGroupController::class, 'show'])->name('permission-groups.show');
    Route::post(  '/settings/permission-groups',                  [\App\Http\Controllers\PermissionGroupController::class, 'store'])->name('permission-groups.store');
    Route::put(   '/settings/permission-groups/{group}',          [\App\Http\Controllers\PermissionGroupController::class, 'update'])->name('permission-groups.update');
    Route::delete('/settings/permission-groups/{group}',          [\App\Http\Controllers\PermissionGroupController::class, 'destroy'])->name('permission-groups.destroy');

    // 외부 서비스 키·설정 (팝빌·토스·알림톡·건보공단·AWS·메일·NICE)
    // 항목 목록은 config/settings-schema.php 가 쥔다.
    Route::get('/settings/services',          [\App\Http\Controllers\ServiceSettingController::class, 'index'])->name('service-settings.index');
    Route::put('/settings/services/{group}',  [\App\Http\Controllers\ServiceSettingController::class, 'update'])->name('service-settings.update');

    // NICE 본인확인 설정 (자격증명 서버 저장 + 연결 테스트)
    Route::get( '/settings/nice',      [\App\Http\Controllers\NiceSettingController::class, 'edit'])->name('nice-settings.edit');
    Route::put( '/settings/nice',      [\App\Http\Controllers\NiceSettingController::class, 'update'])->name('nice-settings.update');
    Route::post('/settings/nice/test', [\App\Http\Controllers\NiceSettingController::class, 'test'])->name('nice-settings.test');

    // 마스터 관리 — 병원·기관을 카테고리 탭으로
    Route::get(   '/settings/masters',           [\App\Http\Controllers\MasterController::class, 'index'])->name('masters.index');
    Route::post(  '/settings/masters',           [\App\Http\Controllers\MasterController::class, 'store'])->name('masters.store');
    Route::put(   '/settings/masters/{master}',  [\App\Http\Controllers\MasterController::class, 'update'])->name('masters.update');
    Route::delete('/settings/masters/{master}',  [\App\Http\Controllers\MasterController::class, 'destroy'])->name('masters.destroy');

    // 환경 설정 — 화면마다 고르는 목록(서류 유형 등)을 여기서 등록·수정한다
    Route::get(   '/settings/common-codes',              [\App\Http\Controllers\CommonCodeController::class, 'index'])->name('common-codes.index');
    Route::post(  '/settings/common-codes',              [\App\Http\Controllers\CommonCodeController::class, 'store'])->name('common-codes.store');
    Route::post(  '/settings/common-codes/bulk',         [\App\Http\Controllers\CommonCodeController::class, 'bulk'])->name('common-codes.bulk');
    Route::put(   '/settings/common-codes/{commonCode}', [\App\Http\Controllers\CommonCodeController::class, 'update'])->name('common-codes.update');
    Route::delete('/settings/common-codes/{commonCode}', [\App\Http\Controllers\CommonCodeController::class, 'destroy'])->name('common-codes.destroy');

    // 메시지 관리 — 거래처를 골라 문자·알림톡을 보낸다
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/',  [\App\Http\Controllers\MessageController::class, 'index'])->name('index');
        Route::post('/send', [\App\Http\Controllers\MessageController::class, 'send'])->name('send');
        // 메시지 유형 — 검수 화면 팝오버와 이 화면이 같은 것을 쓴다
        Route::get(   '/templates',             [\App\Http\Controllers\MessageController::class, 'templates'])->name('templates');
        Route::post(  '/templates',             [\App\Http\Controllers\MessageController::class, 'storeTemplate'])->name('templates.store');
        Route::put(   '/templates/{template}',  [\App\Http\Controllers\MessageController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}',  [\App\Http\Controllers\MessageController::class, 'destroyTemplate'])->name('templates.destroy');
    });

    // 위임장 서명 — 서명한 사람·번호를 한자리에서 보고 서류를 받는다
    Route::get('/prescription-consents', [\App\Http\Controllers\PrescriptionConsentController::class, 'index'])
        ->name('prescription-consents.index');
    Route::post('/prescription-consents', [\App\Http\Controllers\PrescriptionConsentController::class, 'store'])
        ->name('prescription-consents.store');

    // 개인정보 수집·이용 동의 (mcoloplast) — 관리자 조회/관리
    Route::get('/privacy-consents',          [PrivacyConsentAdminController::class, 'index'])->name('privacy-consents.index');
    Route::get('/privacy-consents/export',   [PrivacyConsentAdminController::class, 'export'])->name('privacy-consents.export');
    Route::put('/privacy-consents/{consent}/memo', [PrivacyConsentAdminController::class, 'saveMemo'])->name('privacy-consents.memo');
});

// 관리자 초대 수락 (로그인 불필요 — 이메일 링크)
Route::get( '/admin/invite/{token}', [AdminInvitationController::class, 'accept'])->name('admin.invite.accept');
Route::post('/admin/invite/{token}', [AdminInvitationController::class, 'confirm'])->name('admin.invite.confirm');

// 위임동의 공개 페이지 (로그인 불필요 — 환자 SMS 링크)
Route::prefix('consent')->name('consent.')->group(function () {
    Route::get( '/{token}', [ConsentController::class, 'show'])->name('show');
    Route::post('/{token}', [ConsentController::class, 'submit'])->name('submit');
    // NICE 본인확인: 표준창 파라미터 발급(자기 페이지 fetch) / NICE returnurl 콜백(외부)
    Route::post('/{token}/nice/start', [ConsentController::class, 'niceStart'])->name('nice.start');
    Route::match(['get', 'post'], '/{token}/nice/callback', [ConsentController::class, 'niceCallback'])->name('nice.callback');
});

// 개인정보 수집·이용 동의서 (mcoloplast) 공개 페이지 (로그인 불필요 — 환자 직접 작성)
Route::prefix('privacy')->name('privacy.')->group(function () {
    Route::get( '/',              [PrivacyConsentController::class, 'landing'])->name('landing');
    Route::get( '/catheter',      [PrivacyConsentController::class, 'catheter'])->name('catheter');
    Route::get( '/stoma',         [PrivacyConsentController::class, 'stoma'])->name('stoma');
    Route::post('/{type}',        [PrivacyConsentController::class, 'submit'])->name('submit');
    Route::get( '/{type}/done',   [PrivacyConsentController::class, 'done'])->name('done');
});

// e-Fax 콜백 (팩스 서비스에서 호출 — 인증 불필요)

// ── Dev: admin_invitations 테이블 마이그레이션 ──
Route::get('/dev/migrate-admin-invitations', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    if (!\Illuminate\Support\Facades\Schema::hasTable('admin_invitations')) {
        \Illuminate\Support\Facades\Schema::create('admin_invitations', function ($t) {
            $t->id();
            $t->string('email', 200)->index();
            $t->string('role', 20)->default('manager');
            $t->string('token', 64)->unique();
            $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        return response()->json(['ok' => true, 'msg' => 'admin_invitations 테이블 생성 완료']);
    }
    return response()->json(['ok' => true, 'msg' => '이미 존재합니다.']);
});

// ── Dev: user_activity_logs 테이블 마이그레이션 ──
Route::get('/dev/migrate-activity-logs', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    if (!\Illuminate\Support\Facades\Schema::hasTable('user_activity_logs')) {
        \Illuminate\Support\Facades\Schema::create('user_activity_logs', function ($t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 20)->default('page');
            $t->string('menu_name', 80)->nullable();
            $t->string('route_name', 100)->nullable();
            $t->string('url', 300)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
        return response()->json(['ok' => true, 'msg' => 'user_activity_logs 테이블 생성 완료']);
    }
    return response()->json(['ok' => true, 'msg' => '이미 존재합니다.']);
});

// ── Dev: toured_pages 컬럼 마이그레이션 ──
Route::get('/dev/migrate-toured-pages', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'toured_pages')) {
        \Illuminate\Support\Facades\Schema::table('users', function ($t) {
            $t->json('toured_pages')->nullable()->after('is_active');
        });
        return response()->json(['ok' => true, 'msg' => 'toured_pages 컬럼 추가 완료']);
    }
    return response()->json(['ok' => true, 'msg' => '이미 존재합니다.']);
});

// ── 임시: 재구매 테스트 데이터 시드 (사용 후 삭제 권장) ──
Route::get('/dev/seed-repurchase', function () {
    if (!app()->isLocal() && !\Illuminate\Support\Facades\Auth::check()) {
        abort(403);
    }

    $prescriptions = \App\Models\Prescription::inRandomOrder()->take(30)->get();

    if ($prescriptions->isEmpty()) {
        return response()->json(['error' => '처방전 데이터가 없습니다.'], 404);
    }

    // 2026년 4~6월에 걸쳐 골고루 분산
    $dates = [];
    foreach ([4, 5, 6] as $month) {
        $daysInMonth = \Carbon\Carbon::create(2026, $month)->daysInMonth;
        // 월별 8~12개 날짜 생성
        $count = rand(8, min(12, $prescriptions->count()));
        for ($i = 0; $i < $count; $i++) {
            $dates[] = \Carbon\Carbon::create(2026, $month, rand(1, $daysInMonth))->toDateString();
        }
    }

    shuffle($dates);

    $updated = 0;
    foreach ($prescriptions as $i => $p) {
        $date = $dates[$i % count($dates)];
        $p->update(['repurchase_date' => $date]);
        $updated++;
    }

    // 날짜별 건수 집계
    $summary = \App\Models\Prescription::whereNotNull('repurchase_date')
        ->whereBetween('repurchase_date', ['2026-04-01', '2026-06-30'])
        ->selectRaw('DATE_FORMAT(repurchase_date, "%Y-%m") as ym, COUNT(*) as cnt')
        ->groupBy('ym')
        ->orderBy('ym')
        ->pluck('cnt', 'ym');

    return response()->json([
        'success'  => true,
        'updated'  => $updated,
        'summary'  => $summary,
        'message'  => '테스트 데이터 생성 완료. 이 라우트는 사용 후 web.php에서 삭제하세요.',
    ]);
})->middleware('auth');

// ── 개발용: 데이터 초기화 + 샘플 데이터 50건 생성 ──────────────────
Route::get('/dev/reset-data', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);

    $surnames    = ['김','이','박','최','정','강','조','윤','장','임','한','오','서','신','권','황','안','송','유','홍'];
    $maleNames   = ['준혁','민준','성진','지훈','동현','재원','현우','영수','승민','태양','진호','철수','민성','도현','하준'];
    $femaleNames = ['지수','서연','민지','예진','수빈','하은','지현','나연','유리','소연','혜진','아영','채원','다연','은서'];
    $cities      = [
        '서울특별시 강남구','서울특별시 서초구','서울특별시 마포구','서울특별시 송파구',
        '부산광역시 해운대구','부산광역시 부산진구',
        '대구광역시 수성구','대구광역시 달서구',
        '인천광역시 남동구','인천광역시 연수구',
        '경기도 수원시','경기도 성남시','경기도 고양시','경기도 용인시',
        '경상북도 포항시','경상남도 창원시','충청북도 청주시','전라북도 전주시',
        '광주광역시 서구','대전광역시 서구',
    ];
    $hospitals   = [
        ['name' => '경북대학교병원','code' => '37100017'],
        ['name' => '서울대학교병원','code' => '11100017'],
        ['name' => '세브란스병원','code' => '11100023'],
        ['name' => '삼성서울병원','code' => '11100024'],
        ['name' => '아산의료원','code' => '11100025'],
        ['name' => '가톨릭대학교서울성모병원','code' => '11100026'],
        ['name' => '고려대학교병원','code' => '11100027'],
        ['name' => '부산대학교병원','code' => '26100017'],
        ['name' => '전남대학교병원','code' => '61100017'],
        ['name' => '충북대학교병원','code' => '43100017'],
    ];
    $doctors  = ['유은상','박철환','이준영','김민수','정현식','강태훈','조성일','윤진호','서혜연','최지현'];
    $diseases = [
        ['name' => '하반신마비 및 사지마비','code' => 'G82.x'],
        ['name' => '척수 손상','code' => 'S14.x'],
        ['name' => '신경인성 방광','code' => 'N31.9'],
        ['name' => '배뇨 기능 장애','code' => 'R33.x'],
        ['name' => '전립선 비대증','code' => 'N40.x'],
        ['name' => '요도 협착','code' => 'N35.x'],
        ['name' => '다발성 경화증','code' => 'G35.x'],
        ['name' => '파킨슨병','code' => 'G20.x'],
        ['name' => '요실금','code' => 'N39.3'],
        ['name' => '방광 기능 이상','code' => 'N32.x'],
    ];
    $products = [
        ['name' => 'SpeediCath Compact Male','code' => 'SC-CM-14'],
        ['name' => 'SpeediCath Compact Female','code' => 'SC-CF-14'],
        ['name' => 'SpeediCath Standard Male','code' => 'SC-SM-14'],
        ['name' => 'Lofric Primo Male','code' => 'LP-M-14'],
        ['name' => 'Lofric Primo Female','code' => 'LP-F-14'],
        ['name' => 'Lofric Origo Male','code' => 'LO-M-14'],
        ['name' => 'EasiCath Male','code' => 'EC-M-14'],
        ['name' => 'EasiCath Female','code' => 'EC-F-14'],
    ];

    // 1. 삭제
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \Illuminate\Support\Facades\DB::table('nhis_fax_logs')->truncate();
    \Illuminate\Support\Facades\DB::table('toss_payments')->truncate();
    \Illuminate\Support\Facades\DB::table('orders')->truncate();
    \Illuminate\Support\Facades\DB::table('prescription_items')->truncate();
    \Illuminate\Support\Facades\DB::table('prescriptions')->truncate();
    \Illuminate\Support\Facades\DB::table('patients')->truncate();
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

    // 2. 유저 정보
    $adminId    = \Illuminate\Support\Facades\DB::table('users')->where('role','admin')->value('id')
                    ?? \Illuminate\Support\Facades\DB::table('users')->value('id');
    $managerIds = \Illuminate\Support\Facades\DB::table('users')->where('role','manager')->pluck('id')->all()
                    ?: [$adminId];

    $rnd = fn($arr) => $arr[array_rand($arr)];

    // 3. 상태 풀
    $statusPool = array_merge(
        array_fill(0, 5,  'pending'),
        array_fill(0, 8,  'ocr_done'),
        array_fill(0, 10, 'review_needed'),
        array_fill(0, 17, 'approved'),
        array_fill(0, 10, 'ordered')
    );
    shuffle($statusPool);

    $today    = now();
    $orderSeq = 1;
    $createdPatients = 0;
    $createdRx       = 0;
    $createdOrders   = 0;

    for ($i = 0; $i < 50; $i++) {
        $gender    = ($i % 2 === 0) ? 'M' : 'F';
        $firstName = $gender === 'M' ? $rnd($maleNames) : $rnd($femaleNames);
        $surname   = $rnd($surnames);
        $name      = $surname . $firstName;

        $birthYear  = rand(1960, 2000);
        $birthMonth = str_pad(rand(1,12), 2, '0', STR_PAD_LEFT);
        $birthDay   = str_pad(rand(1,28),  2, '0', STR_PAD_LEFT);
        $birthDate  = "{$birthYear}-{$birthMonth}-{$birthDay}";
        $yy         = substr($birthYear, 2);
        $gCode      = ($gender === 'M') ? ($birthYear >= 2000 ? '3' : '1') : ($birthYear >= 2000 ? '4' : '2');
        $residentNo = "{$yy}{$birthMonth}{$birthDay}-{$gCode}" . str_pad(rand(100000,999999), 6, '0', STR_PAD_LEFT);

        $mobile  = '010-' . str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT) . '-' . str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT);
        $city    = $rnd($cities);
        $address = "{$city} " . ['중앙로','대학로','번영로','시청길','동문로'][array_rand(['중앙로','대학로','번영로','시청길','동문로'])] . ' ' . rand(1,999) . '번길 ' . rand(1,50);

        $nhisEligible    = (rand(1,10) <= 7);
        $nhisCoverageRate = $nhisEligible ? ($rand01 = rand(0,1) ? 90.00 : 80.00) : 0.00;

        $patient = \App\Models\Patient::create([
            'name'               => $name,
            'resident_no'        => $residentNo,
            'birth_date'         => $birthDate,
            'gender'             => $gender,
            'mobile'             => $mobile,
            'address'            => $address,
            'is_nhis_eligible'   => $nhisEligible,
            'nhis_coverage_rate' => $nhisCoverageRate,
        ]);
        $createdPatients++;

        // 처방전
        $status     = $statusPool[$i];
        $daysAgo    = rand(1, 90);
        $createdAt  = $today->copy()->subDays($daysAgo)->subHours(rand(0,23))->subMinutes(rand(0,59));
        $issuedDate = $createdAt->copy()->subDays(rand(0,3))->format('Y-m-d');
        $rxDate     = $createdAt->format('Ymd');
        $rxNumber   = sprintf('RX-%s-%03d', $rxDate, $i + 1);

        $hospital     = $rnd($hospitals);
        $doctor       = $rnd($doctors);
        $disease      = $rnd($diseases);
        $product      = $rnd($products);
        $assignedId   = $rnd($managerIds);
        $dailyCount   = rand(4,8);
        $totalDays    = $rnd([30,60,90]);
        $totalCount   = $dailyCount * $totalDays;
        $productPrice = rand(40000, 80000);
        $nhisAmount   = $nhisEligible ? round($productPrice * $nhisCoverageRate / 100) : 0;
        $patientCopay = $productPrice - $nhisAmount;
        $isReviewed   = in_array($status, ['approved','ordered']);
        $reviewedBy   = $isReviewed ? $rnd($managerIds) : null;
        $reviewedAt   = $isReviewed ? $createdAt->copy()->addHours(rand(1,8)) : null;
        $repurchaseDate = null;
        if (in_array($status, ['approved','ordered']) && rand(1,10) <= 4) {
            $repurchaseDate = $today->copy()->addDays(rand(-30,60))->format('Y-m-d');
        }

        $rx = \App\Models\Prescription::create([
            'rx_number'         => $rxNumber,
            'patient_id'        => $patient->id,
            'assigned_user_id'  => $assignedId,
            'created_by'        => $adminId,
            'upload_source'     => rand(1,3) === 1 ? 'mobile' : 'web',
            'status'            => $status,
            'ocr_confidence'    => $status === 'pending' ? null : rand(70,99),
            'patient_name_ocr'  => $name,
            'hospital_name'     => $hospital['name'],
            'hospital_code'     => $hospital['code'],
            'doctor_name'       => $doctor,
            'specialty'         => '비뇨의학과',
            'license_no'        => str_pad(rand(10000,99999),5,'0',STR_PAD_LEFT),
            'department'        => '비뇨기의학과',
            'disease_name'      => $disease['name'],
            'disease_code'      => $disease['code'],
            'daily_count'       => $dailyCount,
            'total_days'        => $totalDays,
            'total_count'       => $totalCount,
            'usage_period'      => '교부일로부터 처방기간까지',
            'issued_date'       => $issuedDate,
            'product_name'      => $product['name'],
            'product_code'      => $product['code'],
            'quantity'          => $totalCount,
            'nhis_status'       => $nhisEligible ? 'eligible' : 'ineligible',
            'product_price'     => $productPrice,
            'nhis_amount'       => $nhisAmount,
            'patient_copay'     => $patientCopay,
            'reviewed_by'       => $reviewedBy,
            'reviewed_at'       => $reviewedAt,
            'postcode'          => str_pad(rand(10000,99999),5,'0',STR_PAD_LEFT),
            'address_detail'    => $address,
            'repurchase_date'   => $repurchaseDate,
            'created_at'        => $createdAt,
            'updated_at'        => $createdAt,
        ]);
        $createdRx++;

        if ($status === 'ordered') {
            $orderNum   = sprintf('ORD-%04d', $orderSeq++);
            $sFee       = 3000;
            $total      = $patientCopay + $sFee;
            $orderAt    = $reviewedAt->copy()->addHours(rand(1,6));
            $nhisClaims = ['pending','pending','submitted','approved','approved','rejected'];
            $nhisClaim  = $nhisClaims[array_rand($nhisClaims)];
            $oStatuses  = ['confirmed','shipping','delivered'];
            $oStatus    = $oStatuses[array_rand($oStatuses)];
            $tracking   = in_array($oStatus,['shipping','delivered']) ? str_pad(rand(100000000000,999999999999),12,'0',STR_PAD_LEFT) : null;
            $deliveredAt = $oStatus === 'delivered' ? $orderAt->copy()->addDays(rand(3,7)) : null;

            \App\Models\Order::create([
                'order_number'       => $orderNum,
                'prescription_id'    => $rx->id,
                'patient_id'         => $patient->id,
                'created_by'         => $assignedId,
                'product_name'       => $product['name'],
                'product_code'       => $product['code'],
                'quantity'           => $totalCount,
                'unit_price'         => $productPrice,
                'nhis_amount'        => $nhisAmount,
                'patient_copay'      => $patientCopay,
                'shipping_fee'       => $sFee,
                'total_amount'       => $total,
                'status'             => $oStatus,
                'shipping_address'   => $address,
                'tracking_number'    => $tracking,
                'estimated_delivery' => $orderAt->copy()->addDays(rand(2,5))->format('Y-m-d'),
                'delivered_at'       => $deliveredAt,
                'nhis_claim_status'  => $nhisClaim,
                'nhis_submitted_at'  => in_array($nhisClaim,['submitted','approved','rejected']) ? $orderAt->copy()->addDays(rand(1,3)) : null,
                'nhis_approved_at'   => $nhisClaim === 'approved' ? $orderAt->copy()->addDays(rand(5,14)) : null,
                'nhis_reimbursement' => $nhisClaim === 'approved' ? $nhisAmount : null,
                'created_at'         => $orderAt,
                'updated_at'         => $orderAt,
            ]);
            $createdOrders++;
        }
    }

    return response()->json([
        'success'          => true,
        'patients_created' => $createdPatients,
        'rx_created'       => $createdRx,
        'orders_created'   => $createdOrders,
        'message'          => '데이터 초기화 및 샘플 데이터 생성 완료. 이 라우트는 사용 후 삭제하세요.',
    ]);
})->middleware('auth');

// ── 개발용: 발송/발행 내역 가상 데이터 시드 ──────────────────────────
Route::get('/dev/seed-dispatch', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);

    $DB  = \Illuminate\Support\Facades\DB::class;
    $now = \Carbon\Carbon::now();

    // 시드에 사용할 유저 목록
    $userIds = \App\Models\User::pluck('id')->all();
    $rndUser = fn() => $userIds[array_rand($userIds)];

    // 은행 코드 (토스 가상계좌)
    $banks = ['004','011','020','023','027','039','081','088','090'];
    $bankNames = ['004'=>'국민','011'=>'농협','020'=>'우리','023'=>'SC제일','027'=>'씨티','039'=>'경남','081'=>'하나','088'=>'신한','090'=>'카카오'];

    // ────────────────────────────────────────────────
    // 1. 가상계좌 발행 — confirmed/shipping/delivered 주문 중 TossPayment 없는 것
    // ────────────────────────────────────────────────
    $ordersForVA = \App\Models\Order::with(['patient','prescription.patient'])
        ->whereIn('status', ['confirmed','shipping','delivered'])
        ->whereDoesntHave('tossPayment')
        ->inRandomOrder()
        ->take(25)
        ->get();

    $vaCreated = 0;
    foreach ($ordersForVA as $order) {
        $bank       = $banks[array_rand($banks)];
        $statusPool = ['DONE','DONE','DONE','WAITING','WAITING','EXPIRED','DONE'];
        $status     = $statusPool[array_rand($statusPool)];
        $issuedAt   = $order->created_at->copy()->addHours(rand(1, 12));
        $dueDate    = $issuedAt->copy()->addDays(3);
        $depositedAt= $status === 'DONE' ? $issuedAt->copy()->addHours(rand(1, 48)) : null;
        $patient    = $order->patient ?? $order->prescription?->patient;

        \App\Models\TossPayment::create([
            'order_id'       => $order->id,
            'payment_key'    => 'tviva' . strtoupper(\Illuminate\Support\Str::random(30)),
            'toss_order_id'  => $order->order_number . '_' . $issuedAt->format('YmdHis'),
            'method'         => '가상계좌',
            'status'         => $status,
            'amount'         => (int) $order->total_amount,
            'bank'           => $bank,
            'account_number' => rand(100,999) . '-' . rand(10000,99999) . '-' . rand(10000,99999),
            'customer_name'  => $patient?->name ?? '홍길동',
            'due_date'       => $dueDate,
            'deposited_at'   => $depositedAt,
            'raw_response'   => ['seeded' => true, 'bank' => $bankNames[$bank] ?? $bank],
            'created_at'     => $issuedAt,
            'updated_at'     => $issuedAt,
        ]);
        $vaCreated++;
    }

    // ────────────────────────────────────────────────
    // 2. 세금계산서 발행 — delivered 주문 중 미발행 일부
    // ────────────────────────────────────────────────
    $bizNames = ['콜로플라스트코리아(주)','(주)메디케어','신한의료기(주)','대한의료소모품(주)','한국메디컬서비스'];
    $bizNos   = ['123-45-67890','234-56-78901','345-67-89012','456-78-90123','567-89-01234'];

    $ordersForTax = \App\Models\Order::whereIn('status', ['delivered','shipping'])
        ->where(function($q) {
            $q->whereNull('tax_invoice_no')->orWhere('tax_invoice_status','not_issued');
        })
        ->inRandomOrder()
        ->take(20)
        ->get();

    $taxCreated = 0;
    foreach ($ordersForTax as $order) {
        $bizIdx    = array_rand($bizNames);
        $supply    = round($order->total_amount / 1.1);
        $vat       = $order->total_amount - $supply;
        $issuedAt  = ($order->delivered_at ?? $order->created_at)->copy()->addDays(rand(1,5));
        $statusOpt = ['issued','issued','issued','cancelled'];
        $status    = $statusOpt[array_rand($statusOpt)];
        $cancelAt  = $status === 'cancelled' ? $issuedAt->copy()->addDays(rand(1,3)) : null;
        $invoiceNo = 'TI-' . $issuedAt->format('Ymd') . '-' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);

        $order->update([
            'tax_invoice_status'       => $status,
            'tax_invoice_no'           => $invoiceNo,
            'tax_invoice_type'         => 'electronic',
            'tax_invoice_biz_name'     => $bizNames[$bizIdx],
            'tax_invoice_biz_no'       => $bizNos[$bizIdx],
            'tax_invoice_email'        => 'billing@' . strtolower(str_replace(['(주)','(주','주)','(',')',' '],['','','','','',''], $bizNames[$bizIdx])) . '.co.kr',
            'tax_invoice_supply'       => $supply,
            'tax_invoice_vat'          => $vat,
            'tax_invoice_issued_at'    => $issuedAt,
            'tax_invoice_cancelled_at' => $cancelAt,
        ]);
        $taxCreated++;
    }

    // ────────────────────────────────────────────────
    // 3. 현금영수증 발행 — delivered 주문 중 세금계산서 없는 것
    // ────────────────────────────────────────────────
    $ordersForCR = \App\Models\Order::whereIn('status', ['delivered','shipping'])
        ->whereNull('tax_invoice_no')
        ->where(function($q) {
            $q->whereNull('cash_receipt_no')->orWhere('cash_receipt_status','not_issued');
        })
        ->inRandomOrder()
        ->take(15)
        ->get();

    $crCreated = 0;
    foreach ($ordersForCR as $order) {
        $patient   = $order->patient;
        $mobile    = $patient?->mobile
            ? preg_replace('/[^0-9]/', '', $patient->mobile)
            : '01' . rand(0,1) . str_pad(rand(0,99999999),8,'0',STR_PAD_LEFT);
        $crType    = rand(0,1) ? 'income_deduction' : 'business_expense';
        $issuedAt  = ($order->delivered_at ?? $order->created_at)->copy()->addDays(rand(0,3));
        $statusOpt = ['issued','issued','issued','cancelled'];
        $status    = $statusOpt[array_rand($statusOpt)];
        $cancelAt  = $status === 'cancelled' ? $issuedAt->copy()->addDays(rand(1,2)) : null;
        $receiptNo = 'CR-' . $issuedAt->format('Ymd') . '-' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);

        $order->update([
            'cash_receipt_status'       => $status,
            'cash_receipt_no'           => $receiptNo,
            'cash_receipt_type'         => $crType,
            'cash_receipt_identifier'   => $mobile,
            'cash_receipt_amount'       => $order->total_amount,
            'cash_receipt_issued_at'    => $issuedAt,
            'cash_receipt_cancelled_at' => $cancelAt,
        ]);
        $crCreated++;
    }

    // ────────────────────────────────────────────────
    // 4. NHIS 청구 발송 로그 — nhis_claim_status가 submitted/approved/rejected인 주문
    // ────────────────────────────────────────────────
    $ordersForNhis = \App\Models\Order::whereIn('nhis_claim_status', ['submitted','approved','rejected'])
        ->doesntHave('faxLogs')
        ->with('prescription')
        ->get();

    $faxNumbers = ['15884000','15882000','1577-1000','15444000','1599-2000'];
    $nhisTitles = [
        '건강보험 요양급여비용 청구서',
        'NHIS 급여 청구 — 보조기기 급여',
        '건강보험 보조기기 급여청구서',
        '요양기관 급여비용 청구',
    ];
    $faxCreated = 0;

    foreach ($ordersForNhis as $order) {
        $sentAt       = ($order->nhis_submitted_at ?? $order->created_at->copy()->addDays(1));
        $sendStatus   = 'sent';
        $nhisResultMap = [
            'submitted' => 'pending',
            'approved'  => 'approved',
            'rejected'  => 'rejected',
        ];
        $nhisResult   = $nhisResultMap[$order->nhis_claim_status] ?? 'pending';
        $approvedAmt  = $nhisResult === 'approved' ? $order->nhis_amount : null;
        $nhisMsg      = match ($nhisResult) {
            'approved' => '심사 결과: 전액 승인',
            'rejected' => '심사 결과: 기준 초과로 불인정',
            default    => null,
        };
        $resultAt = $nhisResult !== 'pending'
            ? $sentAt->copy()->addDays(rand(5, 14))
            : null;

        $log = \App\Models\NhisFaxLog::create([
            'order_id'       => $order->id,
            'sent_by'        => $rndUser(),
            'fax_number'     => $faxNumbers[array_rand($faxNumbers)],
            'sender_number'  => '02-' . rand(1000,9999) . '-' . rand(1000,9999),
            'document_title' => $nhisTitles[array_rand($nhisTitles)],
            'claim_amount'   => $order->total_amount,
            'nhis_amount'    => $order->nhis_amount ?? 0,
            'patient_copay'  => $order->patient_copay ?? 0,
            'status'         => $sendStatus,
            'reference_no'   => 'FAX-' . $sentAt->format('YmdHis') . '-' . rand(1000,9999),
            'retry_count'    => 0,
            'sent_at'        => $sentAt,
            'confirmed_at'   => $sentAt->copy()->addMinutes(rand(3,30)),
            'nhis_result'    => $nhisResult,
            'approved_amount'=> $approvedAmt,
            'nhis_message'   => $nhisMsg,
            'nhis_result_at' => $resultAt,
            'created_at'     => $sentAt,
            'updated_at'     => $resultAt ?? $sentAt,
        ]);

        // 주문에 latest_fax_log_id 연결
        $order->update(['latest_fax_log_id' => $log->id]);
        $faxCreated++;

        // 일부 주문은 재시도 로그 추가
        if (rand(1,4) === 1) {
            $retry = \App\Models\NhisFaxLog::create([
                'order_id'       => $order->id,
                'sent_by'        => $rndUser(),
                'fax_number'     => $faxNumbers[array_rand($faxNumbers)],
                'sender_number'  => '02-' . rand(1000,9999) . '-' . rand(1000,9999),
                'document_title' => $nhisTitles[array_rand($nhisTitles)] . ' (재전송)',
                'claim_amount'   => $order->total_amount,
                'nhis_amount'    => $order->nhis_amount ?? 0,
                'patient_copay'  => $order->patient_copay ?? 0,
                'status'         => 'sent',
                'reference_no'   => 'FAX-' . $sentAt->copy()->addDays(1)->format('YmdHis') . '-R' . rand(100,999),
                'retry_count'    => 1,
                'sent_at'        => $sentAt->copy()->addDays(1),
                'confirmed_at'   => $sentAt->copy()->addDays(1)->addMinutes(rand(3,15)),
                'nhis_result'    => $nhisResult,
                'approved_amount'=> $approvedAmt,
                'nhis_message'   => $nhisMsg,
                'nhis_result_at' => $resultAt,
                'created_at'     => $sentAt->copy()->addDays(1),
                'updated_at'     => $sentAt->copy()->addDays(1),
            ]);
            $order->update(['latest_fax_log_id' => $retry->id]);
            $faxCreated++;
        }
    }

    // ────────────────────────────────────────────────
    // 결과 리포트
    // ────────────────────────────────────────────────
    return response()->json([
        'success'           => true,
        'virtual_accounts'  => $vaCreated,
        'tax_invoices'      => $taxCreated,
        'cash_receipts'     => $crCreated,
        'nhis_fax_logs'     => $faxCreated,
        'totals' => [
            'toss_payments' => \App\Models\TossPayment::count(),
            'tax_issued'    => \App\Models\Order::whereNotNull('tax_invoice_no')->count(),
            'cash_issued'   => \App\Models\Order::whereNotNull('cash_receipt_no')->count(),
            'fax_logs'      => \App\Models\NhisFaxLog::count(),
        ],
        'message' => '발송/발행 내역 가상 데이터 생성 완료. 이 라우트는 사용 후 삭제하세요.',
    ]);
})->middleware('auth');

// ── 개발용: orders 테이블 컬럼 마이그레이션 ──────────────────────
Route::get('/dev/test-demoworks', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
    $token   = config('services.demoworks.token');
    try {
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->timeout(15)
            ->get("{$baseUrl}/api/v1/item/item_list", [
                'item'     => '도뇨',
                'per_page' => 3,
            ]);
        return response()->json([
            'success'    => true,
            'http_status'=> $response->status(),
            'api_url'    => $baseUrl,
            'token_len'  => strlen($token ?? ''),
            'raw'        => $response->json(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'api_url' => $baseUrl]);
    }
})->middleware('auth');

Route::get('/dev/migrate-order-cols', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    $added = [];
    try {
        \Illuminate\Support\Facades\Schema::table('orders', function ($table) use (&$added) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'so_type')) {
                $table->string('so_type', 10)->nullable()->after('status')
                      ->comment('Withworks 판매 유형 코드');
                $added[] = 'so_type';
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'withworks_so_no')) {
                $table->string('withworks_so_no', 50)->nullable()->after('so_type')
                      ->comment('Withworks SO 번호');
                $added[] = 'withworks_so_no';
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'withworks_so_id')) {
                $table->unsignedBigInteger('withworks_so_id')->nullable()->after('withworks_so_no')
                      ->comment('Withworks SO PK');
                $added[] = 'withworks_so_id';
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'shipping_recipient')) {
                $table->string('shipping_recipient', 100)->nullable()->after('shipping_address')
                      ->comment('배송지 받는 사람');
                $added[] = 'shipping_recipient';
            }
        });
        return response()->json([
            'success' => true,
            'added'   => $added,
            'message' => empty($added) ? '모든 컬럼이 이미 존재합니다.' : implode(', ', $added) . ' 컬럼이 추가되었습니다.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->middleware('auth');

/* 환자가 여는 결제 페이지 — 로그인 없이 열린다.
   토큰으로만 찾는다. 주문번호로 열리면 번호를 바꿔 가며 남의 주문을 볼 수 있다. */
Route::get('/pay/{token}',      [\App\Http\Controllers\PaymentLinkController::class, 'show'])->name('pay.show');
Route::get('/pay/{token}/done', [\App\Http\Controllers\PaymentLinkController::class, 'done'])->name('pay.done');

// 토스페이먼츠 웹훅 (인증 불필요 — 토스 서버에서 직접 호출)
Route::post('/toss/webhook', [TossWebhookController::class, 'handle'])->name('toss.webhook');

// ── 개발용: FCM 상태 진단 및 테스트 전송 ──────────────────
Route::get('/dev/fcm-status', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);

    $saPath  = storage_path('app/firebase/service-account.json');
    $user    = \Illuminate\Support\Facades\Auth::user();
    $results = [
        'service_account_exists' => file_exists($saPath),
        'service_account_path'   => $saPath,
        'current_user_id'        => $user->id,
        'current_user_fcm_token' => $user->fcm_token ? substr($user->fcm_token, 0, 30) . '...' : null,
        'users_with_token'       => \App\Models\User::whereNotNull('fcm_token')->count(),
    ];
    return response()->json($results, 200, [], JSON_UNESCAPED_UNICODE);
})->middleware('auth');

Route::get('/dev/fcm-test', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);

    $user = \Illuminate\Support\Facades\Auth::user();
    if (!$user->fcm_token) {
        return response()->json(['ok' => false, 'msg' => 'fcm_token 없음 — 앱 재시작 후 다시 시도'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    $ok = \App\Helpers\FcmHelper::send(
        $user->fcm_token,
        'FCM 테스트',
        '테스트 알림 수신 완료!',
        ['type' => 'test']
    );

    return response()->json([
        'ok'  => $ok,
        'msg' => $ok ? '전송 성공' : '전송 실패 (laravel.log 확인)',
    ], 200, [], JSON_UNESCAPED_UNICODE);
})->middleware('auth');

// ── 개발용: users 테이블에 fcm_token 컬럼 추가 ──
Route::get('/dev/migrate-fcm-token', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) abort(403);
    if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'fcm_token')) {
        \Illuminate\Support\Facades\Schema::table('users', function ($t) {
            $t->string('fcm_token', 512)->nullable()->after('remember_token')
              ->comment('FCM 디바이스 토큰 (백그라운드 푸시 알림)');
        });
        return response()->json(['ok' => true, 'msg' => 'fcm_token 컬럼 추가 완료']);
    }
    return response()->json(['ok' => true, 'msg' => '이미 존재합니다.']);
})->middleware('auth');


// Laravel Breeze/Fortify 인증 라우트 (별도 설치 필요)
require __DIR__ . '/auth.php';
