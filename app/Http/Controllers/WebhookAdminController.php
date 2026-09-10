<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\WebhookParam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 웹훅 관리 — 정의와 로그 (2026-09-10 지시).
 *
 * 두 화면이다. 하나는 무엇을 어디로 주고받는지 적어 두는 자리(정의ㆍ파라미터),
 * 하나는 실제로 무엇이 오갔는지 보는 자리(값ㆍ성공 여부ㆍ시각).
 */
class WebhookAdminController extends Controller
{
    /** 정의 목록 */
    public function index(Request $request): View
    {
        $q = Webhook::with(['params', 'creator', 'updater'])->withCount('logs');

        if ($request->filled('provider'))  $q->where('provider', $request->provider);
        if ($request->filled('direction')) $q->where('direction', $request->direction);

        if ($request->filled('search')) {
            $kw = trim($request->search);
            $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")
                ->orWhere('event_code', 'like', "%{$kw}%")
                ->orWhere('url', 'like', "%{$kw}%"));
        }

        $rows = $q->orderBy('provider')->orderBy('sort')->orderBy('id')->get();

        $gridData = $rows->map(fn (Webhook $w) => [
            'id'         => $w->id,
            'provider'   => $w->provider_label,
            'name'       => $w->name,
            'event_code' => $w->event_code ?: '',
            'direction'  => $w->direction === 'inbound' ? '받음' : '보냄',
            'url'        => $w->full_url,
            'method'     => $w->http_method,
            'active'     => $w->is_active ? '사용' : '멈춤',
            'params'     => $w->params->count(),
            'logs'       => $w->logs_count,
            'secret_env' => $w->secret_env ?: '',
            'desc'       => $w->description ?: '',
            /* 창을 열 때 쓰는 원값 — 화면 표기와 코드가 서로 다르다 */
            'raw'        => [
                'id'          => $w->id,
                'provider'    => $w->provider,
                'name'        => $w->name,
                'event_code'  => $w->event_code,
                'direction'   => $w->direction,
                'url'         => $w->url,
                'http_method' => $w->http_method,
                'is_active'   => $w->is_active,
                'secret_env'  => $w->secret_env,
                'description' => $w->description,
                'note'        => $w->note,
                'sort'        => $w->sort,
                'params'      => $w->params->map(fn (WebhookParam $p) => [
                    'id' => $p->id, 'position' => $p->position, 'name' => $p->name,
                    'data_type' => $p->data_type, 'required' => (bool) $p->required,
                    'sample' => $p->sample, 'description' => $p->description, 'sort' => $p->sort,
                ])->values(),
            ],
        ])->values();

