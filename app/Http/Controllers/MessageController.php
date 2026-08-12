<?php

namespace App\Http\Controllers;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Services\MessageSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 메시지 관리 — 거래처를 골라 문자·알림톡을 보낸다.
 *
 * 지금까지 문자와 알림톡은 처방전 한 건을 열어야만 보낼 수 있었다. 안내를 여러 거래처에
 * 함께 보내는 일이 잦아 목록에서 골라 보내는 자리를 만든다.
 *
 * '거래처'는 별도 데이터가 아니라 환자(patients) 다 — 사이드바의 '거래처 관리'가 그 화면을
 * 가리킨다. 여기서도 같은 표를 쓴다.
 */
class MessageController extends Controller
{
    /** 한 번에 보낼 수 있는 최대 인원. 실수로 전체가 나가는 일을 막는 마지막 울타리다. */
    public const MAX_RECIPIENTS = 2000;

    public function __construct(private readonly MessageSender $sender) {}

    public function index(Request $request): View
    {
        $gridData = $this->query($request)->get()->map(fn (Patient $p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'mobile'   => $this->formatMobile($p->mobile ?? $p->phone),
            'raw'      => preg_replace('/\D/', '', (string) ($p->mobile ?? $p->phone)),
            'rx_count' => (int) $p->prescriptions_count,
            'last_rx'  => $p->prescriptions_max_created_at
                            ? substr((string) $p->prescriptions_max_created_at, 0, 10) : '',
            'created'  => $p->created_at?->format('Y-m-d') ?? '',
        ]);

        // 번호가 없는 거래처는 보낼 수 없다. 몇 건인지 화면에 알린다.
        $sendable = $gridData->filter(fn ($r) => $r['raw'] !== '')->count();

        return view('messages.index', [
            'gridData'  => $gridData,
            'total'     => $gridData->count(),
            'sendable'  => $sendable,
            'templates' => [
                'sms'      => MessageTemplate::resolve('sms'),
                'alimtalk' => MessageTemplate::resolve('alimtalk'),
            ],
            'histories' => $this->historyGrid(),
        ]);
    }

    /**
     * 발송 이력을 그리드가 읽을 모양으로.
     *
     * 마이그레이션 전에 배포되면 표가 없다. 화면이 죽는 것보다 이력만 비는 편이 낫다.
     */
    private function historyGrid(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('message_histories')) return [];

