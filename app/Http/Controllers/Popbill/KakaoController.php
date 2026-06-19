<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Services\Popbill\KakaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KakaoController extends Controller
{
    public function __construct(private readonly KakaoService $svc) {}

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
     * 알림톡 템플릿 목록
     */
    public function templates(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $list    = $this->svc->listTemplates($corpNum);
        return response()->json($list);
    }

    /**
     * 카카오 채널 목록
     */
    public function plusFriends(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $list    = $this->svc->listPlusFriends($corpNum);
        return response()->json($list);
    }

    /**
     * 알림톡 전송
     */
    public function sendAts(Request $request): JsonResponse
    {
        $request->validate([
            'template_code' => 'required|string',
            'sender'        => 'required|string',
            'content'       => 'required|string',
            'messages'      => 'required|array|min:1',
            'messages.*.rcv'=> 'required|string',
        ]);

        $corpNum  = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId   = config('popbill.test.user_id');

        $receivers = [];
        foreach ($request->input('messages') as $m) {
            $receiver = $this->svc->newReceiver();
            $receiver->rcv   = $m['rcv'];
            $receiver->rcvnm = $m['rcvnm'] ?? '';
            $receiver->msg   = $m['msg']   ?? $request->input('content');
            $receivers[]     = $receiver;
        }

        $receiptNum = $this->svc->sendAts(
            corpNum:      $corpNum,
            templateCode: $request->input('template_code'),
            sender:       $request->input('sender'),
            content:      $request->input('content'),
            messages:     $receivers,
            reserveDt:    $request->input('reserve_dt'),
            userId:       $userId,
            requestNum:   $request->input('request_num'),
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
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
            'state'      => 'required|array',
        ]);

        $result = $this->svc->search(
            corpNum:   $request->query('corp_num', config('popbill.test.corp_num')),
            startDate: $request->query('start_date'),
            endDate:   $request->query('end_date'),
            state:     $request->query('state'),
            item:      $request->query('item', []),
            page:      (int) $request->query('page', 1),
            perPage:   (int) $request->query('per_page', 20),
            order:     $request->query('order', 'D'),
        );

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

    /**
     * 템플릿관리 팝업 URL
     */
    public function templateUrl(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        $userId  = $request->query('user_id',  config('popbill.test.user_id'));
        $url     = $this->svc->getTemplateUrl($corpNum, $userId);
        return response()->json(['url' => $url]);
    }
}
