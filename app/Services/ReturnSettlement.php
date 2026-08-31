<?php

namespace App\Services;

use App\Models\OrderReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 되돌리는 건의 돈 처리 — 금액조정 주문과 마이너스 발행.
 *
 * 절차서의 마지막 칸이다. 「마이너스-환불, 현금영수증, 세금계산서 발행」과, 일반 환불에서만
 * 나오는 「주문 입력 — 판매유형: 전산판매(금액조정)」.
 *
 * ⚠ 팝빌은 운영으로 붙어 있다. 여기서 부르는 취소·발행은 국세청 신고까지 간다.
 *    사람이 단추를 눌러야만 돌게 두고, 자동으로는 절대 부르지 않는다.
 */
class ReturnSettlement
{
    /**
     * 일반 환불의 금액조정 주문을 세운다.
     *
     * 물건은 움직이지 않는다 — 자격이 바뀌어 이미 받은 돈만 되돌리는 것이라, 반품 주문을
     * 세우면 창고는 오지 않을 물건을 기다린다. 판매유형만 금액조정(1092)으로 넣는다.
     *
     * ⚠ 1092 로 넣을 때 수량·금액의 부호를 저쪽이 어떻게 읽는지는 위드웍스 쪽 규격이
     *    정한다. 지금은 유형이 마이너스를 뜻한다고 보고 수량을 양수로 보낸다.
     */
    public function adjust(OrderReturn $return): bool
    {
        $order = $return->order;

        if (!$order) {
            return $this->note($return, '주문을 찾을 수 없어 금액조정 주문을 세우지 못했습니다');
        }

        if ($return->adjust_so_no) {
            return true;   // 이미 세웠다
        }

        /* 사유가 「금액조정 없음」이면 세우지 않는다(요청서 6쪽 · 사유표가 정한다).
           물건만 바꿔 주는 교환은 돈이 그대로라 조정할 것이 없다 — 그런데도 금액조정
           주문을 세우면 없던 마이너스가 창고와 정산에 남는다.
           까닭을 적어 둔다. 아무 말 없이 지나가면 담당자는 실패한 줄 안다. */
        if (!\App\Models\ReturnReason::adjusts($return->reason_code)) {
            $return->forceFill(['credit_note' => mb_substr(
                \App\Models\OrderReturn::reasonLabel($return->reason_code)
                . ' — 금액조정을 하지 않는 사유입니다(마스터 관리 › 반품 사유).', 0, 500)])->save();

            return true;
        }

        $baseUrl = rtrim((string) config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');
        $soType  = config('services.demoworks.adjust_so_type');

        if (!$baseUrl || !$token) {
            return $this->note($return, '위드웍스 연동 설정이 없습니다');
        }

        if (!$soType) {
            return $this->note($return, '금액조정 판매유형이 비어 있습니다 — 설정 › 위드웍스에서 적으십시오');
        }

        $items = $this->items($return);

        if ($items === []) {
            return $this->note($return, '보낼 품목이 없습니다 — 제품코드를 확인하십시오');
        }

        try {
            $res = Http::withToken($token)->timeout(15)->asForm()
                ->post("{$baseUrl}/api/v1/ce-admin/so_store", [
                    // 접수번호를 주문번호로 쓴다 — 원 주문과 헷갈리지 않게
                    'ce_order_number' => $return->receipt_no,
                    'patient_name'    => $order->patient?->name ?? '환자',
                    'patient_mobile'  => $order->patient?->mobile,
                    'ho_account_id'   => config('services.demoworks.account_id'),
                    'so_type'         => $soType,
                    'remark'          => '일반 환불 (자격 변경 등) — ' . $return->receipt_no
                                       . ' / 원 주문 ' . $order->order_number,
                    'items'           => $items,
                    'qty_unit'        => 'EA',
                    'confirm'         => false,
                ]);

            $body = $res->json();

            if (!$res->successful() || !($body['success'] ?? false)) {
                return $this->note($return, '금액조정 주문 등록을 거절당했습니다: '
                    . ($body['message'] ?? ('HTTP ' . $res->status())));
            }

            $return->forceFill([
                'adjust_so_no' => mb_substr((string) ($body['result']['so_no'] ?? ''), 0, 50) ?: null,
                'adjusted_at'  => now(),
            ])->save();

            activity()->causedBy(Auth::user())->performedOn($return->order)
                ->log("금액조정 주문을 세웠습니다 ({$return->receipt_no} · {$return->adjust_so_no})");

            return true;
        } catch (\Throwable $e) {
            Log::warning('금액조정 주문 등록 실패', [
                'receipt' => $return->receipt_no, 'error' => $e->getMessage(),
            ]);

            return $this->note($return, '금액조정 주문을 보내지 못했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 마이너스 발행 — 세금계산서 · 현금영수증 · 공단 청구.
     *
     * 전부 되돌리는 건이면 이미 발행한 것을 그대로 취소한다(없던 거래의 계산서가 남으면
     * 안 된다). 부분이면 자동으로 손대지 않는다 — 절차서가 「기관 청구 서류는 최종
     * 청구분에 반영」이라고 정했고, 얼마를 남길지는 사람이 정할 일이다.
     *
     * @return array{ok: bool, note: string}
     */
    public function credit(OrderReturn $return): array
    {
        $order = $return->order;

        if (!$order) {
            $note = '주문을 찾을 수 없어 발행을 되돌리지 못했습니다';
            $this->note($return, $note);

            return ['ok' => false, 'note' => $note];
        }

        if ($return->is_partial) {
            $note = '부분 취소 — 기관 청구 서류는 최종 청구분에 반영합니다. '
                  . '이미 발행한 계산서·현금영수증은 자동으로 손대지 않았습니다.';

            $return->forceFill(['credit_issued_at' => now(), 'credit_note' => $note])->save();

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("부분 취소 — 최종 청구분 반영으로 남깁니다 ({$return->receipt_no})");

            return ['ok' => true, 'note' => $note];
        }

        /* 사유가 「발행 불포함」이면 되돌릴 발행도 없다(요청서 6쪽 · 사유표가 정한다).
           물건만 바꿔 주는 교환은 돈이 오가지 않아 처음부터 발행에 들지 않았다 —
           그런데도 취소를 부르면 팝빌에서 멀쩡한 건이 취소되고 국세청까지 간다. */
        if (!\App\Models\ReturnReason::includes($return->reason_code)) {
            $note = \App\Models\OrderReturn::reasonLabel($return->reason_code)
                  . ' — 발행 내역에 넣지 않는 사유라 되돌릴 발행이 없습니다'
                  . '(마스터 관리 › 반품 사유).';

            $return->forceFill(['credit_issued_at' => now(), 'credit_note' => $note])->save();

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("발행 불포함 사유 — 되돌릴 발행 없음 ({$return->receipt_no})");

            return ['ok' => true, 'note' => $note];
        }

        $out = app(OrderCancellation::class)->close($order, $return);

        $note = sprintf('세금계산서 %s · 현금영수증 %s · 공단청구 %s',
            ltrim($out['tax'], '!'), ltrim($out['cash'], '!'), ltrim($out['nhis'], '!'));

        $return->forceFill([
            'credit_issued_at' => now(),
            'credit_note'      => mb_substr($note, 0, 500),
        ])->save();

        return ['ok' => $out['warnings'] === [], 'note' => $note];
    }

    /**
     * 되돌리는 품목.
     *
     * 줄을 받아 둔 건은 그대로 쓰고(부분 취소가 여기서 산다), 예전에 줄 없이 접수한
     * 건은 원 주문의 품목을 그대로 쓴다.
     */
    private function items(OrderReturn $return): array
    {
        $rows = $return->items;

        if ($rows->isNotEmpty()) {
            return $rows->filter(fn ($i) => $i->product_code && $i->quantity > 0)
                ->map(fn ($i) => ['item_code' => $i->product_code, 'qty' => (int) $i->quantity])
                ->values()->all();
        }

        $order = $return->order;

        $items = $order->items
            ->filter(fn ($i) => $i->product_code)
            ->map(fn ($i) => ['item_code' => $i->product_code, 'qty' => max(1, (int) $i->quantity)])
            ->values()->all();

        if ($items === [] && $order->product_code) {
            $items = [['item_code' => $order->product_code, 'qty' => max(1, (int) $order->quantity)]];
        }

        return $items;
    }

    /** 안 된 까닭을 접수 건에 적는다 — 화면에서 보여야 사람이 마무리한다 */
    private function note(OrderReturn $return, string $message): bool
    {
        $return->forceFill(['credit_note' => mb_substr($message, 0, 500)])->save();

        return false;
    }
}