        return MessageHistory::with('sentBy')->latest()->limit(50)->get()
            ->map(fn (MessageHistory $h) => [
                'at'      => $h->created_at?->format('Y-m-d H:i') ?? '',
                'ch'      => $h->channelLabel(),
                'tpl'     => $h->template_label ?? $h->template_code ?? '',
                'total'   => $h->total,
                'ok'      => $h->success_count,
                'ng'      => $h->fail_count,
                'result'  => $h->resultLabel(),
                'by'      => $h->sentBy?->name ?? '',
                'content' => mb_substr((string) $h->content, 0, 120),
            ])->all();
    }

    /** 목록과 '전체 발송' 이 같은 조건을 보도록 조회를 한 곳에 둔다 */
    private function query(Request $request)
    {
        $q = Patient::withCount('prescriptions')->withMax('prescriptions', 'created_at')->latest();

        if ($request->filled('q')) {
            $kw     = $request->q;
            $digits = preg_replace('/\D/', '', $kw);
            $q->where(function ($w) use ($kw, $digits) {
                $w->where('name', 'like', "%{$kw}%");
                if ($digits !== '' && strlen($digits) >= 3) {
                    $bare = fn ($c) => "REPLACE(REPLACE({$c}, '-', ''), ' ', '')";
                    $w->orWhereRaw($bare('mobile') . ' LIKE ?', ["%{$digits}%"])
                      ->orWhereRaw($bare('phone')  . ' LIKE ?', ["%{$digits}%"]);
                }
            });
        }

        if ($request->boolean('has_mobile')) {
            $q->where(fn ($w) => $w->whereNotNull('mobile')->orWhereNotNull('phone'));
        }

        return $q;
    }

    /**
     * 발송. 고른 거래처(patient_ids)나 지금 조건에 걸린 전체(scope=all)에 보낸다.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'channel'       => 'required|in:sms,alimtalk',
            'scope'         => 'required|in:selected,all',
            'patient_ids'   => 'required_if:scope,selected|array',
            'patient_ids.*' => 'integer',
            'content'       => 'required_if:channel,sms|nullable|string|max:2000',
            'template_code' => 'nullable|string|max:60',
        ]);

        $patients = $request->scope === 'all'
            ? $this->query($request)->get()
            : Patient::whereIn('id', $request->patient_ids)->get();

        $receivers = $patients
            ->map(fn (Patient $p) => [
                'rcv'        => $p->mobile ?? $p->phone,
                'rcvnm'      => $p->name,
                'patient_id' => $p->id,
            ])
            ->filter(fn ($r) => preg_replace('/\D/', '', (string) $r['rcv']) !== '')
            ->values()
            ->all();

        if (!$receivers) {
            return response()->json(['success' => false, 'message' => '번호가 있는 거래처가 없습니다.'], 422);
        }
        if (count($receivers) > self::MAX_RECIPIENTS) {
            return response()->json([
                'success' => false,
                'message' => '한 번에 ' . number_format(self::MAX_RECIPIENTS) . '명까지 보낼 수 있습니다. 조건을 좁혀 주세요. (지금 '
                           . number_format(count($receivers)) . '명)',
            ], 422);
        }

        // 알림톡 본문은 카카오에 등록된 템플릿이 정한다. 문자는 화면에서 쓴 그대로 나간다.
        $content = (string) $request->input('content', '');

        $result = $this->sender->sendBulk(
            $request->channel, $receivers, $content, $request->input('template_code'),
            ['source' => 'messages'],
        );

        activity()->causedBy(auth()->user())
            ->log(($request->channel === 'alimtalk' ? '알림톡' : 'SMS')
                . " 발송 {$result['success_count']}건"
                . ($result['fail_count'] ? ", {$result['fail_count']}건 실패" : '')
                . ($request->scope === 'all' ? ' (조건 전체)' : ''));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    // ── 메시지 유형 관리 ────────────────────────────────────

    public function templates(Request $request): JsonResponse
    {
        MessageTemplate::seedDefaults();
        $rows = MessageTemplate::when($request->filled('channel'), fn ($q) => $q->channel($request->channel))
            ->orderBy('channel')->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['success' => true, 'templates' => $rows]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $this->templateRules($request);
        $dup  = MessageTemplate::channel($data['channel'])->where('code', $data['code'])->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => '같은 코드가 이미 있습니다.'], 422);
        }

        $tpl = MessageTemplate::create($data + ['sort_order' => (int) MessageTemplate::channel($data['channel'])->max('sort_order') + 1]);
        activity()->causedBy(auth()->user())->performedOn($tpl)->log("메시지 유형 등록: {$tpl->label}");

        return response()->json(['success' => true, 'template' => $tpl]);
    }

    public function updateTemplate(Request $request, MessageTemplate $template): JsonResponse
    {
        $data = $this->templateRules($request);
        $dup  = MessageTemplate::channel($data['channel'])->where('code', $data['code'])
                    ->where('id', '!=', $template->id)->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => '같은 코드가 이미 있습니다.'], 422);
        }

        $template->update($data);
        activity()->causedBy(auth()->user())->performedOn($template)->log("메시지 유형 수정: {$template->label}");

        return response()->json(['success' => true, 'template' => $template]);
    }

    public function destroyTemplate(MessageTemplate $template): JsonResponse
    {
        $label = $template->label;
        $template->delete();
        activity()->causedBy(auth()->user())->log("메시지 유형 삭제: {$label}");

        return response()->json(['success' => true]);
    }

    private function templateRules(Request $request): array
    {
        return $request->validate([
            'channel'     => 'required|in:sms,alimtalk',
            'code'        => 'required|string|max:60|regex:/^[A-Za-z0-9_\-]+$/',
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:200',
            'body'        => 'nullable|string|max:2000',
            'is_active'   => 'boolean',
        ], [
            'code.regex' => '코드는 영문·숫자·_·- 만 쓸 수 있습니다.',
        ]);
    }

    private function formatMobile(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        return match (true) {
            strlen($d) === 11 => substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7),
            strlen($d) === 10 => substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6),
            default           => $d,
        };
    }
}
