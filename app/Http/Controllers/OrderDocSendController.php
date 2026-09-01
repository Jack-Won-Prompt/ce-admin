<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionDocument;
use App\Services\MessageSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * 공단에도 지자체에도 내지 않는 건을 직접 처리한다.
 *
 * 처방외ㆍ산재ㆍ자동차보험은 우리가 청구하지 않는다 — 환자가 보험사나 근로복지공단에
 * 직접 낸다. 그때 필요한 것은 우리가 발행한 증빙(세금계산서ㆍ현금영수증ㆍ거래명세서)이고,
 * 지금까지는 담당자가 서류 관리에서 하나씩 내려받아 메일에 붙여 보내고 있었다.
 *
 * 문자에는 파일을 붙일 수 없어 열어 볼 수 있는 주소를 보낸다. 그 주소는 서명된 것이라
 * 정해진 날이 지나면 열리지 않고, 로그인 없이도 그 건의 서류만 보인다.
 */
class OrderDocSendController extends Controller
{
    /** 보낸 주소가 살아 있는 날수 */
    private const LINK_DAYS = 7;

    /** 이 주문에 딸린, 보낼 수 있는 서류 */
    public function list(Order $order): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->docs($order)]);
    }

    /**
     * 문자나 메일로 보낸다.
     *
     * 메일은 주소가 있을 때만 고를 수 있다. 문자는 파일을 붙이지 못하므로 주소를 보낸다.
     */
    public function send(Request $request, Order $order, MessageSender $sender): JsonResponse
    {
        $data = $request->validate(['channel' => 'required|in:sms,email']);

        $docs = $this->docs($order);
        if (! $docs) {
            return response()->json(['success' => false, 'message' => '보낼 서류가 없습니다 — 먼저 발행해 주십시오.'], 422);
        }

        $order->loadMissing('patient');
        $name  = $order->patient?->name ?: ($order->prescription?->patient_name_ocr ?: '고객');
        /* 문자에도 유형을 한 번씩만 적는다 — 같은 서류의 PDFㆍPNG 두 벌이 붙는 일이 있다 */
        $names = implode('ㆍ', array_unique(array_column($docs, 'label')));

        if ($data['channel'] === 'email') {
            $to = trim((string) $order->patient?->email);
            if ($to === '') {
                return response()->json(['success' => false, 'message' => '이메일이 없어 보내지 못했습니다.'], 422);
            }

            /* 붙일 수 있는 것을 먼저 가린다. 보내면서 붙이면 한 장도 못 붙인 채로 메일이
               나가는데, 받는 사람에게는 「증빙을 보냅니다」라 해 놓고 빈 봉투를 준 셈이다.
               파일이 이 서버에 없는 일이 실제로 있다 — 발행은 다른 서버에서 했다. */
            $ready = array_values(array_filter($docs,
                fn ($d) => $d['path'] && Storage::exists($d['path'])));

            if (! $ready) {
                return response()->json([
                    'success' => false,
                    'message' => '증빙 파일을 이 서버에서 찾지 못해 보내지 않았습니다 — 서류 관리에서 파일을 확인해 주십시오.',
                ], 422);
            }

            $missing = count($docs) - count($ready);
            /* 본문에는 유형을 한 번씩만 적는다. 같은 서류의 PDFㆍPNG 두 벌이 붙는 일이
               있어 「세금계산서ㆍ세금계산서」로 읽혔다 — 붙는 파일은 그대로 다 보낸다. */
            $names   = implode('ㆍ', array_unique(array_column($ready, 'label')));

            try {
                Mail::raw(
                    "{$name}님, 주문 {$order->order_number} 의 증빙을 보내 드립니다.\n\n{$names}\n\n콜로플라스트 코리아",
                    function ($m) use ($to, $order, $ready) {
                        $m->to($to)->subject("[콜로플라스트] 주문 {$order->order_number} 증빙");
                        foreach ($ready as $d) {
                            $m->attach(Storage::path($d['path']), ['as' => $d['file']]);
                        }
                    }
                );
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => '메일을 보내지 못했습니다 — ' . $e->getMessage()], 500);
            }

            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("증빙 메일 발송 → {$to} ({$names})");

            return response()->json([
                'success' => true,
                'message' => $missing
                    ? "{$to} 로 " . count($ready) . "건을 보냈습니다 — 다만 {$missing}건은 파일이 없어 빠졌습니다."
                    : "{$to} 로 " . count($ready) . '건을 보냈습니다.',
            ]);
        }

        $mobile = preg_replace('/\D/', '', (string) ($order->patient?->mobile ?? ''));
        if (strlen($mobile) < 9 || strlen($mobile) > 11) {
            return response()->json(['success' => false, 'message' => '연락처가 없어 보내지 못했습니다.'], 422);
        }

        $link = URL::temporarySignedRoute(
            'orders.docs.open',
            now()->addDays(self::LINK_DAYS),
            ['order' => $order->id]
        );

        $text = "[콜로플라스트] {$name}님, 주문 {$order->order_number} 의 증빙입니다.\n"
              . $names . "\n" . $link . "\n"
              . self::LINK_DAYS . '일 뒤에는 열리지 않습니다.';

        $res = $sender->sendBulk('sms',
            [['rcv' => $mobile, 'rcvnm' => $name, 'patient_id' => $order->patient_id]],
            $text, null, ['source' => 'order-docs']);

        if ($res['success'] ?? false) {
            activity()->causedBy(Auth::user())->performedOn($order)
                ->log("증빙 문자 발송 → {$mobile} ({$names})");
        }

        return response()->json([
            'success' => (bool) ($res['success'] ?? false),
            'message' => ($res['success'] ?? false) ? '문자를 보냈습니다.' : ($res['message'] ?? '문자를 보내지 못했습니다.'),
        ]);
    }

    /** 문자로 보낸 주소가 여는 자리 — 로그인 없이 그 건의 서류만 보인다 */
    public function open(Order $order): View
    {
        $order->loadMissing('patient');

        return view('documents.order_docs', [
            'order' => $order,
            'docs'  => $this->docs($order),
            'days'  => self::LINK_DAYS,
        ]);
    }

    /** 주소로 연 화면에서 한 장을 받는다 — 저장 경로를 그대로 드러내지 않는다 */
    public function file(Order $order, string $key)
    {
        foreach ($this->docs($order) as $d) {
            if ($d['key'] === $key && $d['path'] && Storage::exists($d['path'])) {
                return Storage::download($d['path'], $d['file']);
            }
        }

        abort(404);
    }

    /**
     * 보낼 수 있는 서류를 모은다.
     *
     * 우리가 발행한 증빙만이다 — 처방전ㆍ신분증처럼 환자에게서 받은 것은 돌려보낼
     * 까닭이 없고, 위임 서류는 청구하지 않는 건에 쓸 일이 없다.
     */
    private function docs(Order $order): array
    {
        $out = [];

        $docs = PrescriptionDocument::where('prescription_id', $order->prescription_id)
            ->whereIn('type', ['tax_invoice', 'cash_receipt'])
            ->orderBy('id')
            ->get();

        foreach ($docs as $d) {
            $out[] = [
                'key'   => 'doc-' . $d->id,
                'label' => $d->typeLabel(),
                'file'  => $d->original_filename ?: ($d->typeLabel() . '.pdf'),
                'path'  => $d->file_path,
                'at'    => $d->created_at?->format('Y-m-d'),
            ];
        }

        /* 거래명세서는 첨부 쪽에 쌓인다 — 물건과 함께 나가는 종이라 서류 표에 두지 않았다 */
        $stmts = PrescriptionAttachment::where('prescription_id', $order->prescription_id)
            ->where('doc_type', 'trade_statement')
            ->orderBy('id')
            ->get();

        foreach ($stmts as $s) {
            $out[] = [
                'key'   => 'att-' . $s->id,
                'label' => '거래명세서',
                'file'  => $s->file_original_name ?: '거래명세서.pdf',
                'path'  => $s->file_path,
                'at'    => $s->created_at?->format('Y-m-d'),
            ];
        }

        return $out;
    }
}
