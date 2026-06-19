<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $query = Notice::with('author')->where('is_active', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notices = $query->orderByDesc('is_pinned')->orderByDesc('created_at')->paginate(15);

        return view('notices.index', compact('notices'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        return view('notices.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'required|string',
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data['created_by'] = Auth::id();
        $data['is_pinned']  = $request->boolean('is_pinned');
        $data['is_active']  = $request->boolean('is_active', true);

        Notice::create($data);

        return redirect()->route('notices.index')->with('success', '공지사항이 등록되었습니다.');
    }

    public function show(Notice $notice)
    {
        if (! $notice->is_active && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $notice->incrementViews();

        // 읽음 처리 (패널과 동일하게)
        NoticeRead::firstOrCreate([
            'notice_id' => $notice->id,
            'user_id'   => Auth::id(),
        ]);

        $prev = Notice::where('is_active', true)->where('id', '<', $notice->id)->orderByDesc('id')->first();
        $next = Notice::where('is_active', true)->where('id', '>', $notice->id)->orderBy('id')->first();

        return view('notices.show', compact('notice', 'prev', 'next'));
    }

    public function edit(Notice $notice)
    {
        $this->authorizeAdmin();
        return view('notices.edit', compact('notice'));
    }

    public function update(Request $request, Notice $notice)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'required|string',
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['is_active'] = $request->boolean('is_active');

        $notice->update($data);

        return redirect()->route('notices.show', $notice)->with('success', '공지사항이 수정되었습니다.');
    }

    public function destroy(Notice $notice)
    {
        $this->authorizeAdmin();
        $notice->delete();

        return redirect()->route('notices.index')->with('success', '공지사항이 삭제되었습니다.');
    }

    // ── 패널 API ──────────────────────────────────────────────

    public function panelList(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query  = Notice::with('author')->where('is_active', true);

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $notices = $query->orderByDesc('is_pinned')->orderByDesc('created_at')->limit(60)->get();

        // 로드된 공지 중 읽음 여부 (목록 표시용)
        $noticeIds = $notices->pluck('id')->all();
        $readIdSet = NoticeRead::where('user_id', $userId)
                               ->whereIn('notice_id', $noticeIds)
                               ->pluck('notice_id')
                               ->flip()
                               ->toArray();

        // 전체 활성 공지 기준 미읽음 수 (뱃지용) — 로드 제한(60개)과 무관하게 전수 집계
        $allActiveIds = Notice::where('is_active', true)->pluck('id');
        $totalActive  = $allActiveIds->count();
        $totalRead    = NoticeRead::where('user_id', $userId)
                                  ->whereIn('notice_id', $allActiveIds)
                                  ->count();
        $unreadCount  = max(0, $totalActive - $totalRead);

        return response()->json([
            'notices' => $notices->map(fn($n) => [
                'id'       => $n->id,
                'title'    => $n->title,
                'is_pinned'=> $n->is_pinned,
                'views'    => $n->views,
                'author'   => $n->author->name ?? '-',
                'date'     => $n->created_at->format('Y.m.d'),
                'is_read'  => array_key_exists($n->id, $readIdSet),
            ]),
            'unread_count' => $unreadCount,
            'is_admin'     => Auth::user()->role === 'admin',
        ]);
    }

    public function panelShow(Notice $notice): JsonResponse
    {
        if (! $notice->is_active && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $notice->incrementViews();

        // 읽음 처리 (중복 무시)
        NoticeRead::firstOrCreate([
            'notice_id' => $notice->id,
            'user_id'   => Auth::id(),
        ]);

        $prev = Notice::where('is_active', true)->where('id', '<', $notice->id)->orderByDesc('id')->first();
        $next = Notice::where('is_active', true)->where('id', '>', $notice->id)->orderBy('id')->first();

        $userId       = Auth::id();
        $allActiveIds = Notice::where('is_active', true)->pluck('id');
        $totalActive  = $allActiveIds->count();
        $totalRead    = NoticeRead::where('user_id', $userId)
                                  ->whereIn('notice_id', $allActiveIds)
                                  ->count();
        $unreadCount  = max(0, $totalActive - $totalRead);

        return response()->json([
            'notice' => [
                'id'        => $notice->id,
                'title'     => $notice->title,
                'content'   => $notice->content,
                'is_pinned' => $notice->is_pinned,
                'views'     => $notice->views,
                'author'    => $notice->author->name ?? '-',
                'date'      => $notice->created_at->format('Y년 m월 d일 H:i'),
            ],
            'prev'         => $prev ? ['id' => $prev->id, 'title' => $prev->title, 'date' => $prev->created_at->format('Y.m.d')] : null,
            'next'         => $next ? ['id' => $next->id, 'title' => $next->title, 'date' => $next->created_at->format('Y.m.d')] : null,
            'is_admin'     => Auth::user()->role === 'admin',
            'unread_count' => $unreadCount,
        ]);
    }

    private function authorizeAdmin(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, '관리자만 접근할 수 있습니다.');
        }
    }
}
