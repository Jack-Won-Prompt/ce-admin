<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\TossPayment;
use App\Services\PaymentLinkService;
use App\Services\TossPayments\TossClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 결제 전송 — 환자에게 「여기서 내십시오」를 보내고, 낸 것을 받아 적는다.
 *
 * 결제 페이지(pay.show)와 그 결과(pay.done)는 환자가 여는 자리라 로그인 없이 열린다.
 * 대신 토큰으로만 찾는다 — 주문번호로 열리면 번호를 바꿔 가며 남의 주문을 볼 수 있다.
 */
class PaymentLinkController extends Controller
{
    public function __construct(private readonly PaymentLinkService $links) {}

    /** 만들고 보낸다 */
    public function store(Request $request, Order $order): JsonResponse
    {
        /* **번호는 고르게 한다 — 손으로 적지 않는다** (2026-10-07 지시).
         *
         * 여태 받는 번호가 자유 입력이었다. 그런데 가상계좌는 그 값을 쓰지 않고 늘
         * 거래처의 환자 번호로 보냈으므로(VirtualAccountForOrder::notify), 담당자가 번호를
         * 바꿔 적어도 조용히 버려졌다 — 「바꿨는데 기본 번호로 나간다」는 말이 그것이다.
         *
         * 이제 **거래처에 적힌 번호 가운데 하나를 고른다.** 번호는 서버가 거래처에서
         * 읽으므로, 화면에서 고쳐 두고 저장하지 않은 값은 애초에 나갈 수 없다.
         *
         *   mobile   → 환자 전화번호 (patients.mobile)
         *   guardian → 보호자 전화번호 (patients.phone)
         *
         * 보호자 번호가 `phone` 칸에 담기는 것은 주문 등록 화면이 그렇게 쓰기 때문이다
         * (f-mobile2 → phone). 칸 이름과 뜻이 어긋나 있어 여기 적어 둔다. */
        /* `contact` 를 **필수로 두지 않는다.** 이 고침을 올리는 순간 화면을 열어 둔
           사람의 브라우저에는 옛 코드가 남아 있어 `mobile` 만 보낸다 — 필수로 두면
           업무 중에 그 사람들이 「contact 는 필수입니다」로 막힌다. 비어 오면 환자
           번호로 본다(옛 길의 기본값과 같다). */
        $data = $request->validate([
            'method'  => 'required|in:' . implode(',', array_keys(PaymentLink::METHODS)),
            'contact' => 'nullable|in:mobile,guardian',
            /* 같은 수단으로 다시 보낼 때 담당자가 「네」를 누른 표시 (아래 resend_confirm) */
            'confirm' => 'nullable|boolean',
        ]);

        $접점 = $data['contact'] ?? 'mobile';

        if ((int) $order->total_amount <= 0) {
            return response()->json(['success' => false, 'message' => '결제할 금액이 없습니다.'], 422);
        }

        /* 이미 다 받은 건에는 보내지 않는다 (2026-09-15 지시).

           여태 결제 여부를 보지 않아, 결제가 끝난 뒤에도 링크가 또 나갔다. 그 링크는
           살아 있으므로 환자가 그것으로 한 번 더 내면 **같은 주문에 두 번 결제**된다.
           실제로 그렇게 남은 건이 있었다(EUD202609140920311 — 11:53 결제 완료,
           14:36 에 다른 번호로 링크가 한 번 더 나갔다).

           「받았는가」가 아니라 「다 받았는가」로 가린다. 정정으로 금액이 늘어 차액이
           남은 건은 더 보낼 수 있어야 한다 — 늘어난 몫은 새 링크로 청하는 것이 우리
           규칙이다(OrderCancelService::금액맞추기). */
        if ($order->다받았나()) {
            $받은것 = number_format($order->받은금액());

            return response()->json([
                'success' => false,
                'code'    => 'already_paid',
                'message' => "이미 결제가 끝난 주문입니다 ({$받은것}원). 결제 안내를 추가로 보내면 "
                           . '환자가 같은 주문에 두 번 낼 수 있습니다. 금액이 늘어 더 받아야 하는 '
                           . '건이면 주문 제품을 먼저 고치십시오 — 그때는 차액만큼 보낼 수 있습니다.',
            ], 422);
        }

        /* 위임 서명 없이는 결제 안내를 보내지 않는다 (2026-09-14 지시). 손으로 보내는
           결제전송도 같다 — 처방이 없는 주문(CE샵 등)은 위임과 무관하므로 지나간다. */
        if ($order->prescription
            && ($why = \App\Support\DelegationGate::block($order->prescription))) {
            return response()->json([
                'success' => false,
                'code'    => \App\Support\DelegationGate::CODE,
                'message' => $why,
            ], 422);
        }

        /* 창고에 아직 서지 않은 주문이면 **먼저 세운다** (2026-09-16 지시).

           결제 안내는 여태 창고 연계를 보지 않았다. 자동으로 나가는 길(주문 생성 및
           연계)은 so_store 가 성공한 뒤에만 안내를 보내는데, 손으로 누르는 이 자리는
           그 순서를 건너뛰었다 — 그래서 결제도 끝나고 세금계산서까지 나갔는데 판매번호가
           없는 주문이 운영에 남았다. 돈은 받았고 물건은 나가지 않는다.

           보낼 수 있는 상태이면 세우고, 모자란 것이 있으면 무엇이 모자란지 적어 막는다.
           창고에 없는 주문의 값을 환자에게 청할 수는 없다. */
        if (! $order->withworks_so_no) {
            $연계 = app(\App\Services\WithworksLink::class)->연계($order);

            if (! $연계['ok']) {
                return response()->json([
                    'success' => false,
                    'code'    => 'withworks_unlinked',
                    'message' => $연계['message'],
                    'missing' => $연계['missing'],
                ], 422);
            }

            $order->refresh();
        }

        /* 보낸 방법을 주문에도 적는다 (2026-09-19).

           여태 결제전송에서 가상계좌를 골라 보내도 주문의 결제수단은 상세 목록 탭에서
           고른 값(대개 링크페이) 그대로였다. 입금을 손으로 확인하면 그 값을 보고
           「카드결제 건」으로 갈라, 현금영수증은 건너뛰고 카드매출전표는 토스 승인이
           없어 그리지 못한다 — 본인부담금에 증빙이 한 장도 남지 않았다
           (EUD202609191059021 · 2026-09-19).

           받기 전에는 이 칸이 「무엇으로 안내할 것인가」이므로 방금 보낸 것으로 맞춘다.
           받은 뒤에는 「무엇으로 받았는가」라 사실이므로 건드리지 않는다. */
        if ($order->deposit_confirmed_at === null && ! $order->tossPayment?->is_done) {
            $order->update(['pay_method' => $data['method']]);
        }

        /* 보낼 번호 — 거래처에 적힌 것만 쓴다 */
        $거래처 = $order->patient;
        $번호   = preg_replace('/\D/', '', (string) ($접점 === 'guardian'
            ? ($거래처?->phone ?? '')
            : ($거래처?->mobile ?? '')));

        if ($번호 === '') {
            return response()->json([
                'success' => false,
                'code'    => 'no_contact',
                'message' => $접점 === 'guardian'
                    ? '거래처에 보호자 전화번호가 없습니다. 거래처 정보를 저장한 뒤 보내 주십시오.'
                    : '거래처에 환자 전화번호가 없습니다. 거래처 정보를 저장한 뒤 보내 주십시오.',
            ], 422);
        }

        /* **수단을 바꿔 보내면 앞서 보낸 것을 닫는다** (2026-10-07 지시).
         *
         * 여태 이 자리는 살아 있는 링크를 보지 않았다 — 자동으로 나가는 길만 보았다
         * (PrescriptionController 의 주문 확정 안내). 그래서 가상계좌로 안내한 뒤
         * 링크페이를 보내면 **두 길이 모두 열린 채** 남았고, 환자가 둘 다 쓰면 같은 주문에
         * 두 번 낸다. 10-06 두 주문에 링크가 셋ㆍ둘 서 있었다.
         *
         * 같은 수단으로 다시 보내는 것은 「한 번 더 알려 주기」일 때가 많아 막지 않되,
         * 묻는다 — 모르고 두 번 보내면 환자에게 안내가 두 통 간다.
         *
         * 묻는 답은 **200 으로** 돌려준다. 422 로 하면 화면의 apiRequest 가 오류로 알리고
         * 뒤이어 확인 창이 또 떠, 같은 말을 두 번 읽게 된다. `message` 를 비우는 것도 그
         * 까닭이다(그 자리는 success:false 면 저절로 알린다). */
        $열린것  = PaymentLink::where('order_id', $order->id)->get()
            ->filter(fn (PaymentLink $l) => $l->is_open)
            ->values();
        $같은수단 = $열린것->firstWhere('method', $data['method']);

        if ($같은수단 && ! $request->boolean('confirm')) {
            return response()->json([
                'success' => false,
                'code'    => 'resend_confirm',
                'ask'     => sprintf(
                    '%s 안내를 %s 에 이미 보냈습니다. 다시 보내시겠습니까?'
                    . PHP_EOL . PHP_EOL . '앞서 보낸 것은 닫고 새로 보냅니다.',
                    PaymentLink::METHODS[$data['method']] ?? $data['method'],
                    $같은수단->sent_at?->format('m-d H:i') ?: '-'),
            ]);
        }

        /* 앞서 발급해 둔 가상계좌는 **토스에서도 닫는다** (2026-10-07 지시).
         *
         * 링크만 닫아서는 모자라다 — 계좌는 토스에 72시간 살아 있고, 그 계좌로 돈이
         * 들어오면 입금 웹훅이 `toss_order_id` 로 결제 줄을 찾아 입금완료로 처리한다.
         * 링크 상태는 보지 않는다(VirtualAccountService::handleDepositWebhook).
         *
         * 가상계좌를 다시 고른 때는 닫지 않는다 — 살아 있는 계좌를 되살려 같은 번호를
         * 다시 안내하는 것이 이 길의 뜻이다(VirtualAccountForOrder::living).
         *
         * 못 닫으면 **보내지 않는다.** 계좌가 열린 채로 다른 수단을 안내하는 것이 더
         * 나쁘다 — 그 사고가 바로 이 지시의 까닭이다. */
        $가상 = $order->tossPayment;

        if ($data['method'] !== PaymentLink::METHOD_VIRTUAL
            && $가상 && (string) $가상->method === 'VIRTUAL_ACCOUNT'
            && ! $가상->deposited_at
            && ! in_array((string) $가상->status, ['CANCELED', 'EXPIRED', 'ABORTED', 'DONE'], true)) {
            $닫기 = app(\App\Services\TossPayments\VirtualAccountService::class)
                ->가상계좌닫기($가상, '결제 수단 변경 — ' . (PaymentLink::METHODS[$data['method']] ?? $data['method']));

            if (! ($닫기['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'code'    => 'va_close_failed',
                    'message' => '앞서 발급한 가상계좌를 닫지 못해 보내지 않았습니다 — ' . ($닫기['message'] ?? '')
                               . ' 계좌가 열린 채로 다른 수단을 안내하면 환자가 두 번 낼 수 있습니다.',
                ], 422);
            }

            activity()->causedBy(auth()->user())->performedOn($order)
                ->log('결제 수단을 ' . (PaymentLink::METHODS[$data['method']] ?? $data['method'])
                    . ' 로 바꾸며 발급해 둔 가상계좌를 닫았습니다');
        }

        /* 열려 있던 링크를 거둔다 — 지우지 않고 열리지 않게만 한다(보낸 사실은 남는다).
           한 줄씩 저장한다: PaymentLinkObserver 가 걸음을 그렇게 적는다. */
        foreach ($열린것 as $링크) {
            $링크->update(['status' => 'cancelled']);
        }

        if ($열린것->isNotEmpty()) {
            activity()->causedBy(auth()->user())->performedOn($order)->log(sprintf(
                '결제 안내를 다시 보내며 앞서 열려 있던 결제 요청 %d건을 닫았습니다 (%s)',
                $열린것->count(),
                $열린것->map(fn ($l) => PaymentLink::METHODS[$l->method] ?? $l->method)->unique()->implode('ㆍ')));
        }

        /* 보내지 못했을 때 **앞서 닫은 것을 알려 준다** (2026-10-07).

           닫는 일을 보내기보다 먼저 한다 — 계좌가 열린 채로 다른 수단이 나가는 것이
           가장 나쁘기 때문이다. 그러면 보내기가 실패한 자리에서는 「닫히기만 하고
           나가지는 않은」 상태가 되므로, 그 말을 붙여 주어야 담당자가 다시 보낸다. */
        $닫은뒤말 = fn (string $말) => $열린것->isEmpty()
            ? $말
            : trim($말) . sprintf(' 앞서 열려 있던 결제 안내 %d건은 닫혔습니다 — 다시 보내 주십시오.',
                $열린것->count());

        /* 가상계좌는 주소를 보내는 것이 아니라 계좌를 발급해 적어 보내는 것이라 길이 다르다.
           여기서 갈라 두지 않으면 담당자가 손으로 보낼 때만 계좌 없이 결제 페이지 주소가
           나간다 — 주문 연계에서 자동으로 나갈 때와 다른 것이 간다. */
        if ($data['method'] === PaymentLink::METHOD_VIRTUAL) {
            $out = app(\App\Services\VirtualAccountForOrder::class)->issueAndNotify($order, $번호);

            /* 이력은 PaymentLink 표에 쌓인다(VirtualAccountForOrder::notify).
               방금 쌓인 줄을 그대로 돌려주어야 팝오버의 이력이 그 자리에서 는다. */
            $link = $out['sent']
                ? PaymentLink::where('order_id', $order->id)->latest('id')->first()
                : null;

            return response()->json([
                'success' => $out['sent'],
                'message' => $out['sent'] ? $out['message'] : $닫은뒤말($out['message']),
                'link'    => $link ? $this->row($link) : null,
            ], $out['sent'] ? 200 : 422);
        }

        $res = $this->links->issue($order, $data['method'], $번호);

        return response()->json([
            'success' => $res['sent'],
            'message' => $res['sent'] ? $res['message'] : $닫은뒤말($res['message']),
            'link'    => $this->row($res['link']),
        ]);
    }

