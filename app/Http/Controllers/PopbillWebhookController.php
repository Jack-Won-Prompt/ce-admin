<?php

namespace App\Http\Controllers;

use App\Models\CashbillRecord;
use App\Models\FaxHistory;
use App\Models\PopbillTaxinvoice;
use App\Services\Popbill\CashbillSyncService;
use App\Services\Popbill\FaxSyncService;
use App\Services\Popbill\TaxinvoiceService;
use App\Support\WebhookLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 팝빌이 두드리는 자리 (2026-09-10 지시).
 *
 * 여태 팝빌 쪽 결과는 우리가 주기적으로 물어서 알았다 — 팩스는 5분, 현금영수증은
 * 15분ㆍ1시간마다. 그 사이에 실패한 건은 늦게 드러난다. 이제 저쪽이 알려 줄 때
 * 그 자리에서 맞춘다.
 *
 * **알려 온 값을 그대로 믿지 않는다.** 서명 없는 알림이라 위조할 수 있고, 팝빌이
 * 보내는 본문의 이름이 서비스마다 다르다. 우리가 쓰는 것은 「어느 건인가」 하나뿐이다
 * — 접수번호나 문서번호를 받아 **팝빌에 다시 물어** 확인한 값으로만 고친다.
 * 토스 웹훅과 같은 방식이다.
 *
 * 폴링은 그대로 둔다. 알림이 몇 번 실패해도 결국 맞춰지는 그물이 있어야 한다.
 */
class PopbillWebhookController extends Controller
{
    /** 받는 갈래 — 주소의 마지막 토막이다 */
    public const 갈래 = ['fax', 'sms', 'kakao', 'taxinvoice', 'cashbill'];

    public function __construct(
        private readonly FaxSyncService $faxSync,
        private readonly CashbillSyncService $cashbillSync,
        private readonly TaxinvoiceService $taxinvoice,
    ) {}

    /**
     * POST /popbill/webhook/{service}
     *
     * 로그인 없이 열린다 — 팝빌 서버가 직접 두드린다.
     */
    public function handle(Request $request, string $service): JsonResponse
    {
        if (! in_array($service, self::갈래, true)) {
            return response()->json(['message' => '모르는 구분입니다.'], 404);
        }

        $기록 = WebhookLogger::inbound('popbill', $service, $request);

        /* 팝빌이 무엇을 어떤 이름으로 보내는지는 갈래마다 다르고, JSON 이 아니라 폼으로
           올 수도 있다. 첫 알림이 로그에 그대로 남으므로 그것을 보고 좁혀 나간다. */
        $값 = $this->고르게($request);

        try {
            [$됐나, $말, $무엇] = match ($service) {
                'fax'        => $this->팩스($값),
                'cashbill'   => $this->현금영수증($값),
                'taxinvoice' => $this->세금계산서($값),
                default      => $this->적기만($service, $값),   // 문자ㆍ알림톡
            };
        } catch (\Throwable $e) {
            Log::error('[팝빌 웹훅] 처리 오류', ['service' => $service, 'error' => $e->getMessage()]);
            WebhookLogger::finish($기록, ok: false, status: 200, error: $e->getMessage());

            /* 실패로 답하면 팝빌이 다시 보낸다. 우리 쪽 사정으로 되풀이시키지 않는다 —
               못 맞춘 건은 폴링이 다시 잡는다. */
            return response()->json(['ok' => true]);
        }

        WebhookLogger::finish($기록, ok: $됐나, status: 200, error: $됐나 ? null : $말, ref: $무엇);

        return response()->json(['ok' => true]);
    }

    /**
     * 이름을 소문자로 고르게 만든다.
     *
     * 팝빌은 서비스마다 CorpNumㆍcorpNumㆍreceiptNumㆍReceiptNum 을 섞어 쓴다.
     * 이름을 맞추느라 놓치지 않도록 모두 소문자로 눕혀 두고 찾는다.
     */
    private function 고르게(Request $request): array
    {
        $본문 = $request->json()->all() ?: $request->all();

        $눕히기 = function (array $arr) use (&$눕히기) {
            $결과 = [];
            foreach ($arr as $k => $v) {
                $결과[strtolower((string) $k)] = is_array($v) ? $눕히기($v) : $v;
            }

            return $결과;
        };

        return $눕히기(is_array($본문) ? $본문 : []);
    }

