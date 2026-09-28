<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PrescriptionAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 금액이 바뀐 건의 증빙을 **전부 다시 낸다** (2026-09-28 지시).
 *
 * 지시는 이러했다 —
 *
 *   금액 변경이 없으면 증빙은 손대지 않는다.
 *   금액이 바뀐 건은 고객이 결제해서 웹훅으로 전달받으면 증빙을 전부 다시 발행한다.
 *   원 주문을 확인해 바뀐 내역을 반영한다.
 *   **증빙이 한 장을 넘겨 생기면 안 된다.**
 *
 * 그래서 차례가 하나다 — **먼저 남김없이 무르고, 그 다음에 낸다.** 무르지 않고 내면
 * 옛 금액의 종이 옆에 새 금액의 종이가 한 장 더 붙는다. 한 주문에 같은 이름의 계산서가
 * 석 장 붙어 있던 일이 실제로 있었다(2026-09-18).
 *
 * 금액은 여기서 건드리지 않는다 — 부르기 전에 이미 주문에 반영되어 있어야 한다.
 * 그래야 이 자리를 몇 번 눌러도 같은 결과가 나온다(ReturnTopupPaid 가 한 번만 올린다).
 */
class ReturnDocsReissue
{
    /** 한 장만 남아야 하는 서류 — 셈해서 확인한다 */
    private const 한장씩 = ['tax_invoice_form', 'cash_receipt_form', 'trade_statement', 'card_sales'];

    /**
     * @return array{ok:bool, note:string, counts:array<string,int>, warnings:string[]}
     */
    public function 재발행(OrderReturn $return, ?User $사람 = null, string $왜 = ''): array
    {
        $order = $return->order;

        if (! $order) {
            return ['ok' => false, 'note' => '주문을 찾을 수 없어 증빙을 다시 내지 못했습니다.',
                    'counts' => [], 'warnings' => []];
        }

        $왜 = $왜 ?: ($return->receipt_no . ' ' . $return->typeLabel() . ' — 금액 변경');

        /* ① 옛 증빙을 남김없이 무른다.

           증빙무르기() 는 세 가지를 함께 한다 — 팝빌 세금계산서ㆍ현금영수증 취소,
           우리가 만든 종이(거래명세서ㆍ카드매출전표ㆍ양식) 지우기, 서류함의 PDF 지우기.
           공단 청구는 건드리지 않는다. 금액이 바뀐 것이지 청구를 무르는 것이 아니다. */
        $무름 = app(OrderCancellation::class)->증빙무르기($order, $왜);

        /* ② 바뀐 금액으로 다시 낸다. 부르기 전에 주문의 금액이 이미 바뀌어 있어야 한다. */
        $냄 = app(DepositAutoIssue::class)->run($order->refresh(), $왜);

        /* ③ 한 장씩만 남았는지 **센다**.

           「증빙이 한 장을 넘겨 생기면 안 된다」는 지시라, 되었다고 말만 하지 않고
           실제 장수를 세어 어긋나면 그대로 적는다. */
        $셈   = $this->장수(($order = $order->refresh()));
        $넘친것 = collect($셈)->filter(fn ($n) => $n > 1)->keys()->all();

        $경고 = array_merge($무름['warnings'] ?? [], $냄['skipped'] ?? []);

        if ($넘친것) {
            $이름 = collect($넘친것)
                ->map(fn ($k) => PrescriptionAttachment::만든서류[$k] ?? $k)->implode('ㆍ');
            $경고[] = $이름 . ' 이(가) 한 장을 넘습니다 — 서류함을 확인해 주십시오';

            Log::warning('[증빙 재발행] 서류가 한 장을 넘었다', [
                'receipt' => $return->receipt_no, 'counts' => $셈,
            ]);
        }

        $말 = sprintf('세금계산서 %s · 현금영수증 %s · 거래명세서 %s · 카드전표 %s',
            $냄['tax']       ? '재발행' : '해당 없음',
            $냄['cash']      ? '재발행' : '해당 없음',
            $냄['statement'] ? '재작성' : '해당 없음',
            ($냄['card_slip'] ?? null) ? '재작성' : '해당 없음');

        if ($경고) {
            $말 .= ' — ' . implode(' / ', array_unique($경고));
        }

        $return->forceFill([
            'docs_reissued_at'  => now(),
            'docs_reissued_by'  => $사람?->id,
            'docs_reissue_note' => mb_substr($말, 0, 500),
        ])->save();

        activity()->causedBy($사람)->performedOn($order)
            ->log("{$return->receipt_no} 증빙 재발행 — {$말}");

        return ['ok' => $넘친것 === [], 'note' => $말, 'counts' => $셈, 'warnings' => $경고];
    }

    /** 우리가 만든 서류가 지금 몇 장씩 붙어 있나 */
    private function 장수(Order $order): array
    {
        if (! $order->prescription_id) {
            return [];
        }

        return PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->whereIn('doc_type', self::한장씩)
            ->selectRaw('doc_type, COUNT(*) as n')
            ->groupBy('doc_type')
            ->pluck('n', 'doc_type')
            ->all();
    }
}