    /** 이 주문에 무엇을 보냈는가 */
    public function index(Order $order): JsonResponse
    {
        $rows = PaymentLink::where('order_id', $order->id)
            ->with('creator')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($l) => $this->row($l));

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    /** 잘못 보냈으면 닫는다 — 링크를 지우지 않고 열리지 않게만 한다(보낸 사실은 남는다) */
    public function cancel(PaymentLink $paymentLink): JsonResponse
    {
        if ($paymentLink->status === 'paid') {
            return response()->json(['success' => false, 'message' => '이미 결제된 건은 닫을 수 없습니다.'], 422);
        }

        $paymentLink->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => '결제 요청을 취소했습니다.', 'link' => $this->row($paymentLink)]);
    }

    // ── 환자가 여는 자리 ──────────────────────────────────

    /** 결제 페이지 */
    public function show(string $token): View
    {
        $link = PaymentLink::where('token', $token)->with('order.patient')->firstOrFail();

        // 기한이 지난 것은 그 자리에서 표시해 준다 — 눌러 봐야 안 되는 이유를 알 수 있어야 한다
        if ($link->status === 'sent' && $link->expires_at && $link->expires_at->isPast()) {
            $link->update(['status' => 'expired']);
        }

        /* **이미 받은 건은 결제창을 열지 않는다** (2026-10-02 지시).
         *
         * 여태 이 자리는 기한만 보았다. 그래서 결제를 마친 고객이 문자를 다시 열면
         * 결제창이 또 섰고, 한 번 더 내면 **같은 주문에 두 번 결제**됐다.
         * 2026-10-01 (E)윤채우 건이 그랬다 — 81,000원이 13:33:15 와 13:34:27 두 번
         * 승인됐고, toss_payments 는 한 주문 한 줄이라 앞 결제가 덮여 **장부에서
         * 사라졌다.** 우리 쪽 어디에도 자취가 남지 않아 토스에 묻기 전에는 알 수 없었다.
         *
         * 링크의 상태뿐 아니라 **주문이 다 받았는가**로도 가린다 — 가상계좌 링크를
         * 보내 두고 카드로 따로 받는 길이 있어, 링크만 보면 지나간다.
         *
         * 정정으로 금액이 늘어 차액이 남은 건은 `다받았나()` 가 거짓이라 그대로 열린다. */
        /* **취소된 주문은 결제창을 열지 않는다** (2026-10-06 지시 · SR #81).

           「취소로 보이고 취소전 발송이라도 취소되었으면 결제 안 되게」.

           취소할 때 남은 링크를 거두므로(Order::남은링크거두기) 지금은 열린 링크가
           없다 — 그래도 여기서 한 번 더 본다. 링크 상태만 보는 잣대는 거두기가 한 번
           실패하거나 다른 길로 링크가 서면 뚫린다. 돈이 들어오면 되돌리는 품이 훨씬
           크다 — 취소된 건에 받은 돈은 환불로 돌려야 하고, 그 사이 증빙까지 나간다.

           취소 전에 보낸 링크를 환자가 뒤늦게 열면 이 자리에 선다. */
        if (($link->order?->status ?? null) === 'cancelled') {
            return view('pay.done', [
                'link'    => $link,
                'ok'      => false,
                'waiting' => false,
                'message' => '취소된 주문입니다. 결제가 필요하지 않습니다. '
                           . '문의 사항은 담당자에게 연락해 주십시오.',
                'toss'    => [],
            ]);
        }

        if ($link->status === 'paid' || $link->order?->다받았나()) {
            return view('pay.done', [
                'link'    => $link,
                'ok'      => true,
                'waiting' => false,
                'message' => '이미 결제가 끝난 건입니다. 다시 결제하실 필요가 없습니다.',
                'toss'    => $link->order?->tossPayment?->raw_response ?? [],
            ]);
        }

        /* **이미 나온 계좌가 있으면 결제창을 열지 않고 그 계좌를 다시 보여 준다**
           (2026-10-06 지시).

           토스 결제창은 열 때마다 **새 가상계좌**를 낸다. 그런데 `toss_payments` 는
           한 주문 한 줄이라 새 발급이 앞의 것을 덮는다 — 그러면 옛 계좌로 들어온 돈이
           이을 곳을 잃는다.

           EUD202610010953081 이 그랬다. 환자가 링크를 네 번 열어 계좌가 네 번 나왔고,
           첫 계좌로 10-02 14:05 에 81,000원이 들어왔는데 그때 우리 줄에는 두 번째
           발급이 적혀 있었다. 입금 통지 두 건이 모두 「이어진 주문 없음」으로 지나갔고,
           나흘 뒤 담당자가 토스 화면을 보고서야 찾았다.

           잣대는 가상계좌를 자동 발급할 때 쓰는 것과 **같은 것 하나**를 쓴다
           (VirtualAccountForOrder::living) — 계좌가 있고, 입금 전이고, 기한 안이고,
           받을 돈이 그대로일 때만 「살아 있다」고 본다. 정정으로 금액이 바뀌었거나
           기한이 지났으면 거짓이 되어 결제창이 그대로 열린다.

           다른 방법으로 내고 싶은 분은 담당자를 거쳐야 한다 — 화면에 그렇게 적는다.
           계좌가 살아 있는 채로 카드로 또 내면 같은 주문에 두 번 결제된다. */
        $나온계좌 = $link->order
            ? app(\App\Services\VirtualAccountForOrder::class)->living($link->order)
            : null;

        if ($나온계좌) {
            return view('pay.done', [
                'link'    => $link,
                'ok'      => true,
                'waiting' => true,
                'message' => '',
                'toss'    => $나온계좌->raw_response ?? [],
                'note'    => '이미 받으신 계좌입니다. 다른 방법으로 결제하시려면 담당자에게 말씀해 주십시오.',
            ]);
        }

        return view('pay.show', [
            'link'      => $link,
            'order'     => $link->order,
            'clientKey' => config('toss.client_key'),
            'customerKey' => $this->customerKey($link),
            /* 시험 환경이면 화면이 스스로 승인을 부른다 (2026-09-16 지시) */
            'autoPay'   => $this->시험자동결제인가($link),
        ]);
    }

    /**
     * 시험 환경에서 결제 링크를 열면 바로 승인한다 (2026-09-16 지시).
     *
     * 결제창을 끝까지 지나려면 카드사 앱 인증과 보안프로그램 설치를 거쳐야 한다 —
     * 화면을 훑어 보는 시험에서는 그 앞에서 늘 막혔고, 그래서 **결제 뒤에 도는 일**
     * (세무 서류ㆍ카드매출전표ㆍ창고 확정)을 한 번도 끝까지 보지 못했다.
     *
     * 토스가 실제로 승인해 주는 것과 같은 꼴의 결과를 만들어, 카드로 낸 것처럼
     * 그 뒤 흐름을 그대로 돌린다. 설정에 적어 둔 시험 카드의 번호 끝자리와 카드사가
     * 매출전표에 실린다.
     *
     * **운영에서는 결코 돌지 않는다.** 사용 환경이 test 일 때만이다.
     */
    private function 시험자동결제인가(PaymentLink $link): bool
    {
        /* 문자가 못 나간 건(failed)도 연다 — is_open 이 그 판단을 쥔다 (2026-09-19) */
        /* 끌 수 있다 (2026-09-27 지시) — 꺼 두면 결제창이 열려 토스를 실제로 탄다 */
        return config('toss.env') === 'test'
            && config('toss.test_autopay')
            && $link->is_open
            && $link->method === PaymentLink::METHOD_CARD
            && (int) $link->amount > 0;
    }

    /**
     * 시험 승인 — 토스를 부르지 않고 낸 것으로 적는다 (2026-09-16 지시).
     *
     * 카드 결제가 끝났을 때 토스가 돌려주는 것과 같은 꼴을 만들어 record() 에 넘긴다.
     * 그 뒤는 실제 결제와 한 길이다 — 세무 서류, 카드매출전표, 창고 확정까지.
     */
    public function 시험승인(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        if (config('toss.env') !== 'test') {
            return response()->json(['success' => false, 'message' => '시험 환경에서만 사용할 수 있습니다.'], 403);
        }

        $link = PaymentLink::where('token', $token)->with('order.patient')->firstOrFail();

        if (! $link->is_open) {
            return response()->json(['success' => false, 'message' => '이미 처리된 결제 링크입니다.'], 422);
        }

        $카드 = config('toss.test_card');
        $번호 = (string) ($카드['number'] ?? '');
        $끝자리 = $번호 !== '' ? substr($번호, -4) : '0000';

        $paymentKey = 'TEST_' . strtoupper(\Illuminate\Support\Str::random(20));
        $tossOrder  = $link->order?->order_number ?: ('LINK' . $link->id);

        /* 토스가 카드 결제를 끝냈을 때 돌려주는 꼴 그대로 — 매출전표가 읽는 칸을 채운다 */
        $res = [
            'paymentKey'  => $paymentKey,
            'orderId'     => $tossOrder,
            'status'      => 'DONE',
            'totalAmount' => (int) $link->amount,
            'balanceAmount' => (int) $link->amount,
            'method'      => '카드',
            'approvedAt'  => now()->toIso8601String(),
            'requestedAt' => now()->toIso8601String(),
            'card' => [
                'company'        => $카드['issuer'] ?: '국민',
                'number'         => str_repeat('*', max(0, strlen($번호) - 4)) . $끝자리,
                'installmentPlanMonths' => 0,
                'isInterestFree' => false,
                'approveNo'      => str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'cardType'       => '신용',
                'ownerType'      => '개인',
                'acquireStatus'  => 'READY',
                'issuerCode'     => '11',
                'acquirerCode'   => '11',
            ],
            'receipt' => ['url' => ''],
            '_simulated' => true,
        ];

        $link->update(['payment_key' => $paymentKey, 'toss_order_id' => $tossOrder]);
        $this->links->markPaid($link, $paymentKey, $tossOrder);
        $this->record($link->refresh(), $res);

        activity()->performedOn($link->order)
            ->log('시험 환경 자동 결제 — ' . number_format((int) $link->amount) . '원 (토스에 요청하지 않았습니다)');

        return response()->json([
            'success' => true,
            'message' => number_format((int) $link->amount) . '원을 시험 승인했습니다.',
            'url'     => route('pay.done', ['token' => $token]),
        ]);
    }

    /**
     * 결제위젯에 줄 「이 사람」 표.
     *
     * 여태 비회원(ANONYMOUS)으로 열었더니 결제수단 칸이 비어 있었다 — 위젯에 브랜드페이가
     * 들어 있고, 브랜드페이는 비회원에게 내주지 않는다(「비회원은 브랜드페이 사용이
     * 어려워요」). 그래서 사람마다 하나씩 붙는 표를 준다.
     *
     * 표는 우리 앱 열쇠로 뜬 것이라 밖에서 지어낼 수 없고, 같은 환자에게는 늘 같은 것이
     * 나온다 — 다음에 다시 낼 때 저장해 둔 카드가 그대로 보인다. 환자 줄이 아직 없으면
     * 그 결제 한 건에만 붙는 표를 준다.
     */
    private function customerKey(PaymentLink $link): string
    {
        $seed = $link->order?->patient_id
            ? 'patient:' . $link->order->patient_id
            : 'link:' . $link->token;

        return 'ce-' . substr(hash_hmac('sha256', $seed, (string) config('app.key')), 0, 40);
    }

    /**
     * 토스 결제창이 끝나고 돌아오는 자리.
     *
     * 성공했다고 그대로 믿지 않는다 — 브라우저가 들고 온 값이라 누구든 만들 수 있다.
     * 서버에서 승인(confirm)까지 마쳐야 낸 것으로 적는다.
     */
    public function done(Request $request, string $token): View
    {
        $link = PaymentLink::where('token', $token)->with('order.patient')->firstOrFail();

        $paymentKey = (string) $request->query('paymentKey', '');
        $tossOrder  = (string) $request->query('orderId', '');
        $amount     = (int) $request->query('amount', 0);
        $error      = $request->query('message') ?: $request->query('code');

        /* 이미 낸 건이면 그 사실이 먼저다 (2026-09-16 고침).

           이 자리는 토스가 돌려보낼 때 paymentKey 를 달고 온다. 그것이 없으면
           여태 무조건 「결제가 완료되지 않았습니다」였다 — 그런데 시험 자동 승인은
           서버에서 이미 승인을 마치고 이 화면으로 보내므로 달고 올 것이 없다.
           결제가 끝난 링크에 「실패」가 뜨는 것은 사실과 다르다.

           새로고침으로 다시 들어오는 길도 같다 — 낸 뒤에 화면을 다시 열면 실패로
           보였다. */
        /* **이미 받은 건에 새 결제키가 달려 오면 승인하지 않는다** (2026-10-02 지시).
         *
         * 승인(confirm)을 부르지 않으면 돈은 나가지 않는다 — 토스는 이 자리에서
         * 우리가 confirm 을 불러야 비로소 승인한다. 그러니 여기가 중복 결제를 막는
         * 마지막이자 확실한 자리다.
         *
         * 같은 결제키로 다시 들어오는 길은 막지 않는다(새로고침). 그때는 아래의
         * 「이미 낸 건」으로 떨어진다. */
        if ($link->status === 'paid' && $paymentKey && $paymentKey !== $link->payment_key) {
            Log::warning('[결제전송] 이미 받은 건에 새 결제가 들어와 승인하지 않았다', [
                'link' => $link->id, 'order' => $link->order?->order_number,
                'had'  => $link->payment_key, 'got' => $paymentKey, 'amount' => $amount,
            ]);

            return view('pay.done', [
                'link'    => $link,
                'ok'      => true,
                'waiting' => false,
                'message' => '이미 결제가 끝난 건이라 이번 결제는 진행하지 않았습니다. '
                           . '카드에서 빠져나간 금액은 없습니다.',
                'toss'    => $link->order?->tossPayment?->raw_response ?? [],
            ]);
        }

        if ($link->status === 'paid' && ! $paymentKey) {
            return view('pay.done', [
                'link' => $link, 'ok' => true, 'waiting' => false, 'message' => null,
                'toss' => $link->order?->tossPayment?->raw_response ?? [],
            ]);
        }

        if (!$paymentKey || !$tossOrder) {
            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false,
                                     'message' => $error ?: '결제가 완료되지 않았습니다.']);
        }

        if ($amount !== (int) $link->amount) {
            Log::warning('[결제전송] 금액이 다르다', ['link' => $link->id, 'sent' => $link->amount, 'got' => $amount]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false, 'message' => '결제 금액이 맞지 않습니다.']);
        }

        try {
            $res = app(TossClient::class)->post('/v1/payments/confirm', [
                'paymentKey' => $paymentKey,
                'orderId'    => $tossOrder,
                'amount'     => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[결제전송] 승인 실패', ['link' => $link->id, 'error' => $e->getMessage()]);

            return view('pay.done', ['link' => $link, 'ok' => false, 'waiting' => false, 'message' => $e->getMessage()]);
        }

        /* 가상계좌는 승인이 끝나도 아직 낸 것이 아니다 — 계좌가 나왔을 뿐이고, 돈은
           환자가 은행에 넣어야 들어온다(WAITING_FOR_DEPOSIT). 여기서 「결제완료」로
           적으면 목록에서 받은 돈으로 읽히고, 담당자가 입금을 기다리지 않게 된다.
           들어온 것은 토스가 입금 웹훅으로 알려 준다 — 그때 낸 것으로 적는다. */
        $waiting = ($res['status'] ?? '') === 'WAITING_FOR_DEPOSIT';

        if ($waiting) {
            $link->update(['payment_key' => $paymentKey, 'toss_order_id' => $tossOrder]);
        } else {
            $this->links->markPaid($link, $paymentKey, $tossOrder);
        }

        $this->record($link, $res);

        return view('pay.done', [
            'link' => $link->refresh(), 'ok' => true, 'message' => null,
            'toss' => $res, 'waiting' => $waiting,
        ]);
    }

    // ── 안쪽 ──────────────────────────────────────────────

    /** 토스가 준 결과를 주문 쪽 결제 기록으로도 남긴다 — 정산은 그 표를 본다 */
    private function record(PaymentLink $link, array $res): void
    {
        $va = $res['virtualAccount'] ?? null;

        /* 교환 차액은 **원 결제 줄을 덮지 않는다** (2026-09-28 지시).

           toss_payments 는 한 주문 한 줄이고 order_id 가 유일이다. 아래에서
           firstOrNew(['order_id' => …]) 로 덮어쓰므로, 차액 4,500원이 들어오면
           37,500원 결제 줄이 4,500원으로 바뀌고 원 결제키가 사라진다 — 받은 돈이
           4,500원으로 읽히고, 원 결제를 무를 수도 없게 된다.

           차액은 접수에 따로 적고, 그 자리에서 증빙을 전부 다시 낸다. */
        $차액접수 = \App\Models\OrderReturn::where('topup_payment_link_id', $link->id)->first();

        if ($차액접수) {
            try {
                app(\App\Services\ReturnTopupPaid::class)->받음($차액접수, $link, $res);
            } catch (\Throwable $e) {
                Log::error('[결제전송] 차액 입금 처리 실패', [
                    'link' => $link->id, 'receipt' => $차액접수->receipt_no,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        /* 결제 줄을 세우는 셈은 **한 곳에 둔다** (2026-10-01).

           입금 웹훅도 같은 일을 해야 한다 — 이 화면이 끊겨 줄이 없는 채로 입금이
           들어오는 길이 있기 때문이다(2026-10-01 (E)이승원). 두 곳에서 각자 적으면
           한쪽만 고치는 날이 온다. */
        $tp    = app(\App\Services\TossPayments\VirtualAccountService::class)->주문결제줄($link, $res);
        $isNew = $tp->wasRecentlyCreated;

        /* 가상계좌를 고른 사람에게는 계좌를 문자로 한 번 더 적어 보낸다.
           이 화면을 닫으면 계좌를 다시 볼 곳이 우리 쪽에 없어, 담당자에게 전화해
           다시 묻는 일이 잦았다.
           방금 처음 담긴 때만 보낸다 — 이 자리는 새로고침으로 두 번 들어올 수 있다. */
        if ($va && $isNew) {
            app(\App\Services\PaymentLinkService::class)->sendVirtualAccount($link, $va);
        }

        /* 카드로 낸 건은 이 자리에서 다 낸 것이다 — 가상계좌처럼 기다릴 것이 없다.
           그런데 여태 여기서 아무것도 부르지 않아, 서류도 창고 확정도 돌지 않았다.
           담당자가 정산/회계에서 「입금확인」을 손으로 눌러야 그때 돌았다
           (테스트 시나리오 3.1.1ㆍ3.3 · 2026-09-03).

           가상계좌는 여기서 부르지 않는다. 계좌가 나왔을 뿐 돈은 아직 들어오지
           않았다 — 들어오면 입금 웹훅이 부른다. */
        if (! $va && $tp->order) {
            $this->afterPaid($tp->order, '카드 결제');
        }
    }

    /**
     * 돈이 들어온 뒤에 하는 일 — 서류를 내고 창고를 확정한다.
     *
     * 실패해도 결제는 이미 끝난 것이라 화면을 막지 않는다. 고객은 냈는데 「오류」를
     * 보면 다시 내려 든다 — 못 한 일은 로그와 자취에 남고, 담당자가 정산/회계에서
     * 잇는다.
     */
    private function afterPaid(\App\Models\Order $order, string $cause): void
    {
        try {
            app(\App\Services\DepositAutoIssue::class)->run($order->refresh(), $cause);
        } catch (\Throwable $e) {
            Log::warning('[결제전송] 결제 뒤 자동 처리 실패', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);
        }

        /* 결제가 끝났음을 환자에게 알린다 (2026-09-18 운영 시험에서 드러남).

           결제 완료 화면은 「영수증은 문자로 안내드립니다」라고 적어 두었는데 그 문자를
           보내는 자리가 없었다. 환자는 기다리다 담당자에게 전화했다.

           서류를 낸 뒤에 보낸다 — 증빙이 붙기 전에 알리면 담당자가 찾을 때 아직 없다.
           보내지 못해도 결제는 끝난 것이라 막지 않는다. */
        try {
            app(\App\Services\PaymentDoneNotice::class)->send($order->refresh());
        } catch (\Throwable $e) {
            Log::warning('[결제전송] 결제 완료 안내 실패', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function row(PaymentLink $l): array
    {
        /* 결제된 줄에는 **실제로 오간 돈**을 함께 싣는다 (2026-09-19 지시).

           여태 이 자리가 링크 금액과 발송 시각만 내보냈다. 결제가 언제 끝났는지,
           얼마가 들어왔는지, 뒤에 취소되었는지는 토스 결제 줄에만 있어 화면
           어디에서도 볼 수 없었다. */
        $결제 = $l->payment_key
            ? \App\Models\TossPayment::where('payment_key', $l->payment_key)->first()
            : $l->order?->tossPayment;

        $받은돈 = $결제 && $결제->is_done
            ? max(0, (int) $결제->amount - (int) ($결제->cancel_amount ?? 0))
            : null;

        /* 담당자가 손으로 확인한 입금도 함께 싣는다 (2026-09-19 시험에서 드러남).

           가상계좌ㆍ무통장입금은 토스 승인이 없어 위 값이 비어 있다. 그런 건은 돈이
           들어왔는데도 이 줄에 금액과 시각이 하나도 뜨지 않았다 — 정작 사람이
           확인한 건이라 꼭 보여야 하는 자리다. 기록은 주문에 있다.

           이 링크로 받은 건인지는 가릴 수 없으므로(사람이 통장을 보고 누른다) 이
           주문의 마지막 살아 있는 링크에만 적는다. */
        $order = $l->order;
        $손확인 = null;

        if (! $받은돈 && $order?->deposit_confirmed_at && $l->status !== 'cancelled') {
            $손확인 = [
                'amount' => (int) ($order->deposit_amount ?? $order->expectedDeposit()),
                'at'     => $order->deposit_confirmed_at->format('Y-m-d H:i'),
            ];
        }

        return [
            'id'      => $l->id,
            'method'  => $l->method_label,
            'amount'  => $l->amount,
            'status'  => $l->status,
            'status_label' => $l->status_label,
            'tone'    => $l->status_tone,
            /* 둘 다 나간 건은 둘 다 적는다 — 링크에 'alimtalk,sms' 로 담긴다 */
            'channel' => $l->channel
                ? implode('ㆍ', array_map(
                    [\App\Services\PaymentLinkService::class, '채널이름'],
                    array_filter(explode(',', $l->channel))))
                : '-',
            'receiver' => $l->receiver,
            'sent_at' => $l->sent_at?->format('Y-m-d H:i'),
            'paid_at' => $l->paid_at?->format('Y-m-d H:i'),
            'url'     => $l->url,
            'creator' => $l->creator?->name,
            'error'   => $l->error,
            'open'    => $l->is_open,

            // ── 결제 내용 — 화면이 그대로 보여 준다 ──────────────────
            'paid_amount'   => $받은돈 ?? $손확인['amount'] ?? null,
            'paid_method'   => $결제?->method_label ?: ($손확인 ? '담당자 확인' : null),
            'deposited_at'  => $결제?->deposited_at?->format('Y-m-d H:i') ?? $손확인['at'] ?? null,
            'cancelled_at'  => $결제?->canceled_at?->format('Y-m-d H:i'),
            'cancel_amount' => (int) ($결제?->cancel_amount ?? 0) ?: null,
            'cancel_reason' => $결제?->cancel_reason ?: null,
            'va_bank'       => $결제?->bank_name ?: null,
            'va_account'    => $결제?->account_number ?: null,
            'va_due'        => $결제?->due_date?->format('Y-m-d H:i'),
        ];
    }
}