        /* 로그는 옆 탭이다 — 같은 화면에서 함께 그린다(2026-09-10 지시).
           따로 불러오지 않는다: 화면이 하나면 새로고침ㆍ뒤로 가기ㆍ주소 공유가 모두
           그대로 돈다. 거르개 이름은 log_ 로 시작한다 — 목록 쪽 거르개와 한 주소를
           나눠 쓰기 때문이다. */
        return view('webhooks.index', [
            'gridData'  => $gridData,
            'provider'  => $request->input('provider', ''),
            'direction' => $request->input('direction', ''),
            'search'    => $request->input('search'),
            'tab'       => $request->input('tab') === 'logs' ? 'logs' : 'list',
        ] + $this->로그자료($request));
    }

    /** 정의 저장 — 새로 세우거나 고친다. 파라미터도 함께 담는다. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'          => 'nullable|integer|exists:webhooks,id',
            'provider'    => 'required|string|max:30',
            'name'        => 'required|string|max:100',
            'event_code'  => 'nullable|string|max:80',
            'direction'   => 'required|in:inbound,outbound',
            'url'         => 'required|string|max:500',
            'http_method' => 'required|string|max:10',
            'is_active'   => 'boolean',
            'secret_env'  => 'nullable|string|max:60',
            'description' => 'nullable|string|max:300',
            'note'        => 'nullable|string|max:5000',
            'sort'        => 'nullable|integer|min:0|max:9999',

            'params'                => 'array',
            'params.*.position'     => 'required|in:body,header,query,path',
            'params.*.name'         => 'required|string|max:100',
            'params.*.data_type'    => 'required|string|max:20',
            'params.*.required'     => 'boolean',
            'params.*.sample'       => 'nullable|string|max:200',
            'params.*.description'  => 'nullable|string|max:300',
        ], [
            'provider.required' => '구분을 고르십시오.',
            'name.required'     => '웹훅 명을 적으십시오.',
            'url.required'      => '주소를 적으십시오.',
        ]);

        $webhook = DB::transaction(function () use ($data, $request) {
            $칸 = collect($data)->only([
                'provider', 'name', 'event_code', 'direction', 'url', 'http_method',
                'is_active', 'secret_env', 'description', 'note', 'sort',
            ])->all();

            $칸['is_active'] = (bool) ($data['is_active'] ?? false);
            $칸['sort']      = (int) ($data['sort'] ?? 0);
            $칸['updated_by'] = Auth::id();

            if (! empty($data['id'])) {
                $w = Webhook::findOrFail($data['id']);
                $w->update($칸);
            } else {
                $칸['created_by'] = Auth::id();
                $w = Webhook::create($칸);
            }

            /* 파라미터는 통째로 다시 쓴다 — 화면에서 지운 줄이 표에 남지 않게 한다 */
            $w->params()->delete();

            foreach (array_values($data['params'] ?? []) as $i => $p) {
                $w->params()->create([
                    'position'    => $p['position'],
                    'name'        => $p['name'],
                    'data_type'   => $p['data_type'],
                    'required'    => (bool) ($p['required'] ?? false),
                    'sample'      => $p['sample'] ?? null,
                    'description' => $p['description'] ?? null,
                    'sort'        => ($i + 1) * 10,
                ]);
            }

            return $w;
        });

        activity()->causedBy(Auth::user())->performedOn($webhook)
            ->log("웹훅 저장 → {$webhook->provider_label} · {$webhook->name}");

        return response()->json(['ok' => true, 'id' => $webhook->id]);
    }

    /** 정의 지우기 — 로그는 남는다(그때 무슨 일이 있었는지는 그대로 봐야 한다) */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $이름 = "{$webhook->provider_label} · {$webhook->name}";
        $webhook->delete();

        activity()->causedBy(Auth::user())->log("웹훅 삭제 → {$이름}");

        return response()->json(['ok' => true]);
    }

    /**
     * 낱장으로 여는 로그 화면.
     *
     * 본체는 웹훅 관리 화면의 옆 탭이다 — 여기서는 같은 조각을 레이아웃에 얹기만 한다.
     */
    public function logs(Request $request): View
    {
        return view('webhooks.logs', $this->로그자료($request));
    }

    /**
     * 로그 칸에 담을 것 — 목록 화면과 낱장 화면이 같은 것을 본다 (2026-09-10 지시).
     *
     * 거르개 이름은 log_ 로 시작한다. 웹훅 목록 쪽에도 구분ㆍ방향ㆍ검색어가 있어,
     * 한 주소를 나눠 쓰면 이름이 부딪친다.
     */
    private function 로그자료(Request $request): array
    {
        $from = $request->input('log_from', now()->subDays(7)->toDateString());
        $to   = $request->input('log_to',   now()->toDateString());

        $q = WebhookLog::with('webhook')
            ->whereBetween(DB::raw('DATE(occurred_at)'), [$from, $to]);

        if ($request->filled('log_provider'))  $q->where('provider', $request->log_provider);
        if ($request->filled('log_direction')) $q->where('direction', $request->log_direction);

        if ($request->filled('log_result')) {
            $q->where('ok', $request->log_result === 'ok');
        }

        if ($request->filled('log_search')) {
            $kw = trim($request->log_search);
            $q->where(fn ($s) => $s
                ->where('event_code', 'like', "%{$kw}%")
                ->orWhere('ref', 'like', "%{$kw}%")
                ->orWhere('url', 'like', "%{$kw}%")
                ->orWhere('payload', 'like', "%{$kw}%"));
        }

        $rows = $q->latest('occurred_at')->latest('id')->limit(1000)->get();

        $gridData = $rows->map(fn (WebhookLog $l) => [
            'id'        => $l->id,
            'at'        => $l->occurred_at?->format('Y-m-d H:i:s') ?? '',
            'provider'  => $l->provider_label,
            'direction' => $l->direction === 'inbound' ? '받음' : '보냄',
            'name'      => $l->webhook?->name ?: '',
            'event'     => $l->event_code ?: '',
            'result'    => $l->result_label,
            'status'    => $l->http_status ?: '',
            'sign'      => $l->signature_ok === null ? '' : ($l->signature_ok ? '맞음' : '틀림'),
            'ms'        => $l->duration_ms ?: '',
            'ref'       => $l->ref ?: '',
            'url'       => $l->url ?: '',
            'ip'        => $l->ip ?: '',
            'error'     => $l->error ? mb_substr($l->error, 0, 120) : '',
            /* 상세 창이 읽는 원값 */
            'raw'       => [
                'headers'  => $l->headers ?: [],
                'payload'  => $l->payload ?: '',
                'response' => $l->response ?: '',
                'error'    => $l->error ?: '',
            ],
        ])->values();

        $counts = [
            'all'  => $rows->count(),
            'ok'   => $rows->where('ok', true)->count(),
            'fail' => $rows->where('ok', false)->count(),
        ];

        return [
            'logRows'      => $gridData,
            'logCounts'    => $counts,
            'logFrom'      => $from,
            'logTo'        => $to,
            'logProvider'  => $request->input('log_provider', ''),
            'logDirection' => $request->input('log_direction', ''),
            'logResult'    => $request->input('log_result', ''),
            'logSearch'    => $request->input('log_search'),
        ];
    }
}
