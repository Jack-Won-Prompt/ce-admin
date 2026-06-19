<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Services\Popbill\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $svc) {}

    /**
     * 잔여포인트 조회
     */
    public function balance(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $balance = $this->svc->getBalance($corpNum);
        return response()->json(['corp_num' => $corpNum, 'balance' => $balance]);
    }

    /**
     * 발신번호 목록
     */
    public function senderNumbers(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $list    = $this->svc->getSenderNumberList($corpNum);
        return response()->json($list);
    }

    /**
     * 단문(SMS) 전송
     */
    public function sendSms(Request $request): JsonResponse
    {
        $request->validate([
            'sender'          => 'required|string',
            'content'         => 'required|string|max:90',
            'messages'        => 'required|array|min:1',
            'messages.*.rcv'  => 'required|string',
        ]);

        $corpNum    = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId     = config('popbill.test.user_id');
        $receiptNum = $this->svc->sendSms(
            corpNum:    $corpNum,
            sender:     $request->input('sender'),
            content:    $request->input('content'),
            messages:   $request->input('messages'),
            reserveDt:  $request->input('reserve_dt'),
            userId:     $userId,
            requestNum: $request->input('request_num'),
        );

        return response()->json(['receipt_num' => $receiptNum]);
    }

    /**
     * 장문(LMS) 전송
     */
    public function sendLms(Request $request): JsonResponse
    {
        $request->validate([
            'sender'         => 'required|string',
            'subject'        => 'nullable|string|max:40',
            'content'        => 'required|string|max:2000',
            'messages'       => 'required|array|min:1',
            'messages.*.rcv' => 'required|string',
        ]);

        $corpNum    = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId     = config('popbill.test.user_id');
        $receiptNum = $this->svc->sendLms(
            corpNum:    $corpNum,
            sender:     $request->input('sender'),
            subject:    $request->input('subject', ''),
            content:    $request->input('content'),
            messages:   $request->input('messages'),
            reserveDt:  $request->input('reserve_dt'),
            userId:     $userId,
            requestNum: $request->input('request_num'),
        );

        return response()->json(['receipt_num' => $receiptNum]);
    }

    /**
     * 자동(XMS) 전송
     */
    public function sendXms(Request $request): JsonResponse
    {
        $request->validate([
            'sender'         => 'required|string',
            'content'        => 'required|string',
            'messages'       => 'required|array|min:1',
            'messages.*.rcv' => 'required|string',
        ]);

        $corpNum    = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId     = config('popbill.test.user_id');
        $receiptNum = $this->svc->sendXms(
            corpNum:    $corpNum,
            sender:     $request->input('sender'),
            subject:    $request->input('subject', ''),
            content:    $request->input('content'),
            messages:   $request->input('messages'),
            reserveDt:  $request->input('reserve_dt'),
            userId:     $userId,
            requestNum: $request->input('request_num'),
        );

        return response()->json(['receipt_num' => $receiptNum]);
    }

    /**
     * 전송내역 확인
     */
    public function messages(Request $request): JsonResponse
    {
        $request->validate(['receipt_num' => 'required|string']);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $result  = $this->svc->getMessages($corpNum, $request->query('receipt_num'));
        return response()->json($result);
    }

    /**
     * 전송내역 목록 조회
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'message_type' => 'required|in:SMS,LMS,MMS,XMS',
            'start_date'   => 'required|date_format:Ymd',
            'end_date'     => 'required|date_format:Ymd',
        ]);

        $result = $this->svc->search(
            corpNum:     $request->query('corp_num', config('popbill.test.corp_num')),
            messageType: $request->query('message_type'),
            startDate:   $request->query('start_date'),
            endDate:     $request->query('end_date'),
            state:       $request->query('state', []),
            page:        (int) $request->query('page', 1),
            perPage:     (int) $request->query('per_page', 20),
            order:       $request->query('order', 'D'),
        );

        return response()->json($result);
    }

    /**
     * 예약전송 취소
     */
    public function cancelReserve(Request $request): JsonResponse
    {
        $request->validate(['receipt_num' => 'required|string']);
        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $result  = $this->svc->cancelReserve($corpNum, $request->input('receipt_num'));
        return response()->json($result);
    }

    /**
     * 전송내역 팝업 URL
     */
    public function sentListUrl(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $url     = $this->svc->getSentListUrl($corpNum, $userId);
        return response()->json(['url' => $url]);
    }
}
