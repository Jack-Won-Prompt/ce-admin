<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Prescription;
use App\Support\BillingStrategy;
use App\Support\DelegationGate;
use App\Support\RepurchaseWindow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 주문 하나를 위드웍스에 세운다 (2026-09-16 지시).
 *
 * 여태 창고로 보내는 길은 화면의 ［주문 생성 및 연계］ 하나뿐이었다. 그런데 결제
 * 안내는 그 길을 거치지 않고도 나갈 수 있어(결제전송 단추), **돈은 받았는데 창고에는
 * 주문이 없는 건**이 조용히 쌓였다 — 실제로 결제ㆍ세금계산서까지 끝나고 판매번호가
 * 없는 주문이 운영에 있었다.
 *
 * 그래서 결제 안내를 보내는 자리에서도 이 서비스를 쓴다. 보낼 수 있는 상태이면
 * 먼저 창고에 세우고, 모자란 것이 있으면 무엇이 모자란지 적어 돌려준다.
 *
 * 창고로 보낼 내용은 **한 곳에서만 만든다**. 두 곳이 따로 만들면 어느 길로 갔느냐에
 * 따라 저쪽에 다른 값이 서고, 그 어긋남은 출고 뒤에야 드러난다.
 */
class WithworksLink
{
    /** 저쪽 주소와 열쇠 — 없으면 아무것도 하지 않는다 */
    private function 저쪽(): ?array
    {
        $url   = rtrim((string) config('services.demoworks.api_url'), '/');
        $token = config('services.demoworks.token');

        return ($url && $token) ? ['url' => $url, 'token' => $token] : null;
    }

    /**
     * 창고로 보내려면 무엇이 더 있어야 하는가.
     *
     * 화면의 문 여섯(검수ㆍ동의ㆍ총계ㆍ한도ㆍ수량ㆍ배송지) 가운데 **서버가 혼자
     * 가릴 수 있는 것**만 본다. 총계ㆍ한도처럼 화면이 셈하는 것은 여기서 보지 않는다 —
     * 그것은 담당자가 ［주문 생성 및 연계］를 누를 때 화면이 막는다.
     *
     * @return array<string> 비어 있으면 보낼 수 있다
     */
    public function 모자란것(Order $order): array
    {
        $모자람 = [];
        $rx     = $order->prescription;

        if (! $this->저쪽()) {
            $모자람[] = '위드웍스 연동 설정';
        }

        if ($rx) {
            if ($why = RepurchaseWindow::block($rx)) { $모자람[] = $why; }
            if ($why = DelegationGate::block($rx))   { $모자람[] = $why; }
        }

        if (blank($this->배송지($order))) {
            $모자람[] = '받는 주소';
        }

        if (blank($order->shipping_recipient)) {
            $모자람[] = '받는 사람';
        }

        if (! $this->품목($order)) {
            /* 제품 코드가 없는 줄만 담겨 있어도 여기로 온다 — 저쪽은 코드로 받는다 */
            $모자람[] = '제품 코드가 있는 주문 제품';
        }

        return $모자람;
    }

