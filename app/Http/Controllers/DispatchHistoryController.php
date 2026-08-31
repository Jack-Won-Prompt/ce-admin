<?php
// app/Http/Controllers/DispatchHistoryController.php

namespace App\Http\Controllers;

use App\Models\FaxHistory;
use App\Models\MessageHistory;
use App\Models\NhisFaxLog;
use App\Models\Order;
use App\Models\TossPayment;
use App\Models\WithworksEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 발송ㆍ발행 내역 — 밖으로 나간 것과 밖에서 온 것을 한자리에서 본다.
 *
 * 흩어져 있으면 「그때 그 건 보냈나」를 확인하러 화면 넷을 돌아야 한다. 문자ㆍ알림톡,
 * 팩스, 세금계산서ㆍ현금영수증(취소까지), 가상계좌, 공단 청구, 그리고 창고와 주고받은
 * 것을 갈래만 바꿔 같은 자리에서 훑는다.
 *
 * 갈래마다 원본이 다르므로 표의 칸도 다르다 — 억지로 같은 칸에 맞추면 무엇을 보는
 * 것인지가 흐려진다. 공통은 「언제ㆍ어느 주문ㆍ누구ㆍ어떻게 됐나」 넷이다.
 */
class DispatchHistoryController extends Controller
{
    /** 갈래와 그 이름 — 화면의 고르개가 이 차례로 세운다 */
    public const TYPES = [
        'message'         => '문자ㆍ알림톡',
        'fax'             => '팩스',
        'tax_invoice'     => '세금계산서',
        'cash_receipt'    => '현금영수증',
        'virtual_account' => '가상계좌 발행',
        'nhis'            => '공단 청구 발송',
        'withworks'       => '창고(위드웍스)',
    ];

    public function index(Request $request)
    {
        $type = $request->input('type', 'message');
        if (!isset(self::TYPES[$type])) {
            $type = array_key_first(self::TYPES);
        }

        $search   = $request->input('search');
        $dateFrom = $request->input('date_from') ?: now()->subDays(29)->format('Y-m-d');
        $dateTo   = $request->input('date_to')   ?: now()->format('Y-m-d');
        $perPage  = (int) $request->input('per_page', 20);

        /* 창고 갈래만 원본이 둘이라(보낸 기록과 받은 사건) 여기서 한 벌로 엮는다.
           나머지는 표 하나가 곧 원본이다. */
        if ($type === 'withworks') {
            $rows = $this->withworksRows($search, $dateFrom, $dateTo);
        } else {
            $rows = match ($type) {
                'message'       => $this->messageQuery($search, $dateFrom, $dateTo),
                'fax'           => $this->faxQuery($search, $dateFrom, $dateTo),
                'tax_invoice'   => $this->taxInvoiceQuery($search, $dateFrom, $dateTo),
                'cash_receipt'  => $this->cashReceiptQuery($search, $dateFrom, $dateTo),
                'nhis'          => $this->nhisQuery($search, $dateFrom, $dateTo),
                default         => $this->virtualAccountQuery($search, $dateFrom, $dateTo),
            };
            $rows = $rows->get();
        }

        // wwGrid: 타입별 그리드 데이터/컬럼 (클라이언트사이드, 배지→텍스트)
        [$gridData, $gridColumns] = $this->buildGrid($type, $rows);
        $total = $gridData->count();

        /* 갈래 옆 숫자는 「이 기간에 몇 건인가」다.

           예전에는 전체 건수를 적었다. 그래서 「가상계좌 발행 (1)」로 보이는데 골라 보면
           비어 있었다 — 그 한 건이 두 달 전 것이라 기본 기간(30일) 밖이었다.
           고르기 전에 세어 둔 숫자가 고른 뒤와 달라서는 안 된다. */
        $inRange = fn ($q, string $col) => $q->whereBetween(DB::raw("DATE({$col})"), [$dateFrom, $dateTo]);

        $counts = [
            'message'         => $inRange(MessageHistory::query(), 'created_at')->count(),
            'fax'             => $inRange(FaxHistory::query(), 'created_at')->count(),
            'tax_invoice'     => Order::whereNotNull('tax_invoice_no')
                                    ->where(fn ($q) => $q->whereBetween(DB::raw('DATE(tax_invoice_issued_at)'), [$dateFrom, $dateTo])
                                                         ->orWhereBetween(DB::raw('DATE(tax_invoice_cancelled_at)'), [$dateFrom, $dateTo]))
                                    ->count(),
            'cash_receipt'    => Order::whereNotNull('cash_receipt_no')
                                    ->where(fn ($q) => $q->whereBetween(DB::raw('DATE(cash_receipt_issued_at)'), [$dateFrom, $dateTo])
                                                         ->orWhereBetween(DB::raw('DATE(cash_receipt_cancelled_at)'), [$dateFrom, $dateTo]))
                                    ->count(),
            'virtual_account' => $inRange(TossPayment::whereHas('order'), 'toss_payments.created_at')->count(),
            'nhis'            => $inRange(NhisFaxLog::query(), 'nhis_fax_logs.created_at')->count(),
            /* 창고는 보낸 것과 받은 것을 함께 센다. 보낸 것은 목록이 읽는 것과 같은
               자리(활동 기록)에서 센다 — 주문 수로 세면 목록보다 적게 나온다. */
            'withworks'       => $inRange(WithworksEvent::query(), 'COALESCE(occurred_at, created_at)')->count()
                               + $inRange(\Spatie\Activitylog\Models\Activity::where('description', 'like', 'Withworks %'), 'created_at')->count(),
        ];

        $types = self::TYPES;

        return view('dispatch.index', compact('gridData', 'gridColumns', 'total', 'type', 'types', 'counts', 'dateFrom', 'dateTo', 'search', 'perPage'));
    }

