<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Patient;
use App\Services\MessageSender;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * 의료용품 구입 확인서 (요청서, 2026-08-31 회신).
 *
 * 한 사람이 지금까지 무엇을 얼마에 샀는지를 한 장에 모은다 — 주문 한 건이 아니라
 * 사람의 내역이라, 자리가 거래처다.
 *
 * 거래처 본인이 달라고 할 때 내준다. 담당자가 내려받아 건네거나, 문자ㆍ메일로 링크를
 * 보낸다. 링크는 서명된 것이라 로그인 없이 열리되 이레가 지나면 죽는다 — 사람에게
 * 보내는 주소가 영영 살아 있으면 그 주소를 받은 누구든 남의 구입내역을 볼 수 있다.
 */
class PurchaseConfirmController extends Controller
{
    /** 보낸 링크가 살아 있는 기간 */
    private const LINK_DAYS = 7;

    /** 담당자가 화면에서 내려받는다 */
    public function download(Patient $patient)
    {
        return $this->pdf($patient)->download($this->fileName($patient));
    }

    /**
     * 환자가 링크로 여는 자리 — 로그인이 없다.
     *
     * 서명이 맞아야 열린다(signed 미들웨어). 주소를 손으로 고쳐 남의 것을 볼 수 없다.
     */
    public function open(Patient $patient)
    {
        return $this->pdf($patient)->stream($this->fileName($patient));
    }

    /**
     * 문자나 메일로 링크를 보낸다.
     *
     * 메일은 주소가 있을 때만 고를 수 있다(요청서 회신 — 「이메일 존재하면」).
     */
    public function send(Request $request, Patient $patient, MessageSender $sender)
    {
        $data = $request->validate(['channel' => 'required|in:sms,email']);

        $link = URL::temporarySignedRoute(
            'documents.purchaseConfirm.open',
            now()->addDays(self::LINK_DAYS),
            ['patient' => $patient->id]
        );

        $text = "[콜로플라스트] {$patient->name}님, 요청하신 의료용품 구입 확인서입니다.\n"
              . $link . "\n"
              . self::LINK_DAYS . '일 뒤에는 열리지 않습니다.';

        if ($data['channel'] === 'email') {
            if (blank($patient->email)) {
                return back()->withErrors(['send' => '이메일이 없어 보내지 못했습니다.']);
            }

            try {
                \Illuminate\Support\Facades\Mail::raw($text, fn ($m) => $m
                    ->to($patient->email)->subject('의료용품 구입 확인서'));
            } catch (\Throwable $e) {
                return back()->withErrors(['send' => '메일을 보내지 못했습니다 — ' . $e->getMessage()]);
            }

            return back()->with('success', $patient->email . ' 로 보냈습니다.');
        }

        $mobile = preg_replace('/\D/', '', (string) $patient->mobile);

        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return back()->withErrors(['send' => '연락처가 없어 보내지 못했습니다.']);
        }

        $res = $sender->sendBulk('sms',
            [['rcv' => $mobile, 'rcvnm' => $patient->name, 'patient_id' => $patient->id]],
            $text, null, ['source' => 'purchase-confirm']);

        return ($res['success'] ?? false)
            ? back()->with('success', '문자를 보냈습니다.')
            : back()->withErrors(['send' => $res['message'] ?? '문자를 보내지 못했습니다.']);
    }

    /** 서식대로 그린다 */
    private function pdf(Patient $patient)
    {
        return Pdf::loadView('documents.purchase_confirm', $this->data($patient))
            ->setPaper('a4', 'landscape');
    }

    /**
     * 종이에 실릴 것.
     *
     * 구입내역은 주문이다. 취소된 것은 산 것이 아니라 빼고, 아직 안 나간 것도 뺀다 —
     * 「구입했음을 확인」하는 종이라 실제로 물건이 간 건만 실린다.
     */
    private function data(Patient $patient): array
    {
        $orders = Order::where('patient_id', $patient->id)
            ->whereIn('status', ['shipping', 'delivered'])
            ->with('prescription')
            ->orderBy('created_at')
            ->get();

        $rows = $orders->map(fn (Order $o) => [
            // 구입일이 적혀 있으면 그것이 맞다 — 주문을 나중에 넣는 일이 있다
            'date'  => ($o->prescription?->buy_date
                        ? \Carbon\Carbon::parse($o->prescription->buy_date)
                        : ($o->shipped_at ?? $o->created_at))->format('Y-m-d'),
            'name'  => $o->product_name ?: '자가도뇨 소모성재료',
            'copay' => (int) $o->patient_copay,
            'nhis'  => (int) $o->nhis_amount,
            'total' => (int) ($o->total_amount ?: ((int) $o->patient_copay + (int) $o->nhis_amount)),
        ])->all();

        $company = config('popbill.company');

        return [
            'patient'    => $patient,
            /* 가린 채로 적는다. 이 종이는 우편ㆍ메일로도 도는데 뒷자리까지 찍어 두면
               그 경로 어디서든 새어 나간다. */
            'residentNo' => $patient->masked_resident_no ?: '',
            'address'    => trim(($patient->address ?? '') . ' ' . ($patient->address_detail ?? '')),
            'rows'       => $rows,
            'today'      => now()->format('Y년 m월 d일'),
            'supplier'   => [
                'corp_name' => $company['corp_name'] ?? '',
                'biz_no'    => config('popbill.test.corp_num') ?? '',
                'addr'      => $company['addr'] ?? '',
            ],
        ];
    }

    private function fileName(Patient $patient): string
    {
        return '의료용품구입확인서_' . $patient->name . '_' . now()->format('Ymd') . '.pdf';
    }
}
