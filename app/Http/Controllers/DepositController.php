<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\BankTransactionSplit;
use App\Models\Order;
use App\Services\BankSync;
use App\Support\OrderGridExtras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 입금 내역 (요청서 5쪽, 2026-08-31).
 *
 * 정산/회계와 따로 둔다. 그쪽은 「얼마를 받아야 하는가」를 세고, 이쪽은 「무엇이
 * 들어왔는가」를 본다 — 맞추는 일이 그 사이에 있다. 한 화면에 섞으면 받을 돈과 받은
 * 돈이 같은 표에 서서 무엇이 남았는지 읽히지 않는다.
 *
 * 탭이 셋이다(요청서 5쪽).
 *   환자      처방ㆍ처방외를 가리지 않고 본인부담금이 들어온 것
 *   기관 환급 공단ㆍ지자체가 보낸 것. 여러 환자 건이 통으로 오면 여기서 나눈다.
 *   미정산    아직 어느 주문에도 붙지 않은 입금
 *
 * 통장 내역은 팝빌 계좌조회가 긁어 온다(2026-08-31 회신). 서른 분마다 저절로 돌고,
 * 「지금 가져오기」로 곧바로 받을 수도 있다.
 */
class DepositController extends Controller
{
    public const TABS = [
        'patient' => '환자(본인부담금)',
        'agency'  => '기관 환급',
        'unpaid'  => '미정산',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'patient';

        $dateFrom = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to',   today()->toDateString());

        $query = BankTransaction::with(['order.patient', 'order.prescription.billingOffice',
                                        'order.items.lots', 'order.operationUser', 'patient', 'splits.patient'])
            ->whereBetween('trade_date', [$dateFrom, $dateTo])
            /* 나간 돈은 입금 내역이 아니다. 통장에는 함께 긁혀 오지만 이 화면이 세는
               것은 「무엇이 들어왔는가」다. */
            ->where('amount_in', '>', 0)
            ->orderByDesc('traded_at')->orderByDesc('id');

        match ($tab) {
            'agency' => $query->where('kind', BankTransaction::KIND_AGENCY),
            'unpaid' => $query->unmatched(),
            default  => $query->where(fn ($q) => $q
                            ->where('kind', BankTransaction::KIND_COPAY)
                            ->orWhere(fn ($w) => $w->whereNull('kind')->whereNotNull('order_id'))),
        };

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(fn ($s) => $s
                ->where('remark1', 'like', "%{$kw}%")
                ->orWhere('remark2', 'like', "%{$kw}%")
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$kw}%"))
                ->orWhereHas('order.patient', fn ($p) => $p->where('name', 'like', "%{$kw}%")));
        }

        $rows   = $query->get();
        $extras = OrderGridExtras::forPatients($rows->pluck('order.patient_id'));

        $gridData = $rows->map(function (BankTransaction $t) use ($extras) {
            $o = $t->order;

            return [
                'id'        => $t->id,
                // ── 통장이 준 그대로 ──────────────────────────
                'traded_at' => $t->traded_at?->format('Y-m-d H:i') ?? $t->trade_date?->format('Y-m-d') ?? '',
                'amount'    => (int) $t->amount_in,
                'balance'   => (int) $t->balance,
                // 실제 입금자명 — 환자와 다른 일이 잦다(보호자가 보낸다)
                'sender'    => $t->sender,
                'remark'    => $t->remark1 ?? '',
                // 취급점 — 어느 은행 어느 점을 거쳤는가. 같은 금액이 여럿일 때 이것으로 가린다.
                'branch'    => $t->remark3 ?? '',
                'acct'      => $t->account_number ?? '',
                'bank_memo' => $t->bank_memo ?? '',
                'deposit_no' => $t->tid,

                // ── 우리가 붙인 것 ────────────────────────────
                'kind'      => BankTransaction::KINDS[$t->kind] ?? '',
                'order_no'  => $o?->order_number ?? '',
                'patient'   => $o?->patient?->name ?? $t->patient?->name ?? '',
                'matched'   => $t->matched_at?->format('Y-m-d') ?? '',
                'staff_memo' => $t->staff_memo ?? '',
                // 나눠 적은 몫 — 기관이 통으로 보낸 건이 여기서 갈린다
                'split_n'   => $t->splits->count() ?: '',
                'split_sum' => $t->splits->count() ? (int) $t->split_total : '',
                /* 분리 합계가 원금과 어긋나면 아직 다 가르지 못한 것이다. 눈에 띄어야
                   마감 전에 채운다. */
                'split_left' => $t->splits->count() ? (int) $t->amount_in - (int) $t->split_total : '',

                // 네 화면이 함께 쓰는 칸 — 주문이 붙은 줄에만 값이 선다
            ] + $extras->rx($o?->prescription, $o?->patient)
              + $extras->ww($o, $o?->prescription, $o?->patient)
              + $extras->of($o);
        })->values();

        $counts = [
            'patient' => BankTransaction::where('amount_in', '>', 0)
                            ->where('kind', BankTransaction::KIND_COPAY)->count(),
            'agency'  => BankTransaction::where('amount_in', '>', 0)
                            ->where('kind', BankTransaction::KIND_AGENCY)->count(),
            'unpaid'  => BankTransaction::where('amount_in', '>', 0)->unmatched()->count(),
        ];

        $configured = app(BankSync::class)->configured();

        return view('deposits.index', compact(
            'tab', 'dateFrom', 'dateTo', 'gridData', 'counts', 'configured'
        ));
    }

    /**
     * 지금 가져온다.
     *
     * 서른 분마다 저절로 돌지만, 방금 들어온 돈을 곧바로 봐야 할 때가 있다.
     * 여기서는 다 모일 때까지 기다린다 — 누른 사람이 화면 앞에 있다.
     */
    public function pull(Request $request, BankSync $sync): RedirectResponse
    {
        $days = (int) $request->get('days', config('bank.sync_days', 7));

        $out = $sync->pull(today()->subDays($days), today(), wait: true);

        return $out['ok']
            ? back()->with('status', $out['message'])
            : back()->withErrors(['bank' => $out['message']]);
    }

    /**
     * 이 입금이 어느 주문의 돈인지 적는다.
     *
     * 주문을 비우면 맺음을 푼다 — 잘못 맞춘 것을 되돌릴 길이 있어야 한다.
     */
    public function match(Request $request, BankTransaction $deposit): JsonResponse
    {
        $data = $request->validate([
            'order_number' => 'nullable|string|max:50',
            'kind'         => 'nullable|in:copay,agency',
            'staff_memo'   => 'nullable|string|max:500',
        ]);

        $order = ($data['order_number'] ?? null)
            ? Order::where('order_number', $data['order_number'])->first()
            : null;

        if (($data['order_number'] ?? null) && !$order) {
            return response()->json(['success' => false, 'message' => '그 주문번호를 찾지 못했습니다.'], 422);
        }

        $deposit->update([
            'order_id'   => $order?->id,
            'patient_id' => $order?->patient_id,
            'kind'       => $data['kind'] ?? $deposit->kind,
            'matched_by' => $order ? Auth::id() : null,
            'matched_at' => $order ? now() : null,
            'staff_memo' => $data['staff_memo'] ?? $deposit->staff_memo,
        ]);

        return response()->json([
            'success' => true,
            'message' => $order ? "{$order->order_number} 에 이었습니다." : '연결을 풀었습니다.',
        ]);
    }

    /**
     * 통으로 들어온 입금을 환자별로 나눈다 (요청서 5쪽).
     *
     * 지자체는 여러 환자 건을 한 번에 보낸다. 원본 줄은 은행이 준 그대로 두고 몫만
     * 적는다 — 원본을 쪼개면 통장과 맞춰 볼 수 없게 된다.
     *
     * 보내온 것으로 통째로 갈음한다. 지운 줄을 따로 알려 오게 하면 화면과 표가 갈린다.
     */
    public function split(Request $request, BankTransaction $deposit): JsonResponse
    {
        $data = $request->validate([
            'rows'                 => 'present|array',
            'rows.*.order_number'  => 'required|string|max:50',
            'rows.*.amount'        => 'required|integer|min:1',
            'rows.*.memo'          => 'nullable|string|max:300',
            'rows.*.staff_memo'    => 'nullable|string|max:300',
        ]);

        $sum = collect($data['rows'])->sum('amount');

        if ($sum > (int) $deposit->amount_in) {
            return response()->json([
                'success' => false,
                'message' => '분리 합계('.number_format($sum).'원)가 입금액을 넘습니다.',
            ], 422);
        }

        $numbers = collect($data['rows'])->pluck('order_number')->unique();
        $orders  = Order::whereIn('order_number', $numbers)->get()->keyBy('order_number');

        if ($missing = $numbers->diff($orders->keys())->all()) {
            return response()->json([
                'success' => false,
                'message' => '찾지 못한 주문번호: ' . implode(', ', $missing),
            ], 422);
        }

        $deposit->splits()->delete();

        foreach ($data['rows'] as $row) {
            $o = $orders[$row['order_number']];

            BankTransactionSplit::create([
                'bank_transaction_id' => $deposit->id,
                'order_id'            => $o->id,
                'patient_id'          => $o->patient_id,
                'amount'              => $row['amount'],
                'memo'                => $row['memo'] ?? null,
                'staff_memo'          => $row['staff_memo'] ?? null,
                'created_by'          => Auth::id(),
            ]);
        }

        // 나눈 건은 기관 환급이다 — 환자 한 사람 몫이면 나눌 까닭이 없다
        $deposit->update(['kind' => BankTransaction::KIND_AGENCY, 'matched_at' => now(), 'matched_by' => Auth::id()]);

        $left = (int) $deposit->amount_in - $sum;

        return response()->json([
            'success' => true,
            'message' => count($data['rows']) . '건으로 분리했습니다.'
                . ($left > 0 ? ' 남은 금액 ' . number_format($left) . '원.' : ''),
        ]);
    }

    /** 나눠 적은 몫 — 창을 열 때 읽는다 */
    public function splits(BankTransaction $deposit): JsonResponse
    {
        return response()->json([
            'amount' => (int) $deposit->amount_in,
            'rows'   => $deposit->splits()->with('order')->get()->map(fn ($s) => [
                'order_number' => $s->order?->order_number ?? '',
                'patient'      => $s->patient?->name ?? '',
                'amount'       => (int) $s->amount,
                'memo'         => $s->memo ?? '',
                'staff_memo'   => $s->staff_memo ?? '',
            ]),
        ]);
    }
}
