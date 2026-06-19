<?php
// routes/api.php
// 모바일 앱(Flutter) ↔ Laravel API 엔드포인트

use App\Http\Controllers\Popbill\CashbillController;
use App\Http\Controllers\Popbill\FaxController;
use App\Http\Controllers\Popbill\KakaoController;
use App\Http\Controllers\Popbill\MessageController;
use App\Http\Controllers\Popbill\TaxinvoiceController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\InquiryApiController;
use App\Http\Controllers\Api\NoticeApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PrescriptionApiController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ShopOrderWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 모바일 앱 인증: Laravel Sanctum (Bearer Token)
|
| Base URL: /api
|
*/

// ── Webhook (인증 불필요, 시크릿 키로 검증) ──────────────
Route::post('/webhook/shop-order', [ShopOrderWebhookController::class, 'receive']);

// ── CE샵 배지 카운트 (웹 관리자 사이드바용) ───────────────
Route::get('/shop-badge', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) {
        return response()->json(['count' => 0]);
    }
    if (config('services.ce_shop.api_enabled')) {
        try {
            $baseUrl = rtrim(config('services.ce_shop.base_url'), '/');
            $secret  = config('services.ce_shop.webhook_secret');
            $res = \Illuminate\Support\Facades\Http::withHeaders(['X-Shop-Secret' => $secret])
                ->timeout(3)
                ->get("{$baseUrl}/api/internal/badge");
            if ($res->successful()) {
                return response()->json(['count' => (int) ($res->json('pending') ?? 0)]);
            }
        } catch (\Throwable) {}
    }
    return response()->json(['count' => \App\Models\ShopOrder::where('status', 'confirmed')->count()]);
})->middleware('web');

// ── 인증 불필요 ───────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',       [AuthApiController::class, 'login']);       // 1단계: 이메일/비밀번호 → OTP 발송
    Route::post('/verify-otp',  [AuthApiController::class, 'verifyOtp']);  // 2단계: OTP 검증 → Bearer 토큰
    Route::post('/resend-otp',  [AuthApiController::class, 'resendOtp']);  // OTP 재발송
});

