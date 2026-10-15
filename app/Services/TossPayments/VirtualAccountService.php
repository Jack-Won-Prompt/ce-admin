<?php
// app/Services/TossPayments/VirtualAccountService.php
// 토스페이먼츠 가상계좌 발급 및 입금 확인

namespace App\Services\TossPayments;

use App\Models\Order;
use App\Models\TossPayment;
use Illuminate\Support\Facades\Log;

class VirtualAccountService extends TossClient
{
    // ─────────────────────────────────────────────────────────────
    // 가상계좌 발급
    // ─────────────────────────────────────────────────────────────

    /**
     * 주문에 대한 가상계좌 발급
     *
     * @return TossPayment  저장된 결제 레코드
     */
    /**
     * 사업자 상호 — 설정값이 비어 있으면 팝빌에 적어 둔 상호를 쓴다.
     *
     * **예금주는 환자 이름이다** (2026-10-13 지시). 이 값은 환자 이름을 하나도 찾지
     * 못했을 때 마지막으로 기대는 곳일 뿐이다 — PayNames::상호 가 그렇게 쓴다.
     *
     * 2026-10-12 ~ 10-13 하루 동안은 이 값이 그대로 예금주였다.
     */
    public static function 예금주(): string
    {
        $정한것 = trim((string) config('toss.virtual_account.holder'));

        if ($정한것 !== '') {
            return $정한것;
        }

        return trim((string) (config('popbill.company.corp_name') ?: config('app.name')));
    }

