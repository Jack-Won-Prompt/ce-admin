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
            /* 목록에서 바로 상태를 바꾸려면 저장할 코드가 함께 있어야 한다 —
               보이는 글(「조치 완료」)만으로는 어느 코드인지 되찾을 수 없다. */
            'state_key' => $r->status,
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
            '고칠수있나' => perm('error-logs', 'update'),
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
        abort_unless(perm('error-logs', 'update'), 403);

        $request->validate([
            'status' => 'required|in:open,checked,fixed,ignored',
            'memo'   => 'nullable|string|max:500',
        ]);

        $바꿀것 = [
            'status'     => $request->input('status'),
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ];

        /* 메모는 보내 온 때만 적는다 — 목록의 상태 딱지는 상태만 보내므로,
           없는 것을 빈 값으로 읽으면 창에서 적어 둔 메모가 지워진다. */
        if ($request->has('memo')) {
            $바꿀것['memo'] = $request->input('memo');
        }

        $errorLog->update($바꿀것);

        return response()->json([
            'success' => true,
            'state'   => $errorLog->status_label,
            'message' => '처리 상태를 「' . $errorLog->status_label . '」로 저장했습니다.',
        ]);
    }

    /**
     * 한 건만 삭제한다 (2026-10-08).
     *
     * 일괄 삭제는 이레보다 오래된 것만 지운다 — 오늘 남은 기록 하나를 치울 길이
     * 없었다. 지운 사실은 활동 이력에 남긴다.
     */
    public function destroy(ErrorLog $errorLog)
    {
        $적을말 = "오류 기록 삭제: #{$errorLog->id} {$errorLog->exception} "
                . mb_substr((string) $errorLog->file, 0, 120) . ':' . $errorLog->line;

        $errorLog->delete();

        activity()->causedBy(Auth::user())->log($적을말);

        return response()->json([
            'success' => true,
            'message' => '오류 기록을 삭제했습니다.',
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