// ── 인증 필요 (Bearer Token) ──────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // 인증
    Route::prefix('auth')->group(function () {
        Route::post('/logout',    [AuthApiController::class, 'logout']);
        Route::get('/me',         [AuthApiController::class, 'me']);
        Route::post('/fcm-token', [AuthApiController::class, 'updateFcmToken']);
    });

    // 주문
    Route::prefix('orders')->group(function () {
        Route::get('/',                [OrderApiController::class, 'index']);
        Route::get('/{order_number}',  [OrderApiController::class, 'show']);
    });

    // 처방전
    Route::prefix('prescriptions')->group(function () {
        Route::get('/',                           [PrescriptionApiController::class, 'index']);
        Route::post('/upload',                    [PrescriptionApiController::class, 'upload']);
        Route::get('/{rx_number}',               [PrescriptionApiController::class, 'show']);
    });

    // 공지사항
    Route::prefix('notices')->group(function () {
        Route::get('/',         [NoticeApiController::class, 'index']);
        Route::get('/{notice}', [NoticeApiController::class, 'show']);
    });

    // 문의하기
    Route::prefix('inquiries')->group(function () {
        Route::get('/',                        [InquiryApiController::class, 'index']);
        Route::post('/',                       [InquiryApiController::class, 'store']);
        Route::get('/{inquiry}',               [InquiryApiController::class, 'show']);
        Route::post('/{inquiry}/messages',     [InquiryApiController::class, 'addMessage']);
    });

    // 채팅 (웹 ChatController 공용)
    Route::prefix('chat')->group(function () {
        Route::get('/rooms',                  [ChatController::class, 'rooms']);
        Route::post('/rooms',                 [ChatController::class, 'createRoom']);
        Route::get('/rooms/{room}/messages',  [ChatController::class, 'messages']);
        Route::post('/rooms/{room}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/rooms/{room}/read',     [ChatController::class, 'markRead']);
    });

    // ── 팝빌 API ─────────────────────────────────────────────
    Route::prefix('popbill')->group(function () {

        // 세금계산서
        Route::prefix('taxinvoice')->group(function () {
            Route::get('/balance',        [TaxinvoiceController::class, 'balance']);
            Route::get('/url',            [TaxinvoiceController::class, 'url']);
            Route::get('/search',         [TaxinvoiceController::class, 'search']);
            Route::get('/info',           [TaxinvoiceController::class, 'info']);
            Route::get('/popup-url',      [TaxinvoiceController::class, 'popupUrl']);
            Route::get('/print-url',      [TaxinvoiceController::class, 'printUrl']);
            Route::post('/regist-issue',  [TaxinvoiceController::class, 'registIssue']);
            Route::post('/cancel-issue',  [TaxinvoiceController::class, 'cancelIssue']);
            Route::post('/sync',          [TaxinvoiceController::class, 'sync']);
        });

        // 현금영수증
        Route::prefix('cashbill')->group(function () {
            Route::get('/balance',        [CashbillController::class, 'balance']);
            Route::get('/url',            [CashbillController::class, 'url']);
            Route::get('/search',         [CashbillController::class, 'search']);
            Route::get('/info',           [CashbillController::class, 'info']);
            Route::get('/popup-url',      [CashbillController::class, 'popupUrl']);
            Route::get('/print-url',      [CashbillController::class, 'printUrl']);
            Route::get('/order-receipts', [CashbillController::class, 'orderReceipts']);
            Route::post('/regist-issue',  [CashbillController::class, 'registIssue']);
            Route::post('/revoke',        [CashbillController::class, 'revoke']);
            Route::post('/sync',          [CashbillController::class, 'sync']);
        });

        // 카카오 알림톡
        Route::prefix('kakao')->group(function () {
            Route::get('/balance',      [KakaoController::class, 'balance']);
            Route::get('/templates',    [KakaoController::class, 'templates']);
            Route::get('/plus-friends', [KakaoController::class, 'plusFriends']);
            Route::get('/search',       [KakaoController::class, 'search']);
            Route::get('/messages',     [KakaoController::class, 'messages']);
            Route::get('/sent-list-url',[KakaoController::class, 'sentListUrl']);
            Route::get('/template-url', [KakaoController::class, 'templateUrl']);
            Route::post('/send-ats',    [KakaoController::class, 'sendAts']);
        });

        // 문자(SMS/LMS)
        Route::prefix('message')->group(function () {
            Route::get('/balance',         [MessageController::class, 'balance']);
            Route::get('/sender-numbers',  [MessageController::class, 'senderNumbers']);
            Route::get('/search',          [MessageController::class, 'search']);
            Route::get('/messages',        [MessageController::class, 'messages']);
            Route::get('/sent-list-url',   [MessageController::class, 'sentListUrl']);
            Route::post('/send-sms',       [MessageController::class, 'sendSms']);
            Route::post('/send-lms',       [MessageController::class, 'sendLms']);
            Route::post('/send-xms',       [MessageController::class, 'sendXms']);
            Route::post('/cancel-reserve', [MessageController::class, 'cancelReserve']);
        });

        // 팩스
        Route::prefix('fax')->group(function () {
            Route::get('/balance',         [FaxController::class, 'balance']);
            Route::get('/sender-numbers',  [FaxController::class, 'senderNumbers']);
            Route::get('/search',          [FaxController::class, 'search']);
            Route::get('/history',         [FaxController::class, 'history']);
            Route::get('/messages',        [FaxController::class, 'messages']);
            Route::get('/sent-list-url',   [FaxController::class, 'sentListUrl']);
            Route::post('/send',           [FaxController::class, 'send']);
            Route::post('/cancel-reserve', [FaxController::class, 'cancelReserve']);
            Route::post('/sync-pending',      [FaxController::class, 'syncPending']);
            Route::post('/sync-from-popbill', [FaxController::class, 'syncFromPopbill']);
        });
    });

    // Pusher 채널 인증 (모바일 Bearer 토큰용)
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        $room = \App\Models\ChatRoom::find(
            (int) preg_replace('/.*chat\./', '', $request->channel_name ?? '')
        );
        if (!$room || !$room->users()->where('user_id', $request->user()->id)->exists()) {
            abort(403);
        }
        $pusher = new \Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            ['cluster' => config('broadcasting.connections.pusher.options.cluster'), 'useTLS' => true]
        );
        $auth = json_decode($pusher->authorizeChannel($request->channel_name, $request->socket_id), true);
        return response()->json($auth);
    });
});