    public function issueVirtualAccount(Order $order): TossPayment
    {
        $bank       = config('toss.virtual_account.bank', 'IBK');
        $validHours = (int) config('toss.virtual_account.valid_hours', 72);
        $dueDate    = now()->addHours($validHours)->format('Y-m-d\TH:i:s');
        $orderId    = 'CE-' . $order->order_number . '-' . now()->format('YmdHis');
        $amount     = (int) round($order->patient_copay);

        if ($amount <= 0) {
            throw new TossApiException('본인부담금이 0원인 주문에는 가상계좌를 발급할 수 없습니다.');
        }

        // 주의: 토스 API는 validHours / dueDate 중 하나만 허용한다.
        // (둘 다 보내면 INVALID_VALID_HOURS_WITH_DUE_DATE_AND_SINGLE 400 발생)
        try {
        $response = $this->post('/v1/virtual-accounts', [
            'amount'       => $amount,
            'orderId'      => $orderId,
            'orderName'    => ($order->product_name ?? '처방조제') . ' 본인부담금',
            /* 이 값이 곧 **가상계좌 예금주**다 — 환자가 은행 앱에서 보는 「받는 사람」.

               **환자 이름을 보낸다** (2026-10-13 지시 「가상계좌는 예금주가 환자명이
               되어야 한다」).

               2026-10-12 에는 상호로 바꿨었다 — 제 이름이 뜨면 「내가 나에게 보내나」로
               읽혀 입금을 망설인다는 까닭이었다. 그 지시를 되돌린다.

               그때 고치려던 것은 사실 이름에 붙은 동명이인 꼬리였다(홍영철**A**).
               PayNames 가 Patient::실명 으로 지으므로 꼬리 없이 「홍영철」로 나간다.

               결제 화면이 보내는 값과 **같은 자리**에서 짓는다(PayNames) — 두 곳에서
               따로 지으면 같은 주문인데 예금주가 갈려 보인다. */
            'customerName' => \App\Support\PayNames::토스에보낼이름($order, 'VIRTUAL_ACCOUNT'),
            'bank'         => $bank,
            'validHours'   => $validHours,
        ]);
        } catch (TossApiException $e) {
            /* 시험 상점이 가상계좌를 열어 두지 않은 일이 있다
               (NOT_SUPPORTED_METHOD). 그러면 시험이 거기서 막혀 받는 쪽 화면·문자·
               입금 확인을 한 번도 밟지 못한다.

               **시험 모드에서만** 임의 계좌를 세워 그다음 걸음을 밟게 한다.
               운영 키에서는 그대로 되돌린다 — 진짜 돈을 받는 계좌를
               우리가 지어내면 그 돈은 어디로도 가지 않는다. */
            if (!$this->testMode) {
                throw $e;
            }

            Log::warning('[Toss][시험] 가상계좌를 임의로 세움', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return $this->mockVirtualAccount($order, $orderId, $amount, $bank, $dueDate, $e->getMessage());
        }

        /* ── 앞서 받아 둔 가상계좌를 닫는다 (2026-10-13 지시) ──────────────

           이 줄은 **한 주문에 하나**다. 새 계좌를 내면 앞 계좌의 자취가 덮여 사라지는데,
           토스 쪽에서는 앞 계좌가 그대로 살아 있다. 환자가 **먼저 받은 계좌**에 넣으면
           우리는 그 주문번호를 모르니 입금을 보지 못한다.

           새 계좌가 선 **뒤에** 닫는다 — 먼저 닫았다가 발급이 엎어지면 환자에게 넣을
           곳이 하나도 남지 않는다. */
        $앞것 = TossPayment::where('order_id', $order->id)->first();

        if ($앞것 && (string) $앞것->method === 'VIRTUAL_ACCOUNT'
            && $앞것->payment_key !== ($response['paymentKey'] ?? null)) {
            $this->가상계좌닫기($앞것, '가상계좌를 다시 발급했습니다');
        }

        /* **앞 결제의 취소 자취를 지운다** (2026-10-14 점검에서 드러남).

           이 줄은 한 주문에 하나다. 수단을 바꿔 다시 받으면 같은 줄을 덮어쓰는데,
           취소액만 남겨 두면 `Order::받은금액()` 이 「받은 돈 − 취소액」을 0 으로 읽는다.

           (E)김대한 님 EUD202610081716351 이 그랬다 —

             08:53 카드 링크 발송 → 10:48 **카드 결제 취소 웹훅**(취소액 81,000 적힘)
             → 10:47 가상계좌로 바꿔 발급(이 자리) → 12:31 **81,000원 입금**
             → 현금영수증ㆍ세금계산서ㆍ위드웍스 확정ㆍ결제완료 안내까지 모두 나감
             → 그런데 장부는 「받은 돈 0원」 (토스는 남은 금액 81,000원ㆍ취소 없음)

           결제 줄을 세우는 다른 자리(주문결제줄)는 진작 이렇게 지우고 있었는데
           (2026-09-23 무한 테스트 CASE 6), 가상계좌를 발급하는 이 자리만 빠져 있었다.

           **결제키가 달라졌을 때만** 지운다 — 같은 키로 다시 들어오는 일이 있고
           (돌아오는 화면 새로고침), 그때까지 지우면 부분취소한 금액이 없던 일이 된다. */
        $앞결제키 = $앞것?->payment_key;
        $새결제   = ($response['paymentKey'] ?? null) !== $앞결제키;

        return TossPayment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_key'    => $response['paymentKey'],
                'toss_order_id'  => $orderId,
                'method'         => 'VIRTUAL_ACCOUNT',
                'status'         => $response['status'],
                'amount'         => $amount,
                'bank'           => $response['virtualAccount']['bank']          ?? $bank,
                'account_number' => $response['virtualAccount']['accountNumber'] ?? '',
                'customer_name'  => $response['virtualAccount']['customerName']  ?? '',
                'due_date'       => $response['virtualAccount']['dueDate']       ?? $dueDate,
                'raw_response'   => $response,
            ] + ($새결제 ? [
                'cancel_amount'  => 0,
                'cancel_reason'  => null,
                'canceled_at'    => null,
                'deposited_at'   => null,
            ] : [])
        );
    }

    /**
     * 임의 가상계좌 — 시험 모드 전용.
     *
     * 번호는 진짜 계좌와 갈리도록 머리에 9 를 붙인다. 결제키도
     * SIMVA- 로 뗄어 토스에 물어볼 것과 섞이지 않게 한다 — 섞이면
     * 입금 확인이 토스로 돌아가 NOT_FOUND_PAYMENT 로 죽는다.
     */
    private function mockVirtualAccount(
        Order $order, string $orderId, int $amount, string $bank, string $dueDate, string $why
    ): TossPayment {
        $account = '9' . str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);

        return TossPayment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_key'    => 'SIMVA-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
                'toss_order_id'  => $orderId,
                'method'         => 'VIRTUAL_ACCOUNT',
                'status'         => 'WAITING_FOR_DEPOSIT',
                'amount'         => $amount,
                'bank'           => $bank,
                'account_number' => $account,
                /* 예금주 — 실제 발급과 같은 값을 적는다. 시험에서 본 것과 운영에서
                   나가는 것이 다르면 시험이 시험이 아니다 (2026-10-13 지시로 환자명). */
                'customer_name'  => \App\Support\PayNames::토스에보낼이름($order, 'VIRTUAL_ACCOUNT'),
                'due_date'       => $dueDate,
                'raw_response'   => [
                    'simulated' => true,
                    'reason'    => $why,
                    'note'      => '테스트 상점이 가상계좌를 지원하지 않아 임의 생성한 계좌입니다. '
                                . '입금 확인은 손으로 합니다.',
                ],
            ]
        );
    }

    /** 임의로 세운 계좌인가 — 토스에 물어봐야 소용없는 건이다 */
    public static function isSimulated(?TossPayment $p): bool
    {
        return $p && str_starts_with((string) $p->payment_key, 'SIMVA-');
    }

    /**
     * **입금 전 가상계좌를 닫는다** (2026-10-07 지시 — 결제전송에서 수단을 바꿀 때).
     *
     * 링크만 닫아서는 모자라다. 계좌는 토스에 72시간 살아 있고, 그 계좌로 돈이 들어오면
     * 입금 웹훅이 `toss_order_id` 로 결제 줄을 찾아 입금완료로 처리한다 — **링크 상태를
     * 보지 않는다.** 그래서 가상계좌로 안내한 뒤 링크페이로 바꿔도 환자가 옛 문자의
     * 계좌로 넣으면 그대로 들어왔다.
     *
     * 토스에는 「가상계좌 발급 취소」가 따로 없고 결제 취소 하나로 한다. 입금 전이면
     * 돌려줄 돈이 없으므로 환불 계좌를 싣지 않는다(토스 문서 확인).
     *
     * **들어온 돈은 건드리지 않는다** — 그것은 환불이고, 환불 계좌와 담당자의 손이 함께
     * 가는 일이라 PaymentCancelService 가 맡는다.
     *
     * @return array{ok:bool, message:string}
     */
    public function 가상계좌닫기(?TossPayment $결제, string $사유): array
    {
        if (! $결제 || (string) $결제->method !== 'VIRTUAL_ACCOUNT') {
            return ['ok' => true, 'message' => '닫을 가상계좌가 없습니다.'];
        }

        if ($결제->deposited_at || (string) $결제->status === 'DONE') {
            return ['ok' => false, 'message' => '이미 입금된 계좌는 닫을 수 없습니다 — 환불로 처리해야 합니다.'];
        }

        if (in_array((string) $결제->status, ['CANCELED', 'EXPIRED', 'ABORTED'], true)) {
            return ['ok' => true, 'message' => '이미 닫혀 있습니다.'];
        }

        /* 시험용으로 지어낸 계좌는 토스에 없다 — 물으면 NOT_FOUND_PAYMENT 로 죽는다 */
        if (self::isSimulated($결제)) {
            $결제->forceFill(['status' => 'CANCELED'])->save();

            return ['ok' => true, 'message' => '임의로 세운 계좌라 우리 쪽에서만 닫았습니다.'];
        }

        try {
            $답 = $this->post('/v1/payments/' . $결제->payment_key . '/cancel', [
                'cancelReason' => mb_substr($사유, 0, 200),
            ]);
        } catch (TossApiException $e) {
            Log::warning('[Toss] 입금 전 가상계좌를 닫지 못했다', [
                'order' => $결제->order_id, 'key' => $결제->payment_key, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $결제->forceFill([
            'status'       => $답['status'] ?? 'CANCELED',
            'raw_response' => $답,
        ])->save();

        Log::info('[Toss] 입금 전 가상계좌를 닫았다', [
            'order' => $결제->order_id, 'key' => $결제->payment_key, 'reason' => $사유,
        ]);

        return ['ok' => true, 'message' => '발급해 둔 가상계좌를 닫았습니다.'];
    }

    // ─────────────────────────────────────────────────────────────
    // 결제 조회
    // ─────────────────────────────────────────────────────────────

    /**
     * paymentKey 로 결제 정보 조회 후 DB 갱신.
     *
     * **입금 시각을 오늘로 덮지 않는다** (2026-10-13).
     *
     * 여태 `now()` 를 적었다. 같은 결제에 웹훅이 두 번 닿거나 뒤늦게 되살리면, 실제로
     * 돈이 들어온 날이 아니라 **마지막으로 물어본 날**이 적혔다. 그 날짜에서 급여
     * 종료일과 다음 재구매 가능일을 세므로 그만큼 밀린다.
     *
     *   · 이미 적혀 있으면 그대로 둔다
     *   · 없으면 토스가 준 승인 시각(`approvedAt`)을 쓴다 — 가상계좌는 입금 시각이다
     *   · 그것도 없을 때만 지금을 쓴다
     */
    public function fetchByPaymentKey(string $paymentKey): array
    {
        $data = $this->get('/v1/payments/' . urlencode($paymentKey));

        $줄 = TossPayment::where('payment_key', $paymentKey)->first();

        if ($줄) {
            $입금 = null;

            if (($data['status'] ?? '') === 'DONE') {
                $입금 = $줄->deposited_at;

                if (! $입금) {
                    try {
                        $입금 = filled($data['approvedAt'] ?? null)
                            ? \Illuminate\Support\Carbon::parse($data['approvedAt'])
                            : now();
                    } catch (\Throwable) {
                        $입금 = now();
                    }
                }
            }

            $줄->forceFill([
                'status'       => $data['status'],
                'raw_response' => $data,
                'deposited_at' => $입금,
            ])->save();
        }

        return $data;
    }

    /** 토스 orderId로 결제 정보 조회 */
    public function fetchByOrderId(string $tossOrderId): array
    {
        return $this->get('/v1/payments/orders/' . urlencode($tossOrderId));
    }

    /**
     * 사람이 보게 오류 목록에 세운다 (2026-10-13 지시).
     *
     * 돈이 걸린 자리에서 조용히 지나가면 아무도 모른다. 로그만으로는 찾아보지 않으면
     * 보이지 않으므로 오류 화면에 줄을 세운다.
     *
     * 여기서 나는 어떤 오류도 본래 하려던 일을 그르치지 않는다 — 삼킨다.
     */
    private static function 소리내기(string $글월): void
    {
        try {
            \App\Support\ErrorRecorder::담기(new \RuntimeException('[토스] ' . $글월));
        } catch (\Throwable $e) {
            Log::warning('[Toss] 오류 목록에 세우지 못했다', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 웹훅 처리 — 가상계좌 입금
    // ─────────────────────────────────────────────────────────────

    /** 가상계좌 입금을 알리는 이벤트 이름 (2026-09-10 확인) */
    private const 입금이벤트 = ['DEPOSIT_CALLBACK', 'VIRTUAL_ACCOUNT_DEPOSIT'];

    /**
     * 지나간 까닭 — 웹훅 로그에 그대로 적는다 (2026-09-10 지시).
     *
     * 아무것도 하지 않고 200 으로 답하는 길이 여럿이다(모르는 이벤트ㆍ이어진 결제
     * 없음ㆍsecret 불일치…). 로그에 「성공」만 남으면 무엇을 건너뛰었는지 알 수 없다.
     */
    public ?string $건너뛴까닭 = null;

    /** 남이 두드린 것으로 보이는 까닭 — 이것만은 로그에서 실패로 세운다 */
    public const 수상함 = 'secret 불일치 — 처리하지 않았습니다';

    /**
     * 입금 웹훅 처리
     *
     * **이름이 둘이다.** 토스 상점관리자의 웹훅 등록 화면에 있는 이름은
     * `DEPOSIT_CALLBACK` 이다. 코드는 여태 `VIRTUAL_ACCOUNT_DEPOSIT` 만 받고 있어,
     * 등록해 두었더라도 오는 족족 버렸을 것이다(2026-09-10 확인). 둘 다 받는다.
     *
     * **본문에 paymentKey 가 없다.** DEPOSIT_CALLBACK 은 orderIdㆍstatusㆍsecret 만
     * 실어 보낸다. 그래서 우리가 매긴 주문 번호(toss_order_id)로 건을 찾고,
     * 가상계좌를 만들 때 받아 둔 secret 과 맞춰 본다.
     *
     * {
     *   "eventType": "DEPOSIT_CALLBACK",
     *   "createdAt": "2026-09-10T12:00:00+09:00",
     *   "data": { "orderId": "...", "status": "DONE", "secret": "...", "transactionKey": "..." }
     * }
     *
     * @return TossPayment|null 매칭된 결제 레코드
     */
    /**
     * 토스가 답한 것을 그 주문의 결제 줄로 적는다 — 한 주문 한 줄 (2026-10-01).
     *
     * 여태 PaymentLinkController::record 안에만 있었다. 그런데 **입금 웹훅도 같은 일을
     * 해야 한다** — 결제를 마치고 돌아오는 화면이 끊기면 줄이 없는 채로 입금이 들어오고,
     * 그때 웹훅은 이을 줄을 찾지 못해 「이어진 결제 없음」으로 지나간다. 돈은 들어왔는데
     * 장부에 한 줄도 남지 않는다(2026-10-01 (E)이승원 · 81,000원).
     *
     * 셈을 두 곳에 적지 않으려고 이리로 옮겼다. 돌아오는 화면이 쓰던 것과 **같은 코드**다.
     */
    public function 주문결제줄(\App\Models\PaymentLink $link, array $res): TossPayment
    {
        $va = $res['virtualAccount'] ?? null;

        /* 한 주문에 한 줄이다 — toss_payments 는 order_id 가 유일하다.
           그런데 예전에는 결제키로 찾아 올렸다. 가상계좌를 먼저 발급해 둔
           주문을 고객이 카드로 내면, 같은 주문에 다른 결제키로 한 줄을 더
           넣으려 해 유일 제약에 걸렸다 — 결제는 끝난 뒤인데 돌아오는 화면이
           500 으로 죽어, 고객은 돈을 내고도 실패한 줄 알았다.

           주문으로 찾아 올린다. 가장 마지막 결제가 그 주문의 결제다. */
        /* 지운 줄까지 본다. 유일 제약은 소프트 삭제를 가리지 않는다 — 지워 둔
           가상계좌 줄이 남아 있으면 새로 넣으려다 똑같이 걸린다. 되살려 덮는다. */
        $tp    = TossPayment::withTrashed()->firstOrNew(['order_id' => $link->order_id]);
        $isNew = ! $tp->exists;

        /* 앞 결제의 취소 자취를 지울 것인가 — **결제키가 달라졌을 때만** 지운다.

           한 주문에 한 줄이라, 정정으로 환불한 뒤 새 금액을 다시 받으면 같은 줄을
           덮어 쓴다. 그런데 취소액만 남겨 두어 Order::결제기준금액() 이
           「받은 돈 − 취소액」을 0 으로 읽었다. 목록은 결제완료라는데 정정
           미리보기는 「입금 전이라 환불할 금액이 없습니다」로 갈려, 그대로 정정하면
           **실제로 받은 돈을 돌려주지 않는다**
           (2026-09-23 무한 테스트 CASE 6 에서 드러남).

           같은 결제키로 이 자리에 다시 들어오는 일이 있다(돌아오는 화면 새로고침).
           그때까지 지우면 부분취소한 금액이 없던 일이 되므로, 키가 같으면 둔다. */
        $새결제 = ($res['paymentKey'] ?? $link->payment_key) !== $tp->payment_key;

        /* ── 앞서 받아 둔 가상계좌를 닫는다 (2026-10-13 지시) ──────────────

           결제창은 누를 때마다 **새 주문번호**를 만든다 — `링크 토큰 앞 40자 + 무작위
           8자`(pay/show.blade.php 의 `newOrderId`). 가상계좌를 고르면 그때마다 계좌가
           새로 난다. 그런데 이 줄은 한 주문에 하나라 새 계좌가 앞 계좌를 덮는다.

           2026-10-12 (E)김정수C 님 건이 그랬다 —

             15:57 가상계좌 발급 → 16:02 **다시 발급**(우리 줄이 덮임)
             → 16:12 환자가 **먼저 받은 계좌**로 81,000원 입금 → 화면은 「입금 대기」

           앞 계좌를 토스에서 닫으면 그 계좌로는 넣을 수 없다. */
        /* **받은 돈이 있는 줄은 덮지 않는다** — 결제수단을 가리지 않는다.

           2026-10-13 에 이 잣대를 가상계좌 갈래 안에만 두었다. 그래서 **카드 줄은
           그대로 덮였다** — 같은 날 저녁 점검에서 드러났다.

             (E)주기철 · EUD202610011420371
               14:02 카드로 81,000원 결제 → 세금계산서ㆍ위드웍스 확정ㆍ결제완료 안내까지 나감
               14:32 같은 링크로 다시 들어온 **만료된 시도**가 돌아오는 화면(pay.done)을
                     지나며 그 줄을 EXPIRED 로 덮음 → 「받은 돈 0원」
             (E)정성만 · EUD202610131416041 도 같다(16:03 결제 → 16:32 덮임)

           돌아오는 화면은 환자가 다시 눌러도 열리고, 토스 결제창은 시간이 지나면
           만료로 돌아온다. 그때마다 이미 받은 돈이 장부에서 사라져서는 안 된다.

           들어오는 쪽도 다 낸 것이면(카드로 다시 낸 일 따위) 그때는 덮는다 —
           두 번 낸 것을 가리는 일은 DuplicatePaymentFinder 가 맡는다. */
        if ($tp->exists && ($tp->deposited_at || (string) $tp->status === 'DONE')
            && ($res['status'] ?? '') !== 'DONE') {
            Log::error('[Toss] 이미 입금된 결제 줄을 덮으려 했다 — 멈춘다', [
                'order'    => $tp->order_id,
                '수단'     => $tp->method,
                '있던것'   => $tp->toss_order_id,
                '오는것'   => $res['orderId'] ?? null,
                '오는상태' => $res['status']  ?? null,
            ]);

            self::소리내기('이미 입금된 결제 줄을 덮으려 했습니다 — 주문 '
                . ($tp->order?->order_number ?? $tp->order_id)
                . ' · 있던 것 ' . $tp->toss_order_id
                . ' · 오는 것 ' . ($res['orderId'] ?? '-')
                . ' (' . ($res['status'] ?? '-') . ')');

            return $tp;
        }

        /* ── 앞 가상계좌를 닫는다 ── */
        if ($tp->exists && $새결제 && (string) $tp->method === 'VIRTUAL_ACCOUNT') {
            $this->가상계좌닫기($tp, '가상계좌를 다시 발급했습니다');
        }

        $tp->forceFill([
            'payment_key'    => $res['paymentKey'] ?? $link->payment_key,
            'toss_order_id'  => $res['orderId'] ?? $link->toss_order_id,
            'method'         => $va ? 'VIRTUAL_ACCOUNT' : 'CARD',
            'status'         => $res['status'] ?? 'DONE',
            'amount'         => (int) ($res['totalAmount'] ?? $link->amount),
            'bank'           => $va['bankCode']      ?? null,
            'account_number' => $va['accountNumber'] ?? null,
            /* 가상계좌면 토스가 돌려준 예금주를 그대로 적는다 — 저쪽이 아는 값이 정본이다.
               카드면 토스 응답에 그 칸이 없으므로 **우리가 보낸 값**을 적는다.

               여태 여기서 `patient->name` 을 그대로 썼다. 그 값에는 `(E)` 와 동명이인
               꼬리가 붙어 있어, 보낸 것은 「최재웅」인데 우리 표에는 「(E)최재웅」이
               담겼다(2026-10-15 에 드러남). 밖으로 나가는 이름에서 그 표시를 떼기로
               한 잣대와도 어긋난다 — 짓는 자리를 PayNames 하나로 모은 까닭이다. */
            'customer_name'  => $va['customerName']
                                ?? \App\Support\PayNames::토스에보낼이름($link->order, 'CARD'),
            'due_date'       => $va['dueDate']       ?? null,
            /* **다 냈을 때만 입금 시각을 적는다** (2026-10-13 저녁 점검에서 드러남).

               여태 가상계좌가 아니면 상태를 보지 않고 `now()` 를 적었다. 그래서 만료ㆍ
               실패로 돌아온 시도에도 입금 시각이 찍혀, 정산 화면이 「입금 2026-10-13
               18:30」이라 적는데 상태는 만료인 줄이 생겼다. */
            'deposited_at'   => ($va || ($res['status'] ?? 'DONE') !== 'DONE')
                                ? ($va ? null : $tp->deposited_at)
                                : ($tp->deposited_at ?? now()),
            'raw_response'   => $res,
            'deleted_at'     => null,
        ] + ($새결제 ? [
            'cancel_amount'  => 0,
            'cancel_reason'  => null,
            'canceled_at'    => null,
        ] : []))->save();

        return $tp;
    }

    public function handleDepositWebhook(array $payload): ?TossPayment
    {
        $this->건너뛴까닭 = null;

        $eventType = $payload['eventType'] ?? '';

        /* 본문이 data 로 한 겹 싸여 오기도 하고 그대로 오기도 한다 — 둘 다 받는다 */
        $data = $payload['data'] ?? $payload;

        /* **이름표가 없는 입금 콜백도 받는다** (2026-09-27 운영에서 드러남).

           토스의 가상계좌 입금 콜백은 이름표(eventType) 없이 납작하게 온다.

               {"createdAt":…, "secret":…, "orderId":"CE-EUD…", "status":"DONE",
                "transactionKey":"txrd_…"}

           여태 이름표만 보고 갈라, 실제 입금이 두 번 닿았는데 둘 다 「받지 않는
           이벤트입니다 ()」로 지나갔다. 돈은 들어왔는데 우리는 몰랐다.

           이름표가 없으면 **생김새로 가린다** — 주문번호와 상태가 있으면 입금 콜백이다.
           본문을 믿고 세우는 것이 아니다: 아래에서 secret 을 맞춰 보고 토스에 다시
           물어 확인하므로, 넓게 받아도 거짓이 심기지 않는다. */
        $입금꼴 = $eventType === ''
               && isset($data['orderId'])
               && (isset($data['status']) || isset($data['secret']));

        if (! in_array($eventType, self::입금이벤트, true) && ! $입금꼴) {
            Log::info('[Toss] 웹훅 무시 (이벤트 타입 불일치)', ['type' => $eventType]);
            $this->건너뛴까닭 = "받지 않는 이벤트입니다 ({$eventType})";

            return null;
        }

        $paymentKey  = $data['paymentKey'] ?? null;
        $tossOrderId = $data['orderId']    ?? null;

        $tossPayment = $paymentKey
            ? TossPayment::where('payment_key', $paymentKey)->first()
            : null;

        /* paymentKey 가 없으면 우리가 매긴 주문 번호로 찾는다 — DEPOSIT_CALLBACK 의 길이다 */
        if (! $tossPayment && $tossOrderId) {
            $tossPayment = TossPayment::where('toss_order_id', $tossOrderId)->latest('id')->first();
        }

        /* ── 주문번호로 못 찾으면 **결제키로 다시 찾는다** (2026-10-13 지시) ────

           가상계좌 입금 콜백에는 결제키가 실려 오지 않는다 — 주문번호와 secret 뿐이다.
           그래서 주문번호가 어긋나면 더 찾아볼 데가 없었다. 토스에 그 주문번호로 물으면
           결제키를 알려 주므로, 그것으로 한 번 더 찾는다. */
        if (! $tossPayment && $tossOrderId && ! $paymentKey) {
            try {
                $저쪽 = $this->fetchByOrderId($tossOrderId);
                $paymentKey = $저쪽['paymentKey'] ?? null;

                if ($paymentKey) {
                    $tossPayment = TossPayment::where('payment_key', $paymentKey)->first();
                }
            } catch (\Throwable $e) {
                Log::warning('[Toss] 주문번호로 결제키를 받아 오지 못했다', [
                    'order' => $tossOrderId, 'error' => mb_substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        /* **결제 줄이 없으면 결제 링크로 찾는다** (2026-10-01 지시).

           결제를 마치고 돌아오는 화면(/pay/{token}/done)이 그 줄을 세운다. 그 요청이
           끊기면 — 배포로 php-fpm 이 다시 읽히거나, 시간이 넘거나, 환자가 창을 닫거나 —
           줄이 없는 채로 입금이 들어온다. 그러면 여기서 이을 것을 찾지 못해 그냥
           지나갔고, 우리는 200 으로 답하므로 토스도 다시 보내지 않았다.
           **돈은 들어왔는데 장부에 한 줄도 남지 않는다**(2026-10-01 (E)이승원 81,000원).

           링크에는 토스에 넘긴 주문 번호와 결제 열쇠가 적혀 있다 — 그것으로 찾는다.
           본문을 믿고 세우는 것이 아니다: 아래에서 토스에 다시 물어 확인한 값으로만
           줄을 세운다. */
        if (! $tossPayment) {
            /* **링크 토큰 앞 40자로도 찾는다** (2026-10-13 지시).

               결제창이 만드는 주문번호는 `링크 토큰 앞 40자 + '-' + 무작위 8자`다
               (pay/show.blade.php 의 `newOrderId`). 뒤 여덟 자는 누를 때마다 바뀌므로
               주문번호가 통째로는 어긋나도 **앞 40자는 그 링크를 가리킨다.**

               링크의 `toss_order_id` 는 마지막 시도만 담는다. 그래서 앞선 시도로 입금이
               들어오면 여태 이을 데가 없었다 — 2026-10-08 (E)임귀라 님,
               2026-10-12 (E)김정수C 님 두 건이 그렇게 「입금 대기」로 서 있었다. */
            $앞40 = $tossOrderId ? substr((string) $tossOrderId, 0, 40) : null;

            $링크 = \App\Models\PaymentLink::query()
                ->when($tossOrderId, fn ($q) => $q->where(fn ($w) => $w
                    ->where('toss_order_id', $tossOrderId)
                    ->orWhere('token', 'like', $앞40 . '%')))
                ->when(! $tossOrderId && $paymentKey, fn ($q) => $q->where('payment_key', $paymentKey))
                ->with('order')
                ->latest('id')
                ->first();

            if ($링크?->order && ($링크->payment_key || $paymentKey)) {
                try {
                    $확인 = $this->fetchByPaymentKey($링크->payment_key ?: $paymentKey);
                    $tossPayment = $this->주문결제줄($링크, $확인);
                    $paymentKey  = $tossPayment->payment_key;

                    Log::warning('[Toss] 결제 줄이 없어 링크로 세웠다 — 돌아오는 화면이 끊긴 건이다', [
                        'link' => $링크->id, 'order' => $링크->order_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[Toss] 링크로 결제 줄을 세우지 못했다', [
                        'link' => $링크->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $tossPayment) {
            /* **소리를 낸다** (2026-10-13 지시).

               여태는 `Log::warning` 한 줄로 조용히 지나갔고, 우리가 200 으로 답하므로
               토스도 다시 보내지 않았다. 입금 콜백을 잇지 못한다는 것은 **돈은 들어왔는데
               장부에 없다**는 뜻이다. 오류 목록에 세워 사람이 보게 한다. */
            Log::error('[Toss] 입금 웹훅을 이을 결제를 찾지 못했다 — 받은 돈이 장부에 없을 수 있다', [
                'key' => $paymentKey, 'order' => $tossOrderId,
            ]);

            self::소리내기('입금 웹훅을 이을 결제를 찾지 못했습니다 — 토스 주문번호 '
                . ($tossOrderId ?: '(없음)') . ' · 결제키 ' . ($paymentKey ?: '(없음)')
                . '. 받은 돈이 장부에 없을 수 있습니다.');

            $this->건너뛴까닭 = '이어진 결제를 조회하지 못했습니다 (' . ($tossOrderId ?: $paymentKey ?: '값 없음') . ')';

            return null;
        }

        /* 가상계좌를 만들 때 받아 둔 secret 과 맞춰 본다.
           틀리면 남이 두드린 것이다 — 그 자리에서 멈춘다. 받아 둔 것이 없는 옛 건은
           맞춰 볼 것이 없어 지나가되, 아래 재조회가 다시 한 번 걸러 준다. */
        $받아둔비밀 = $tossPayment->raw_response['secret'] ?? null;
        $온비밀     = $data['secret'] ?? null;

        if ($받아둔비밀 && $온비밀 && ! hash_equals((string) $받아둔비밀, (string) $온비밀)) {
            Log::warning('[Toss] 입금 웹훅 secret 불일치 — 처리하지 않는다', [
                'order' => $tossOrderId, 'payment_id' => $tossPayment->id,
            ]);
            $this->건너뛴까닭 = self::수상함;

            return null;
        }

        $paymentKey = $tossPayment->payment_key;

        // 보안: 가상계좌 입금 웹훅에는 서명이 없으므로 페이로드의 status 를 신뢰하지 않는다.
        // paymentKey 로 토스 API 를 재조회해 실제 결제 상태로만 갱신한다. (위조 웹훅 방어)
        try {
            $verified = $this->fetchByPaymentKey($paymentKey);  // API 조회 + DB 갱신
        } catch (TossApiException $e) {
            Log::error('[Toss] 입금 웹훅 API 재검증 실패 — 상태 미변경', [
                'payment_key' => $paymentKey,
                'error'       => $e->getMessage(),
            ]);
            $this->건너뛴까닭 = '토스에 재조회하지 못했습니다 — ' . $e->getMessage();

            return null;
        }

        $tossPayment->refresh();

        Log::info('[Toss] 입금 웹훅 처리 완료 (API 재검증)', [
            'payment_key' => $paymentKey,
            'order_id'    => $tossPayment->order_id,
            'status'      => $verified['status'] ?? null,
        ]);

        /* 입금이 취소된 알림도 온다(status=CANCELED). 되돌리는 일은 담당자의 손을
           거쳐 돌므로 여기서 건드리지 않되, 조용히 지나가지도 않는다 — 돈이 들어온
           줄 알고 이미 서류가 나갔을 수 있다. */
        if (in_array($verified['status'] ?? '', ['CANCELED', 'PARTIAL_CANCELED'], true)) {
            /* 로그만 남기던 것을 고친다 (2026-09-16 지시). 서버 로그는 아무도 보지
               않는다 — 결제 줄과 주문을 사실에 맞추고 담당자에게 알린다. */
            app(PaymentCancelSync::class)
                ->맞추기($tossPayment, $verified, '가상계좌 입금 취소 웹훅');
        }

        /* 돈이 들어왔으면 청구전략이 정한 세무 서류를 낸다.
           담당자가 통장을 보고 세운 것과 같은 일이다 — 부르는 곳만 다르다.
           웹훅이 실패로 끝나면 토스가 다시 보내므로, 발행에서 나는 오류가 그 재시도를
           부르지 않게 여기서 삼킨다(자동 발행은 스스로 두 번 내지 않는다). */
        if ($tossPayment->is_done && $tossPayment->order) {
            /* 돈이 들어온 날이 곧 모든 서류 발행일이다 — 거기서 급여 종료일과 다음 재구매
               가능일을 센다(2026-09-09 확정). 세무 서류보다 먼저 세운다: 서류에 그 날짜가
               실린다. */
            \App\Support\BenefitDates::onPaid($tossPayment->order);

            try {
                app(\App\Services\DepositAutoIssue::class)->run($tossPayment->order, '토스 웹훅');
            } catch (\Throwable $e) {
                Log::warning('[Toss] 입금 후 자동 발행 실패', [
                    'order_id' => $tossPayment->order_id, 'error' => $e->getMessage(),
                ]);
            }

            /* 입금이 확인됐음을 환자에게 알린다 (2026-09-18 운영 시험) */
            try {
                app(\App\Services\PaymentDoneNotice::class)->send($tossPayment->order->refresh());
            } catch (\Throwable $e) {
                Log::warning('[Toss] 결제 완료 안내 실패', [
                    'order_id' => $tossPayment->order_id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $tossPayment;
    }
}