    /** 타입별 wwGrid 데이터/컬럼 생성 (원본 테이블 셀을 텍스트로 매핑) */
    private function buildGrid(string $type, $rows): array
    {
        if ($type === 'message') {
            $data = $rows->map(fn ($m) => [
                'id'       => $m->id,
                'created'  => $m->created_at->format('Y-m-d H:i'),
                'channel'  => $m->channelLabel(),
                'template' => $m->template_label ?: ($m->template_code ?: '자유 문구'),
                'rx'       => $m->prescription?->rx_number ?? '-',
                'total'    => (int) $m->total,
                'ok'       => (int) $m->success_count,
                'ng'       => (int) $m->fail_count,
                'result'   => $m->resultLabel(),
                // 무엇을 보냈는지는 첫 줄만 — 훑는 자리라 전문은 상세에서 본다
                'content'  => \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', (string) $m->content), 100),
                'sender'   => $m->sentBy?->name ?? '-',
            ])->values();
            $columns = [
                ['header' => '발송일시', 'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '채널',     'name' => 'channel',  'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '유형',     'name' => 'template', 'width' => 130, 'sortable' => true],
                ['header' => '처방번호', 'name' => 'rx',       'width' => 130],
                ['header' => '대상',     'name' => 'total',    'width' => 70,  'editor' => 'number'],
                ['header' => '성공',     'name' => 'ok',       'width' => 70,  'editor' => 'number'],
                ['header' => '실패',     'name' => 'ng',       'width' => 70,  'editor' => 'number'],
                ['header' => '결과',     'name' => 'result',   'width' => 100, 'align' => 'center', 'sortable' => true],
                ['header' => '내용',     'name' => 'content',  'width' => 300],
                ['header' => '보낸 사람', 'name' => 'sender',  'width' => 90],
            ];
        } elseif ($type === 'fax') {
            $stateLabels = [
                FaxHistory::STATE_WAIT    => '대기',
                FaxHistory::STATE_SENDING => '전송 중',
                FaxHistory::STATE_OK      => '성공',
                FaxHistory::STATE_FAIL    => '실패',
                FaxHistory::STATE_CANCEL  => '취소',
            ];
            $data = $rows->map(function ($f) use ($stateLabels) {
                $rx      = $f->prescription;
                $files   = is_array($f->file_names) ? $f->file_names : (json_decode((string) $f->file_names, true) ?: []);
                return [
                    'id'       => $f->id,
                    'created'  => $f->created_at->format('Y-m-d H:i'),
                    'rx'       => $rx?->rx_number ?? '-',
                    'patient'  => $rx?->patient?->name ?? $rx?->patient_name_ocr ?? '-',
                    'title'    => $f->title ?: '-',
                    'to'       => trim(($f->recipient_type ? $f->recipient_type . ' ' : '') . ($f->fax_no ?: '-')),
                    'files'    => $files ? count($files) . '장' : '-',
                    'status'   => $stateLabels[$f->popbill_state] ?? '대기',
                    'receipt'  => $f->receipt_num ?: '-',
                    'sender'   => $f->sentBy?->name ?? '-',
                ];
            })->values();
            $columns = [
                ['header' => '발송일시', 'name' => 'created', 'width' => 130, 'sortable' => true],
                ['header' => '처방번호', 'name' => 'rx',      'width' => 130],
                ['header' => '이름',     'name' => 'patient', 'width' => 90,  'sortable' => true],
                ['header' => '제목',     'name' => 'title',   'width' => 220],
                ['header' => '받는 곳',  'name' => 'to',      'width' => 160],
                ['header' => '문서',     'name' => 'files',   'width' => 70,  'align' => 'center'],
                ['header' => '상태',     'name' => 'status',  'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '접수번호', 'name' => 'receipt', 'width' => 150],
                ['header' => '보낸 사람', 'name' => 'sender', 'width' => 90],
            ];
        } elseif ($type === 'withworks') {
            /* 보낸 것과 받은 것을 한 표에 세운다 — 「우리가 넘긴 뒤 창고가 어떻게 했나」가
               한 줄씩 이어져야 읽힌다. 방향 칸이 그 둘을 가른다. */
            $data    = collect($rows)->values();
            $columns = [
                ['header' => '일시',     'name' => 'at',        'width' => 130, 'sortable' => true],
                ['header' => '방향',     'name' => 'way',       'width' => 70,  'align' => 'center', 'sortable' => true],
                ['header' => '주문번호', 'name' => 'order_no',  'width' => 120, 'sortable' => true],
                ['header' => '이름',     'name' => 'patient',   'width' => 90],
                ['header' => '판매번호', 'name' => 'so_no',     'width' => 130],
                ['header' => '사건',     'name' => 'event',     'width' => 140, 'sortable' => true],
                ['header' => '상태',     'name' => 'status',    'width' => 120, 'align' => 'center'],
            ];
        } elseif ($type === 'tax_invoice') {
            $data = $rows->map(function ($o) {
                $patient = $o->patient ?? $o->prescription?->patient;
                $si = \App\Models\Order::TAX_INVOICE_STATUS_LABELS[$o->tax_invoice_status] ?? ['미발행', 'secondary'];
                return [
                    'id'       => $o->id,
                    'created'  => $o->tax_invoice_issued_at?->format('Y-m-d H:i') ?? '-',
                    'order_no' => $o->order_number,
                    'patient'  => $patient?->name ?? '-',
                    'biz_no'   => $o->tax_invoice_biz_no ?? '-',
                    'biz_name' => $o->tax_invoice_biz_name ?? '-',
                    'supply'   => (int) $o->tax_invoice_supply,
                    'vat'      => (int) $o->tax_invoice_vat,
                    'inv_no'   => $o->tax_invoice_no ?? '-',
                    'status'   => $si[0],
                    // 취소한 것도 이 목록에 남는다 — 언제 취소했는지가 곧 그 건의 끝이다
                    'cancelled' => $o->tax_invoice_cancelled_at?->format('Y-m-d H:i') ?? '-',
                ];
            })->values();
            $columns = [
                ['header' => '발행일시',   'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no', 'width' => 120],
                ['header' => '이름',     'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '사업자번호', 'name' => 'biz_no',   'width' => 120],
                ['header' => '상호',       'name' => 'biz_name', 'width' => 140],
                ['header' => '공급가액',   'name' => 'supply',   'width' => 100, 'editor' => 'number'],
                ['header' => '부가세',     'name' => 'vat',      'width' => 90,  'editor' => 'number'],
                ['header' => '계산서번호', 'name' => 'inv_no',   'width' => 130],
                ['header' => '상태',       'name' => 'status',   'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '취소일시',   'name' => 'cancelled', 'width' => 130, 'sortable' => true],
            ];
        } elseif ($type === 'cash_receipt') {
            $data = $rows->map(function ($o) {
                $patient = $o->patient ?? $o->prescription?->patient;
                $ci = \App\Models\Order::CASH_RECEIPT_STATUS_LABELS[$o->cash_receipt_status] ?? ['미발행', 'secondary'];
                return [
                    'id'       => $o->id,
                    'created'  => $o->cash_receipt_issued_at?->format('Y-m-d H:i') ?? '-',
                    'order_no' => $o->order_number,
                    'patient'  => $patient?->name ?? '-',
                    'cr_type'  => \App\Models\Order::CASH_RECEIPT_TYPE_LABELS[$o->cash_receipt_type] ?? '-',
                    'ident'    => $o->cash_receipt_identifier ?? '-',
                    'amount'   => (int) $o->cash_receipt_amount,
                    'rc_no'    => $o->cash_receipt_no ?? '-',
                    'status'   => $ci[0],
                    // 취소한 것도 이 목록에 남는다 — 언제 취소했는지가 곧 그 건의 끝이다
                    'cancelled' => $o->cash_receipt_cancelled_at?->format('Y-m-d H:i') ?? '-',
                ];
            })->values();
            $columns = [
                ['header' => '발행일시',   'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호',   'name' => 'order_no', 'width' => 120],
                ['header' => '이름',     'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '종류',       'name' => 'cr_type',  'width' => 90,  'align' => 'center'],
                ['header' => '식별번호',   'name' => 'ident',    'width' => 130],
                ['header' => '발행금액',   'name' => 'amount',   'width' => 100, 'editor' => 'number'],
                ['header' => '영수증번호', 'name' => 'rc_no',    'width' => 130],
                ['header' => '상태',       'name' => 'status',   'width' => 80,  'align' => 'center', 'sortable' => true],
                ['header' => '취소일시',   'name' => 'cancelled', 'width' => 130, 'sortable' => true],
            ];
        } elseif ($type === 'nhis') {
            $data = $rows->map(function ($log) {
                $order   = $log->order;
                $patient = $order?->patient ?? $order?->prescription?->patient;
                $sl = \App\Models\NhisFaxLog::STATUS_LABELS[$log->status] ?? ['label' => $log->status, 'badge' => 'secondary'];
                $result = '-';
                if ($log->nhis_result) {
                    $rl = \App\Models\NhisFaxLog::NHIS_RESULT_LABELS[$log->nhis_result] ?? ['label' => $log->nhis_result];
                    $result = $rl['label'] . ($log->approved_amount ? ' (₩' . number_format($log->approved_amount) . ')' : '');
                }
                return [
                    'id'       => $log->id,
                    'created'  => $log->created_at->format('Y-m-d H:i'),
                    'order_no' => $order?->order_number ?? '-',
                    'patient'  => $patient?->name ?? '-',
                    'fax'      => $log->fax_number ?? '-',
                    'claim'    => (int) $log->claim_amount,
                    'nhis'     => (int) $log->nhis_amount,
                    'ref_no'   => $log->reference_no ?? '-',
                    'status'   => $sl['label'],
                    'result'   => $result,
                    'sender'   => $log->sender?->name ?? '-',
                ];
            })->values();
            $columns = [
                ['header' => '발송일시', 'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호', 'name' => 'order_no', 'width' => 120],
                ['header' => '이름',   'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '발송 팩스','name' => 'fax',      'width' => 120],
                ['header' => '청구금액', 'name' => 'claim',    'width' => 100, 'editor' => 'number'],
                ['header' => '공단부담금','name' => 'nhis',     'width' => 100, 'editor' => 'number'],
                ['header' => '참조번호', 'name' => 'ref_no',   'width' => 120],
                ['header' => '전송상태', 'name' => 'status',   'width' => 90,  'align' => 'center', 'sortable' => true],
                ['header' => '심사결과', 'name' => 'result',   'width' => 130, 'align' => 'center'],
                ['header' => '발송자',   'name' => 'sender',   'width' => 90],
            ];
        } else { // virtual_account
            $data = $rows->map(function ($tp) {
                $order   = $tp->order;
                $patient = $order?->patient ?? $order?->prescription?->patient;
                $due = $tp->due_date ? $tp->due_date->format('Y-m-d') . ($tp->is_expired ? ' (만료)' : '') : '-';
                return [
                    'id'       => $tp->id,
                    'created'  => $tp->created_at->format('Y-m-d H:i'),
                    'order_no' => $order?->order_number ?? '-',
                    'patient'  => $patient?->name ?? $tp->customer_name ?? '-',
                    'bank'     => $tp->bank_name,
                    'account'  => $tp->account_number ?? '-',
                    'amount'   => (int) $tp->amount,
                    'due'      => $due,
                    'status'   => $tp->status_label,
                ];
            })->values();
            $columns = [
                ['header' => '발행일시', 'name' => 'created',  'width' => 130, 'sortable' => true],
                ['header' => '주문번호', 'name' => 'order_no', 'width' => 120],
                ['header' => '이름',   'name' => 'patient',  'width' => 90,  'sortable' => true],
                ['header' => '은행',     'name' => 'bank',     'width' => 90],
                ['header' => '계좌번호', 'name' => 'account',  'width' => 150],
                ['header' => '금액',     'name' => 'amount',   'width' => 100, 'editor' => 'number'],
                ['header' => '만료일',   'name' => 'due',      'width' => 130, 'align' => 'center'],
                ['header' => '상태',     'name' => 'status',   'width' => 90,  'align' => 'center', 'sortable' => true],
            ];
        }

        return [$data, $columns];
    }

    /**
     * 한 건의 상세.
     *
     * id 를 문자로 받는다 — 창고 갈래는 원본이 둘이라 「a12(보낸 기록)」ㆍ「e45(받은 사건)」
     * 처럼 앞 글자로 어느 쪽인지 가린다.
     */
    public function show(string $type, string $id)
    {
        // 다른 화면의 '상세내용' 탭에 주입될 때(?partial=1)는 크롬 없는 프래그먼트로 렌더
        if (request()->boolean('partial')) {
            view()->share('layout', 'layouts.partial');
        }

        /* 상세를 갖춘 갈래만 연다. 문자ㆍ팩스ㆍ창고는 아직 목록뿐이라, 모르는 갈래를
           가상계좌 상세로 흘려보내면 엉뚱한 건을 열거나 500 으로 떨어진다. */
        return match ($type) {
            'virtual_account' => $this->showVirtualAccount((int) $id),
            'tax_invoice'     => $this->showTaxInvoice((int) $id),
            'cash_receipt'    => $this->showCashReceipt((int) $id),
            'nhis'            => $this->showNhis((int) $id),
            'message'         => $this->showMessage((int) $id),
            'fax'             => $this->showFax((int) $id),
            'withworks'       => $this->showWithworks($id),
            default           => abort(404, '없는 유형입니다.'),
        };
    }

    /* ── 상세: 문자ㆍ알림톡 ─────────────────────── */
    private function showMessage(int $id)
    {
        $record = MessageHistory::with(['sentBy', 'prescription.patient', 'prescription.order'])
            ->findOrFail($id);

        $prescription = $record->prescription;
        $order        = $prescription?->order;
        $patient      = $prescription?->patient;
        $type         = 'message';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 팩스 ─────────────────────────────── */
    private function showFax(int $id)
    {
        $record = FaxHistory::with(['sentBy', 'prescription.patient', 'prescription.order'])
            ->findOrFail($id);

        $prescription = $record->prescription;
        $order        = $prescription?->order;
        $patient      = $prescription?->patient;
        $type         = 'fax';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 창고와 주고받은 것 ─────────────────── */
    private function showWithworks(string $id)
    {
        $kind = substr($id, 0, 1);
        $num  = (int) substr($id, 1);

        if ($kind === 'e') {
            $record = WithworksEvent::with(['order.prescription.patient', 'order.patient'])->findOrFail($num);
            $order  = $record->order;
        } elseif ($kind === 'a') {
            $record       = \Spatie\Activitylog\Models\Activity::with('subject')->findOrFail($num);
            $prescription = $record->subject instanceof \App\Models\Prescription ? $record->subject : null;
            $order        = $prescription?->order
                         ?? ($record->subject instanceof Order ? $record->subject : null);
        } else {
            abort(404, '없는 기록입니다.');
        }

        $prescription = $order?->prescription ?? ($prescription ?? null);
        $patient      = $order?->patient ?? $prescription?->patient;
        $type         = 'withworks';
        // 화면이 「보냄/받음」을 가려 그린다
        $wwWay        = $kind === 'a' ? 'sent' : 'got';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type', 'wwWay'));
    }

    /* ── 상세: 가상계좌 ─────────────────────────── */
    private function showVirtualAccount(int $id)
    {
        $record = TossPayment::with([
            'order.prescription.patient',
            'order.prescription.items',
            'order.patient',
            'order.faxLogs.sender',
        ])->findOrFail($id);

        $order       = $record->order;
        $prescription = $order?->prescription;
        $patient     = $order?->patient ?? $prescription?->patient;
        $type        = 'virtual_account';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 세금계산서 ──────────────────────── */
    private function showTaxInvoice(int $id)
    {
        $order = Order::with([
            'prescription.patient',
            'prescription.items',
            'patient',
            'faxLogs.sender',
        ])->where('tax_invoice_status', '!=', 'not_issued')
          ->findOrFail($id);

        $record      = null;
        $prescription = $order->prescription;
        $patient     = $order->patient ?? $prescription?->patient;
        $type        = 'tax_invoice';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: 현금영수증 ──────────────────────── */
    private function showCashReceipt(int $id)
    {
        $order = Order::with([
            'prescription.patient',
            'prescription.items',
            'patient',
        ])->where('cash_receipt_status', '!=', 'not_issued')
          ->findOrFail($id);

        $record      = null;
        $prescription = $order->prescription;
        $patient     = $order->patient ?? $prescription?->patient;
        $type        = 'cash_receipt';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type'));
    }

    /* ── 상세: NHIS 청구 팩스 ──────────────────── */
    private function showNhis(int $id)
    {
        $record = NhisFaxLog::with([
            'order.prescription.patient',
            'order.prescription.items',
            'order.patient',
            'sender',
        ])->findOrFail($id);

        // 같은 주문의 전체 발송 이력 (타임라인용)
        $allLogs = NhisFaxLog::with('sender')
            ->where('order_id', $record->order_id)
            ->orderBy('created_at')
            ->get();

        $order       = $record->order;
        $prescription = $order?->prescription;
        $patient     = $order?->patient ?? $prescription?->patient;
        $type        = 'nhis';

        return view('dispatch.show', compact('record', 'order', 'prescription', 'patient', 'type', 'allLogs'));
    }

    /* ── 쿼리 헬퍼 ─────────────────────────────── */
    private function virtualAccountQuery(?string $search, string $from, string $to)
    {
        return TossPayment::with(['order.prescription.patient', 'order.patient'])
            ->whereBetween(\DB::raw('DATE(toss_payments.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('toss_payments.toss_order_id', 'like', "%{$search}%")
                       ->orWhere('toss_payments.account_number', 'like', "%{$search}%")
                       ->orWhere('toss_payments.customer_name', 'like', "%{$search}%")
                       ->orWhereHas('order.patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"))
                       ->orWhereHas('order', fn($q3) => $q3->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('toss_payments.created_at');
    }

    /* 발행한 것과 취소한 것을 함께 본다. 기간은 둘 중 하나만 걸리면 잡는다 —
       지난달 낸 것을 오늘 취소했다면 오늘 자리에서도 보여야 한다. */
    private function taxInvoiceQuery(?string $search, string $from, string $to)
    {
        return Order::with(['prescription.patient', 'patient'])
            ->whereNotNull('tax_invoice_no')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('DATE(tax_invoice_issued_at)'), [$from, $to])
                  ->orWhereBetween(DB::raw('DATE(tax_invoice_cancelled_at)'), [$from, $to]);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('order_number', 'like', "%{$search}%")
                       ->orWhere('tax_invoice_no', 'like', "%{$search}%")
                       ->orWhere('tax_invoice_biz_name', 'like', "%{$search}%")
                       ->orWhereHas('patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('tax_invoice_issued_at');
    }

    /** 발행과 취소를 함께 본다(세금계산서와 같은 잣대다) */
    private function cashReceiptQuery(?string $search, string $from, string $to)
    {
        return Order::with(['prescription.patient', 'patient'])
            ->whereNotNull('cash_receipt_no')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('DATE(cash_receipt_issued_at)'), [$from, $to])
                  ->orWhereBetween(DB::raw('DATE(cash_receipt_cancelled_at)'), [$from, $to]);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('order_number', 'like', "%{$search}%")
                       ->orWhere('cash_receipt_no', 'like', "%{$search}%")
                       ->orWhere('cash_receipt_identifier', 'like', "%{$search}%")
                       ->orWhereHas('patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('cash_receipt_issued_at');
    }

    private function messageQuery(?string $search, string $from, string $to)
    {
        return MessageHistory::with(['sentBy', 'prescription'])
            ->whereBetween(DB::raw('DATE(message_histories.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('content', 'like', "%{$search}%")
                       ->orWhere('template_label', 'like', "%{$search}%")
                       ->orWhere('receivers', 'like', "%{$search}%")
                       ->orWhereHas('prescription', fn ($q3) => $q3->where('rx_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('message_histories.created_at');
    }

    private function faxQuery(?string $search, string $from, string $to)
    {
        return FaxHistory::with(['sentBy', 'prescription.patient'])
            ->whereBetween(DB::raw('DATE(fax_histories.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('title', 'like', "%{$search}%")
                       ->orWhere('fax_no', 'like', "%{$search}%")
                       ->orWhere('receipt_num', 'like', "%{$search}%")
                       ->orWhereHas('prescription', fn ($q3) => $q3->where('rx_number', 'like', "%{$search}%"))
                       ->orWhereHas('prescription.patient', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('fax_histories.created_at');
    }

    /**
     * 창고와 주고받은 것 — 원본이 둘이라 여기서 한 벌로 엮는다.
     *
     * 보낸 것은 활동 기록에 남는다(「Withworks 판매주문 연계: S…」). 판매주문을 넘긴
     * 일은 주문 한 줄에 값으로만 남고 따로 적어 두는 표가 없어서다.
     * 받은 것은 창고가 보내 온 사건 그대로다(withworks_events).
     *
     * @return \Illuminate\Support\Collection
     */
    private function withworksRows(?string $search, string $from, string $to)
    {
        $hit = fn (?string $hay) => !$search || ($hay && mb_stripos($hay, $search) !== false);

        // ① 우리가 넘긴 것
        $sent = \Spatie\Activitylog\Models\Activity::with('subject')
            ->where('description', 'like', 'Withworks %')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->latest('id')->limit(500)->get()
            ->map(function ($a) {
                /* 이 기록의 주체는 처방전이다(performedOn($prescription)) — 주문번호와
                   이름은 거기서 건너간다. 처방전이 지워졌으면 판매번호만 남는다. */
                $rx    = $a->subject instanceof \App\Models\Prescription ? $a->subject : null;
                $order = $rx?->order ?? ($a->subject instanceof Order ? $a->subject : null);
                // 「Withworks 판매주문 연계: S2608250001」 에서 뒤쪽 번호만 뗀다
                $soNo  = str_contains($a->description, ':')
                    ? trim(substr($a->description, strrpos($a->description, ':') + 1))
                    : '';
                return [
                    'id'       => 'a' . $a->id,
                    'at'       => $a->created_at->format('Y-m-d H:i'),
                    'way'      => '보냄',
                    'order_no' => $order?->order_number ?? '-',
                    'patient'  => $order?->patient?->name ?? $rx?->patient?->name ?? '-',
                    'so_no'    => $soNo ?: ($order?->withworks_so_no ?? '-'),
                    'event'    => '판매주문 등록',
                    'status'   => '-',
                ];
            });

        // ② 창고가 보내 온 것
        $got = WithworksEvent::with('order.patient')
            ->whereBetween(DB::raw('DATE(COALESCE(occurred_at, created_at))'), [$from, $to])
            ->latest('id')->limit(1000)->get()
            ->map(fn ($e) => [
                'id'       => 'e' . $e->id,
                'at'       => ($e->occurred_at ?? $e->created_at)->format('Y-m-d H:i'),
                'way'      => '받음',
                'order_no' => $e->ce_order_number ?: ($e->order?->order_number ?? '-'),
                'patient'  => $e->order?->patient?->name ?? '-',
                'so_no'    => $e->so_no ?: '-',
                'event'    => self::WW_EVENTS[$e->event] ?? $e->event,
                'status'   => $e->status_label ?: ($e->status ?: '-'),
            ]);

        return $sent->concat($got)
            ->filter(fn ($r) => $hit($r['order_no']) || $hit($r['so_no']) || $hit($r['patient']) || $hit($r['event']))
            ->sortByDesc('at')
            ->values();
    }

    /** 창고가 보내 오는 사건 이름 — 그대로 두면 so.picked 처럼 읽힌다 */
    private const WW_EVENTS = [
        'so.created'   => '판매주문 생성',
        'so.confirmed' => '판매 확정',
        'so.allocated' => '재고 할당',
        'so.picked'    => '피킹',
        'so.shipped'   => '출고 완료',
        'so.invoiced'  => '송장 발행',
        'so.cancelled' => '판매 취소',
        'ro.created'   => '반품 접수',
    ];

    private function nhisQuery(?string $search, string $from, string $to)
    {
        return NhisFaxLog::with(['order.prescription.patient', 'order.patient', 'sender'])
            ->whereBetween(\DB::raw('DATE(nhis_fax_logs.created_at)'), [$from, $to])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('document_title', 'like', "%{$search}%")
                       ->orWhere('reference_no',  'like', "%{$search}%")
                       ->orWhereHas('order', fn($q3) => $q3->where('order_number', 'like', "%{$search}%"))
                       ->orWhereHas('order.patient', fn($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('nhis_fax_logs.created_at');
    }
}