    /**
     * 창고에 세운다.
     *
     * 이미 서 있으면 아무것도 하지 않는다 — 두 번 보내면 저쪽에 판매주문이 하나 더 선다.
     *
     * @return array{ok:bool, so_no:?string, skipped:bool, message:string, missing:array<string>}
     */
    public function 연계(Order $order): array
    {
        if ($order->withworks_so_no) {
            return ['ok' => true, 'so_no' => $order->withworks_so_no, 'skipped' => true,
                    'message' => '이미 창고에 등록된 주문입니다.', 'missing' => []];
        }

        $모자람 = $this->모자란것($order);
        if ($모자람) {
            return ['ok' => false, 'so_no' => null, 'skipped' => false, 'missing' => $모자람,
                    'message' => '창고로 보내지 못했습니다 — ' . implode(' · ', $모자람)];
        }

        $저쪽 = $this->저쪽();
        $내용 = $this->창고내용($order);

        try {
            $res  = Http::withToken($저쪽['token'])->timeout(15)->asForm()
                        ->post("{$저쪽['url']}/api/v1/ce-admin/so_store", $내용);
            $body = $res->json();
        } catch (\Throwable $e) {
            Log::error('[위드웍스 연계] 닿지 못했습니다', [
                'order' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'so_no' => null, 'skipped' => false, 'missing' => [],
                    'message' => '위드웍스 서버에 연결할 수 없습니다.'];
        }

        /* 보낸 것을 그대로 남긴다 — 저쪽 화면과 나란히 견주려는 것이다 */
        WithworksNotice::sent('주문 등록', 'so_store', $내용, $body);

        if (! ($res->successful() && ($body['success'] ?? false))) {
            $말 = $body['message'] ?? "HTTP {$res->status()}";

            return ['ok' => false, 'so_no' => null, 'skipped' => false, 'missing' => [],
                    'message' => "위드웍스 연계 실패 — {$말}"];
        }

        $결과 = $body['result'] ?? [];
        $soNo = $결과['so_no'] ?? null;

        $담을것 = [];
        if ($soNo)                      { $담을것['withworks_so_no'] = $soNo; }
        if ($결과['so_id'] ?? null)     { $담을것['withworks_so_id'] = $결과['so_id']; }
        if ($담을것) {
            try { $order->update($담을것); } catch (\Throwable) {}
        }

        activity()->causedBy(Auth::user())->performedOn($order)
            ->log("위드웍스 판매주문 연계: {$soNo}");

        return ['ok' => true, 'so_no' => $soNo, 'skipped' => false, 'missing' => [],
                'message' => "위드웍스 판매주문 {$soNo} 을 등록했습니다."];
    }

    /** 받는 주소 — 주문에 적힌 것이 먼저고, 없으면 처방전에 적힌 것 */
    private function 배송지(Order $order): ?string
    {
        $전체 = $order->shipping_address
            ?: ($order->prescription?->address_ocr ?: null);

        if (! $전체) {
            return null;
        }

        /* 우리 주문은 기본주소와 상세주소를 **합쳐** 한 칸에 담는다 — 주문 화면에서
           읽기 좋기 때문이다. 그런데 창고는 둘을 따로 받아 스스로 합친다. 합친 것을
           기본주소 자리에 그대로 보내면 상세가 두 번 붙는다 —
           「…테헤란로 152 강남파이낸스센터 17층 강남파이낸스센터 17층」.

           주문 화면이 부르는 길은 화면이 쥔 값으로 두 칸을 나눠 덮으므로 괜찮았다.
           그러나 「결제전송」을 손으로 눌러 창고 줄을 세우는 길(PaymentLinkController)은
           덮을 것이 없어 이 값을 그대로 보냈다 (2026-09-25 무한테스트 3회차에서 드러남).

           뒤에 붙어 있는 상세를 떼어 기본만 보낸다. 떼고 남는 것이 없으면 —
           상세만 적힌 주문이다 — 합친 것을 그대로 보낸다. 주소가 사라지면 물건이
           어디로도 가지 못한다. */
        $상세 = trim((string) $order->shipping_address_detail);

        if ($상세 !== '' && str_ends_with($전체, $상세)) {
            $기본 = rtrim(mb_substr($전체, 0, mb_strlen($전체) - mb_strlen($상세)));

            if ($기본 !== '') {
                return $기본;
            }
        }

        return $전체;
    }

    /** 창고로 보낼 제품 줄 — 코드가 없는 줄은 뺀다(저쪽은 코드로 받는다) */
    private function 품목(Order $order): array
    {
        return $order->items()
            ->get()
            ->map(fn ($i) => [
                'item_code'  => (string) ($i->product_code ?? ''),
                'qty'        => (int) ($i->quantity ?: 1),
                'unit_price' => (int) round((float) ($i->insurance_price ?: $i->product_price ?: 0)),
            ])
            ->filter(fn ($i) => $i['item_code'] !== '')
            ->values()
            ->all();
    }

    /**
     * 창고로 보낼 내용 — **이 꼴 하나만 쓴다**.
     *
     * 화면에서 부르는 길(PrescriptionController)은 화면이 들고 있는 값으로 몇 칸을
     * 덮어쓴다. 적어 두었지만 아직 저장되지 않은 값이 있기 때문이다.
     *
     * @param array<string,mixed> $덮어쓸것 화면이 들고 있는 값
     */
    public function 창고내용(Order $order, array $덮어쓸것 = []): array
    {
        $rx      = $order->prescription;
        $patient = $order->patient ?? $rx?->patient;

        $기본 = [
            'ce_order_number'         => $order->order_number,
            'rx_number'               => $rx?->rx_number,
            // 환자 정보 (거래처·배송지 자동 등록용)
            'patient_name'            => $patient?->name ?? $rx?->patient_name_ocr ?? '환자',
            'patient_mobile'          => $patient?->mobile,
            'patient_zipcode'         => $order->shipping_postcode ?? $rx?->postcode,
            // 배송지 — 저쪽은 기본과 상세를 따로 받아 스스로 합친다
            'shipping_address'        => $this->배송지($order),
            'shipping_address_detail' => $order->shipping_address_detail ?? $rx?->address_detail,
            'delivery_date'           => $order->ship_request_date?->format('Y-m-d'),
            // 콜로플라스트 거래처 id — 테스트와 운영이 다르다(설정 화면에서 관리)
            'ho_account_id'           => config('services.demoworks.account_id'),
            /* 창고 「비고」 — 창고에 전할 말이 있으면 그것, 없으면 등록자 메모 */
            'remark'                  => $order->warehouse_note ?: $rx?->admin_note,
            'items'                   => $this->품목($order),
            /* 판매 유형 — 위드웍스와는 End User Direct 로만 주고받는다 */
            'so_type'                 => config('services.demoworks.so_type', '5001'),
            'recipient_name'          => $order->shipping_recipient,
            'billing_strategy'        => $this->청구전략코드($rx),
            /* 수량은 낱개로 센다 — 밝히지 않으면 저쪽이 박스로 읽어 열 배가 된다 */
            'qty_unit'                => 'EA',
            /* 확정은 창고에서 한다. 우리는 등록까지만 한다 */
            'confirm'                 => false,
        ];

        /* 널로 덮지 않는다 — 화면이 안 보낸 칸은 주문에 담긴 값을 그대로 쓴다 */
        foreach ($덮어쓸것 as $칸 => $값) {
            if ($값 !== null && $값 !== '') { $기본[$칸] = $값; }
        }

        return $기본;
    }

    /** 우리 청구전략을 위드웍스 코드로 옮긴다 */
    public function 청구전략코드(?Prescription $rx): ?int
    {
        if (! $rx) {
            return null;
        }

        /* 자격을 아직 고르지 않은 건은 열쇠가 null 이다 — 널로 배열을 찾으면 PHP 가
           나무란다. 빈 글자로 바꾸면 표에 없는 열쇠가 되어 기본값으로 내려간다. */
        $key  = BillingStrategy::key($rx->counsel_acc_add_type, $rx->benefit_class) ?? '';
        $mode = config('services.demoworks.mode') === 'production' ? 'production' : 'test';
        $conf = (array) config("services.withworks_billing_strategy.{$mode}", []);

        $id = ((array) ($conf['map'] ?? []))[$key] ?? $conf['default'] ?? null;

        return $id === null ? null : (int) $id;
    }
}
