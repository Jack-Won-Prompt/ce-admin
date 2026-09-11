<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 오류 기록 화면 (2026-09-11 지시).
 *
 * 서버에서 난 잘못을 담당자가 화면에서 바로 본다. 파일 로그와 달리 갈리지 않고,
 * 같은 잘못은 한 줄로 묶여 셈이 올라간다 — 무엇이 자주 나는지가 눈에 들어온다.
 */
class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $부터  = $request->input('from', now()->subDays(7)->toDateString());
        $까지  = $request->input('to', now()->toDateString());
        $갈래  = $request->input('kind', '');
        $상태  = $request->input('status', '');
        $코드  = $request->input('http_status', '');
        $출처  = $request->input('source', '');
        $검색  = trim((string) $request->input('q', ''));

        $q = ErrorLog::query()
            ->whereBetween('last_at', [$부터 . ' 00:00:00', $까지 . ' 23:59:59']);

        if ($갈래 !== '') {
            $q->where('kind', $갈래);
        }
        if ($상태 !== '') {
            $q->where('status', $상태);
        }
        if ($코드 !== '') {
            $q->where('http_status', (int) $코드);
        }
        if ($출처 !== '') {
            $q->where('source', $출처);
        }
        if ($검색 !== '') {
            $q->where(function ($w) use ($검색) {
                $w->where('message', 'like', "%{$검색}%")
                  ->orWhere('url', 'like', "%{$검색}%")
                  ->orWhere('file', 'like', "%{$검색}%")
                  ->orWhere('route_name', 'like', "%{$검색}%")
                  ->orWhere('user_name', 'like', "%{$검색}%");
            });
        }

        $줄들 = (clone $q)->latest('last_at')
            ->limit((int) config('errors.list_limit', 1000))->get();

        $rows = $줄들->map(fn (ErrorLog $r) => [
            'id'      => $r->id,
            'at'      => $r->last_at?->format('Y-m-d H:i:s'),
            'source'  => $r->source_label,
            'kind'    => $r->kind,
            'status'  => $r->http_status,
            'message' => mb_substr((string) $r->message, 0, 160),
            'where'   => $r->where,
            'route'   => $r->route_name ?? '-',
            'user'    => $r->user_name ?? '-',
            'hit'     => $r->hit,
            'state'   => $r->status_label,
            'url'     => $r->url,
        ])->values();

        /* 위에 세우는 셈 — 무엇이 얼마나 났는지 한눈에 */
        $셈 = [
            'all'     => (clone $q)->count(),
            'hit'     => (int) (clone $q)->sum('hit'),
            'open'    => (clone $q)->where('status', 'open')->count(),
            'server'  => (clone $q)->where('source', 'server')->count(),
            'browser' => (clone $q)->where('source', 'browser')->count(),
        ];

        /* 갈래 거르개는 쌓인 것에서 뽑는다 — 미리 적어 두면 새 갈래가 안 보인다 */
        $갈래들 = ErrorLog::query()
            ->selectRaw('kind, count(*) n, sum(hit) h')
            ->whereBetween('last_at', [$부터 . ' 00:00:00', $까지 . ' 23:59:59'])
            ->groupBy('kind')->orderByDesc('h')->limit(40)->get();

        return view('error-logs.index', [
            'rows'    => $rows,
            '셈'      => $셈,
            '갈래들'  => $갈래들,
            'from'    => $부터,
            'to'      => $까지,
            'kind'    => $갈래,
            'status'  => $상태,
            'httpStatus' => $코드,
            'source'  => $출처,
            '출처표'  => ErrorLog::출처,
            'q'       => $검색,
            '상태표'  => ErrorLog::상태,
        ]);
    }

    /** 한 건의 온 내용 — 목록에서 줄을 누르면 창으로 편다 */
    public function show(ErrorLog $errorLog)
    {
        return response()->json([
            'id'        => $errorLog->id,
            'kind'      => $errorLog->kind,
            'source'    => $errorLog->source_label,
            'exception' => $errorLog->exception,
            'status'    => $errorLog->http_status,
            'level'     => $errorLog->level,
            'message'   => $errorLog->message,
            'file'      => $errorLog->short_file,
            'line'      => $errorLog->line,
            'col'       => $errorLog->col,
            'trace'     => $errorLog->trace,
            'url'       => $errorLog->url,
            'method'    => $errorLog->http_method,
            'route'     => $errorLog->route_name,
            'ip'        => $errorLog->ip,
            'agent'     => $errorLog->user_agent,
            'user'      => $errorLog->user_name,
            'input'     => $errorLog->input,
            'hit'       => $errorLog->hit,
            'first_at'  => $errorLog->first_at?->format('Y-m-d H:i:s'),
            'last_at'   => $errorLog->last_at?->format('Y-m-d H:i:s'),
            'state'     => $errorLog->status,
            'state_label' => $errorLog->status_label,
            'memo'      => $errorLog->memo,
            'checked'   => $errorLog->checker?->name,
            'checked_at' => $errorLog->checked_at?->format('Y-m-d H:i'),
        ]);
    }

    /** 살펴본 자취를 남긴다 — 누가 언제 무엇으로 보았는지 */
    public function mark(Request $request, ErrorLog $errorLog)
    {
        $request->validate([
            'status' => 'required|in:open,checked,fixed,ignored',
            'memo'   => 'nullable|string|max:500',
        ]);

        $errorLog->update([
            'status'     => $request->input('status'),
            'memo'       => $request->input('memo'),
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'state'   => $errorLog->status_label,
            'message' => '처리 상태를 「' . $errorLog->status_label . '」로 저장했습니다.',
        ]);
    }

    /** 오래된 것 비우기 — 표가 끝없이 자라지 않게 */
    public function purge(Request $request)
    {
        $날 = (int) $request->input('days', config('errors.keep_days', 180));
        $날 = max(7, min(3650, $날));

        $지움 = ErrorLog::where('last_at', '<', now()->subDays($날))->delete();

        activity()->causedBy(Auth::user())
            ->log("오류 기록 삭제: {$날}일보다 오래된 {$지움}건");

        return response()->json([
            'success' => true,
            'deleted' => $지움,
            'message' => "{$날}일보다 오래된 {$지움}건을 삭제했습니다.",
        ]);
    }
}
