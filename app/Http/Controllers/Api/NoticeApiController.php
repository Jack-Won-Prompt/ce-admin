<?php
// app/Http/Controllers/Api/NoticeApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\NoticeRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeApiController extends Controller
{
    // ── GET /api/notices ──────────────────────────────────
    /** 공지사항 목록 */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query  = Notice::with('author')->where('is_active', true);

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $notices = $query->orderByDesc('is_pinned')->orderByDesc('created_at')->paginate(20);

        // 읽음 여부 O(1) 조회
        $readIds = NoticeRead::where('user_id', $userId)
                             ->whereIn('notice_id', $notices->pluck('id'))
                             ->pluck('notice_id')
                             ->flip();

        // 전체 미읽음 수
        $totalActive = Notice::where('is_active', true)->count();
        $totalRead   = NoticeRead::where('user_id', $userId)
                                 ->whereIn('notice_id', Notice::where('is_active', true)->pluck('id'))
                                 ->count();
        $unreadCount = max(0, $totalActive - $totalRead);

        return response()->json([
            'success' => true,
            'data'    => $notices->map(fn(Notice $n) => [
                'id'        => $n->id,
                'title'     => $n->title,
                'is_pinned' => $n->is_pinned,
                'views'     => $n->views,
                'author'    => $n->author->name ?? '-',
                'date'      => $n->created_at->format('Y-m-d'),
                'is_read'   => $readIds->has($n->id),
            ]),
            'meta' => [
                'current_page' => $notices->currentPage(),
                'last_page'    => $notices->lastPage(),
                'total'        => $notices->total(),
                'per_page'     => $notices->perPage(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    // ── GET /api/notices/{id} ─────────────────────────────
    /** 공지사항 상세 */
    public function show(Notice $notice): JsonResponse
    {
        if (! $notice->is_active) {
            return response()->json(['success' => false, 'message' => '존재하지 않는 공지사항입니다.'], 404);
        }

        $notice->load('author');
        $notice->incrementViews();

        // 읽음 처리
        NoticeRead::firstOrCreate([
            'notice_id' => $notice->id,
            'user_id'   => Auth::id(),
        ]);

        $prev = Notice::where('is_active', true)->where('id', '<', $notice->id)->orderByDesc('id')->first();
        $next = Notice::where('is_active', true)->where('id', '>', $notice->id)->orderBy('id')->first();

        $userId      = Auth::id();
        $totalActive = Notice::where('is_active', true)->count();
        $totalRead   = NoticeRead::where('user_id', $userId)
                                 ->whereIn('notice_id', Notice::where('is_active', true)->pluck('id'))
                                 ->count();
        $unreadCount = max(0, $totalActive - $totalRead);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $notice->id,
                'title'     => $notice->title,
                'content'   => $notice->content,
                'is_pinned' => $notice->is_pinned,
                'views'     => $notice->views,
                'author'    => $notice->author->name ?? '-',
                'date'      => $notice->created_at->format('Y-m-d H:i'),
                'prev'      => $prev ? ['id' => $prev->id, 'title' => $prev->title] : null,
                'next'      => $next ? ['id' => $next->id, 'title' => $next->title] : null,
            ],
            'unread_count' => $unreadCount,
        ]);
    }
}
