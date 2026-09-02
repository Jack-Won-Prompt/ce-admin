<?php

namespace App\Http\Controllers;

use App\Models\TossPayment;
use App\Support\OrderGridExtras;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PG정산내역 — 토스페이먼츠 (요청서 7쪽, 2026-08-31 · 이름 2026-09-02).
 *
 * 지금까지 토스 결제는 정산/회계의 「가상계좌 매칭」 탭에만 있었다. 거기는 발급하고
 * 입금을 기다리는 자리라, 이미 끝난 결제나 카드 결제를 되짚어 볼 곳이 없었다.
 *
 * 입금 내역과 다른 것을 본다 — 그쪽은 통장에 들어온 돈이고(팝빌 계좌조회), 이쪽은
 * PG 를 거친 결제다. 가상계좌로 받은 돈은 둘 다에 나타나는데, 한쪽은 「토스가 발급하고
 * 확인해 준 결제」이고 다른 쪽은 「통장에 찍힌 줄」이다. 둘이 어긋나면 그것이 곧 봐야
 * 할 일이라, 한 화면에 합치지 않는다.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $dateFrom = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to',   today()->toDateString());

        $query = TossPayment::with([
                'order.patient', 'order.prescription.billingOffice',
                'order.items.lots', 'order.operationUser',
            ])
            ->whereBetween(\DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('toss_order_id', 'like', "%{$kw}%")
                ->orWhere('customer_name', 'like', "%{$kw}%")
                ->orWhere('account_number', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows   = $query->get();
        $extras = OrderGridExtras::forPatients($rows->pluck('order.patient_id'));

        $gridData = $rows->map(function (TossPayment $t) use ($extras) {
            $o = $t->order;

            return [
                'id'         => $t->id,
                'issued_at'  => $t->created_at?->format('Y-m-d H:i') ?? '',
                'order_no'   => $o?->order_number ?? '',
                'patient'    => $o?->patient?->name ?? '',
                'method'     => $t->method_label,
                'status'     => $t->status_label,
                'amount'     => (int) $t->amount,
                // 가상계좌로 받은 건만 값이 선다 — 카드는 계좌가 없다
                'bank'       => $t->bank_name,
                'account'    => $t->account_number ?? '',
                'holder'     => $t->customer_name ?? '',
                'due_date'   => $t->due_date?->format('Y-m-d') ?? '',
                'paid_at'    => $t->deposited_at?->format('Y-m-d H:i') ?? '',
                // 토스가 매기는 번호 — 토스 화면과 맞춰 볼 때 쓴다
                'toss_no'    => $t->toss_order_id ?? '',
                'pay_key'    => $t->payment_key ?? '',

                // 네 화면이 함께 쓰는 칸 — 주문이 붙은 줄에만 값이 선다
            ] + $extras->rx($o?->prescription, $o?->patient)
              + $extras->ww($o, $o?->prescription, $o?->patient)
              + $extras->of($o);
        })->values();

        $statusCounts = TossPayment::selectRaw('status, count(*) c')
            ->groupBy('status')->pluck('c', 'status')->all();

        return view('payments.index', compact('dateFrom', 'dateTo', 'gridData', 'statusCounts'));
    }
}