    /** 여러 이름 가운데 먼저 잡히는 것 */
    private function 꺼내기(array $값, array $이름들): ?string
    {
        foreach ($이름들 as $이름) {
            $v = $값[strtolower($이름)] ?? null;
            if (is_scalar($v) && (string) $v !== '') {
                return (string) $v;
            }
        }

        return null;
    }

    /** 팩스 — 접수번호로 우리 건을 찾아 팝빌에 다시 묻는다 */
    private function 팩스(array $값): array
    {
        $접수 = $this->꺼내기($값, ['receiptNum', 'receipt_num', 'ReceiptNum']);

        if (! $접수) {
            return [false, '접수번호가 없습니다.', null];
        }

        $건 = FaxHistory::where('receipt_num', $접수)->latest('id')->first();

        if (! $건) {
            return [false, "이어진 팩스를 찾지 못했습니다 ({$접수}).", $접수];
        }

        $결과 = $this->faxSync->syncOne($건);

        return [$결과, $결과 ? null : '팝빌에 다시 묻지 못했습니다.', $접수];
    }

    /** 현금영수증 — 문서번호로 그 한 건만 다시 읽는다 */
    private function 현금영수증(array $값): array
    {
        $문서 = $this->꺼내기($값, ['mgtKey', 'mgt_key', 'MgtKey', 'ItemKey']);
        $사업자 = $this->꺼내기($값, ['corpNum', 'corp_num', 'CorpNum'])
                  ?: config('popbill.test.corp_num');

        if (! $문서) {
            return [false, '문서번호가 없습니다.', null];
        }

        if (! CashbillRecord::where('mgt_key', $문서)->exists()) {
            return [false, "이어진 현금영수증을 찾지 못했습니다 ({$문서}).", $문서];
        }

        $this->cashbillSync->refreshOne((string) $사업자, $문서);

        return [true, null, $문서];
    }

    /** 세금계산서 — 문서번호로 그 한 건만 다시 읽는다 */
    private function 세금계산서(array $값): array
    {
        $문서 = $this->꺼내기($값, ['mgtKey', 'mgt_key', 'MgtKey']);
        $사업자 = $this->꺼내기($값, ['corpNum', 'corp_num', 'CorpNum'])
                  ?: config('popbill.test.corp_num');

        if (! $문서) {
            return [false, '문서번호가 없습니다.', null];
        }

        $건 = PopbillTaxinvoice::where('mgt_key', $문서)->latest('id')->first();

        if (! $건) {
            return [false, "이어진 세금계산서를 찾지 못했습니다 ({$문서}).", $문서];
        }

        $정보 = $this->taxinvoice->getInfo((string) $사업자, $건->mgt_key_type ?: 'SELL', $문서);

        $건->update([
            'state_code'      => (int) ($정보->stateCode ?? $건->state_code),
            'state_dt'        => $정보->stateDT ?? $건->state_dt,
            'nts_confirm_num' => $정보->ntsconfirmNum ?? $건->nts_confirm_num,
            'synced_at'       => now(),
        ]);

        return [true, null, $문서];
    }

    /**
     * 문자ㆍ알림톡 — 지금은 적기만 한다.
     *
     * 보낸 내역은 묶음으로 남고(message_histories.receipt_nums) 받는 사람마다의
     * 결과를 담아 둘 칸이 아직 없다. 알림이 어떤 모양으로 오는지 로그에 쌓인 뒤에
     * 그 칸을 만든다 — 지어내어 만들면 첫 알림에 어긋난다.
     */
    private function 적기만(string $service, array $값): array
    {
        $접수 = $this->꺼내기($값, ['receiptNum', 'receipt_num', 'ReceiptNum']);

        Log::info('[팝빌 웹훅] 받았습니다 (아직 옮겨 적지 않습니다)', [
            'service' => $service, 'receipt' => $접수,
        ]);

        return [true, null, $접수];
    }
}
