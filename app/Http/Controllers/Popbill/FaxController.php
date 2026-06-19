<?php

namespace App\Http\Controllers\Popbill;

use App\Http\Controllers\Controller;
use App\Models\FaxHistory;
use App\Services\Popbill\FaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FaxController extends Controller
{
    public function __construct(private readonly FaxService $svc) {}

    /**
     * 잔여포인트 조회
     */
    public function balance(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        try {
            $balance = $this->svc->getBalance($corpNum);
            return response()->json(['corp_num' => $corpNum, 'balance' => $balance]);
        } catch (\Throwable $e) {
            Log::error('[Fax] balance 조회 실패', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * 발신번호 목록
     */
    public function senderNumbers(Request $request): JsonResponse
    {
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        try {
            $list = $this->svc->getSenderNumberList($corpNum);
            return response()->json($list);
        } catch (\Throwable $e) {
            Log::error('[Fax] senderNumbers 조회 실패', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * 팩스 전송
     *
     * 파일은 multipart/form-data 로 전송 (files[])
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'sender'           => 'required|string',
            'receivers'        => 'required|array|min:1',
            'receivers.*.rcv'  => 'required|string',
            'files'            => 'required|array|min:1',
            'files.*'          => 'required|file|mimes:pdf,tif,tiff,jpg,jpeg,gif,png|max:10240',
        ]);

        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $userId  = config('popbill.test.user_id');

        $filePaths = [];
        foreach ($request->file('files') as $file) {
            $filePaths[] = $file->getRealPath();
        }

        $receivers = [];
        foreach ($request->input('receivers') as $r) {
            $receiver        = $this->svc->newReceiver();
            $receiver->rcv   = $r['rcv'];
            $receiver->rcvnm = $r['rcvnm'] ?? '';
            $receivers[]     = $receiver;
        }

        $receiptNum = $this->svc->sendFax(
            corpNum:    $corpNum,
            sender:     $request->input('sender'),
            receivers:  $receivers,
            filePaths:  $filePaths,
            reserveDt:  $request->input('reserve_dt'),
            senderName: $request->input('sender_name'),
            title:      $request->input('title'),
            userId:     $userId,
            requestNum: $request->input('request_num'),
        );

        // 발송 이력 저장 (접수번호 발급 = API 접수 성공, 초기 state=0 대기)
        try {
            FaxHistory::create([
                'corp_num'       => $corpNum,
                'receipt_num'    => $receiptNum,
                'sender'         => $request->input('sender'),
                'sender_name'    => $request->input('sender_name'),
                'title'          => $request->input('title'),
                'receivers'      => $request->input('receivers'),
                'file_names'     => collect($request->file('files'))->map(fn($f) => $f->getClientOriginalName())->values()->all(),
                'reserve_dt'     => $request->input('reserve_dt'),
                'request_num'    => $request->input('request_num'),
                'sent_by'        => Auth::id(),
                'popbill_state'  => FaxHistory::STATE_WAIT,
                'popbill_result' => null,
                'synced_at'      => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Fax] 이력 저장 실패', ['receipt_num' => $receiptNum, 'error' => $e->getMessage()]);
        }

        return response()->json(['receipt_num' => $receiptNum]);
    }

    /**
     * DB 기반 전송 이력 목록 (우리 시스템에서 발송한 건)
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        $startDate = \Carbon\Carbon::createFromFormat('Ymd', $request->query('start_date'))->startOfDay();
        $endDate   = \Carbon\Carbon::createFromFormat('Ymd', $request->query('end_date'))->endOfDay();
        $perPage   = (int) $request->query('per_page', 15);
        $corpNum   = $request->query('corp_num', config('popbill.test.corp_num'));

        $paginator = FaxHistory::where('corp_num', $corpNum)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1));

        $list = $paginator->getCollection()->map(fn($h) => [
            'id'            => $h->id,
            'receiptNum'    => $h->receipt_num,
            'sendNum'       => $h->sender,
            'senderName'    => $h->sender_name,
            'receiveNum'    => collect($h->receivers)->pluck('rcv')->implode(', '),
            'title'         => $h->title,
            'sendDT'        => $h->created_at->format('YmdHis'),
            'state'         => $h->popbill_state,
            'result'        => $h->popbill_result,
            'syncedAt'      => $h->synced_at?->toDateTimeString(),
            'fileNames'     => $h->file_names,
            'receivers'     => $h->receivers,
        ]);

        return response()->json([
            'total' => $paginator->total(),
            'list'  => $list,
        ]);
    }

    /**
     * 미완료 건 팝빌 동기화 (state NOT IN 성공·취소)
     */
    public function syncPending(Request $request): JsonResponse
    {
        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));

        $pending = FaxHistory::where('corp_num', $corpNum)->pending()->get();

        $synced = 0;
        $errors = 0;

        foreach ($pending as $history) {
            try {
                $arr = $this->svc->getMessages($history->corp_num, $history->receipt_num, null);

                if (empty($arr)) {
                    continue;
                }

                // 전체 상태 결정
                $states = array_map(fn($s) => (int)($s->state ?? 0), $arr);
                if (in_array(FaxHistory::STATE_SENDING, $states)) {
                    $overall = FaxHistory::STATE_SENDING;
                } elseif (count(array_unique($states)) === 1 && $states[0] === FaxHistory::STATE_OK) {
                    $overall = FaxHistory::STATE_OK;
                } elseif (in_array(FaxHistory::STATE_FAIL, $states)) {
                    $overall = FaxHistory::STATE_FAIL;
                } elseif (count(array_unique($states)) === 1 && $states[0] === FaxHistory::STATE_CANCEL) {
                    $overall = FaxHistory::STATE_CANCEL;
                } else {
                    $overall = FaxHistory::STATE_WAIT;
                }

                // 결과코드: 실패 수신자 우선, 없으면 첫 번째
                $result = null;
                foreach ($arr as $s) {
                    if (isset($s->result) && $s->result !== null) {
                        $result = (int) $s->result;
                        if ((int)($s->state ?? 0) === FaxHistory::STATE_FAIL) {
                            break; // 실패 건의 코드 우선
                        }
                    }
                }

                $history->update([
                    'popbill_state'  => $overall,
                    'popbill_result' => $result,
                    'synced_at'      => now(),
                ]);
                $synced++;
            } catch (\Throwable $e) {
                Log::error('[Fax] syncPending 실패', [
                    'receipt_num' => $history->receipt_num,
                    'error'       => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return response()->json([
            'synced' => $synced,
            'errors' => $errors,
            'total'  => $pending->count(),
        ]);
    }

    /**
     * 전송내역 확인
     */
    public function messages(Request $request): JsonResponse
    {
        $request->validate(['receipt_num' => 'required|string']);
        $corpNum = $request->query('corp_num', config('popbill.test.corp_num'));
        try {
            $result = $this->svc->getMessages($corpNum, $request->query('receipt_num'), null);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('[Fax] messages 조회 실패', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * 전송내역 목록 조회 (팝빌 직접)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        try {
            $result = $this->svc->search(
                corpNum:   $request->query('corp_num', config('popbill.test.corp_num')),
                startDate: $request->query('start_date'),
                endDate:   $request->query('end_date'),
                state:     $request->query('state', []),
                page:      (int) $request->query('page', 1),
                perPage:   (int) $request->query('per_page', 20),
                order:     $request->query('order', 'D'),
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('[Fax] search 조회 실패', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * 팝빌 전체 발송 내역 → fax_histories DB 동기화
     * 팝빌 포털/외부에서 보낸 건 포함, sendDT 기준 created_at 설정
     */
    public function syncFromPopbill(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Ymd',
            'end_date'   => 'required|date_format:Ymd',
        ]);

        $corpNum = $request->input('corp_num', config('popbill.test.corp_num'));
        $start   = $request->input('start_date');
        $end     = $request->input('end_date');
        $synced  = 0;
        $errors  = 0;
        $page    = 1;
        $perPage = 100;

        do {
            try {
                $result = $this->svc->search(
                    corpNum:   $corpNum,
                    startDate: $start,
                    endDate:   $end,
                    state:     [],
                    page:      $page,
                    perPage:   $perPage,
                    order:     'D',
                );
            } catch (\Throwable $e) {
                Log::error('[Fax] syncFromPopbill Search 실패', ['error' => $e->getMessage()]);
                break;
            }

            $list  = $result->list ?? [];
            $total = (int) ($result->total ?? 0);

            foreach ($list as $item) {
                try {
                    $receiptNum = $item->receiptNum ?? null;
                    if (!$receiptNum) continue;

                    // sendDT: "YYYYMMDDHHmmss" → Carbon
                    $sendDtStr = $item->sendDT ?? $item->receiptDT ?? null;
                    $sendAt    = $sendDtStr && strlen($sendDtStr) >= 14
                        ? \Carbon\Carbon::createFromFormat('YmdHis', substr($sendDtStr, 0, 14))
                        : now();

                    // 수신자 JSON
                    $receivers = [[
                        'rcv'   => $item->receiveNum  ?? '',
                        'rcvnm' => $item->receiveName ?? '',
                    ]];

                    // 파일명 배열
                    $fileNames = is_array($item->fileNames) ? $item->fileNames : [];

                    // 상태 매핑 (팝빌: 0전송전,1전송중,2성공,3실패,4취소 → 동일)
                    $state  = isset($item->state)  ? (int) $item->state  : FaxHistory::STATE_WAIT;
                    $result2 = isset($item->result) ? (int) $item->result : null;

                    $existing = FaxHistory::where('receipt_num', $receiptNum)->first();

                    if ($existing) {
                        // 상태값 또는 결과코드가 다를 때만 업데이트
                        if ($existing->popbill_state !== $state || $existing->popbill_result !== $result2) {
                            $existing->update([
                                'popbill_state'  => $state,
                                'popbill_result' => $result2,
                                'synced_at'      => now(),
                            ]);
                        }
                    } else {
                        // 신규 생성 — created_at을 sendDT로 설정
                        $rec = new FaxHistory([
                            'corp_num'       => $corpNum,
                            'receipt_num'    => $receiptNum,
                            'sender'         => $item->sendNum    ?? '',
                            'sender_name'    => $item->senderName ?? null,
                            'title'          => $item->title      ?? null,
                            'receivers'      => $receivers,
                            'file_names'     => $fileNames,
                            'reserve_dt'     => $item->reserveDT  ?? null,
                            'request_num'    => $item->requestNum ?? null,
                            'sent_by'        => null,
                            'popbill_state'  => $state,
                            'popbill_result' => $result2,
                            'synced_at'      => now(),
                        ]);
                        $rec->created_at = $sendAt;
                        $rec->updated_at = now();
                        $rec->save();
                    }
                    $synced++;
                } catch (\Throwable $e) {
                    Log::warning('[Fax] syncFromPopbill upsert 실패', [
                        'receipt_num' => $item->receiptNum ?? null,
                        'error'       => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            $page++;
        } while (count($list) === $perPage && ($page - 1) * $perPage < $total);

        return response()->json([
            'message' => '동기화 완료',
            'synced'  => $synced,
            'errors'  => $errors,
        ]);
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
        try {
            $url = $this->svc->getSentListUrl($corpNum, null);
            return response()->json(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('[Fax] sentListUrl 오류', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
