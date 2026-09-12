<?php
// app/Http/Controllers/Api/NotificationApiController.php
//
// 앱으로 보낸 알림을 다시 본다.
// 푸시는 폰 알림창에만 떠서 지우면 사라진다 — 무엇이 왔는지 여기서 확인한다.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    // ── GET /api/notifications ────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = FcmNotification::where('user_id', $request->user()->id)->latest('id');

        // 안 읽은 것만 보기
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => collect($page->items())
                ->map(fn (FcmNotification $n) => $this->format($n))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'unread'       => FcmNotification::where('user_id', $request->user()->id)
                                    ->whereNull('read_at')->count(),
            ],
        ]);
    }

    // ── POST /api/notifications/{id}/read ─────────────────
    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = FcmNotification::where('user_id', $request->user()->id)->find($id);

        if (! $n) {
            return response()->json(['success' => false, 'message' => '알림을 찾을 수 없습니다.'], 404);
        }

        // 이미 읽은 것은 그때 시각을 그대로 둔다
        if (! $n->read_at) {
            $n->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    // ── POST /api/notifications/read-all ──────────────────
    public function markAllRead(Request $request): JsonResponse
    {
        $count = FcmNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * 앱이 쓰는 꼴로 내보낸다.
     *
     * target_type·target_key 를 따로 준다. 갈래마다 키 이름이 다른데(room_id,
     * rx_number) 앱이 그것을 다 알고 있어야 할 까닭이 없다. 앱은 이 두 값만 보고
     * 화면을 잇고, 모르는 갈래면 글만 보여 준다.
     */
    private function format(FcmNotification $n): array
    {
        return [
            'id'          => $n->id,
            'title'       => $n->title,
            'body'        => $n->body,
            'type'        => $n->type,
            'target_key'  => $n->target_key,
            'payload'     => $n->payload,
            'sent'        => $n->sent,
            'is_read'     => $n->read_at !== null,
            'created_at'  => $n->created_at?->format('Y-m-d H:i'),
        ];
    }
}
