<?php
// app/Http/Controllers/Api/InquiryApiController.php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InquiryApiController extends Controller
{
    // ── GET /api/inquiries ────────────────────────────────
    /** 내 문의 목록 */
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Inquiry::where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $inquiries = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $inquiries->map(fn(Inquiry $i) => [
                'id'          => $i->id,
                'title'       => $i->title,
                'category'    => $i->category,
                'category_label' => $i->categoryLabel(),
                'status'      => $i->status,
                'created_at'  => $i->created_at->format('Y-m-d H:i'),
            ]),
            'meta' => [
                'current_page' => $inquiries->currentPage(),
                'last_page'    => $inquiries->lastPage(),
                'total'        => $inquiries->total(),
                'per_page'     => $inquiries->perPage(),
            ],
        ]);
    }

    // ── POST /api/inquiries ───────────────────────────────
    /** 새 문의 등록 */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'body'       => 'nullable|string',
            'category'   => 'required|in:general,technical,other',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (! $request->filled('body') && ! $request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => '내용을 입력하거나 파일을 첨부해주세요.'], 422);
        }

        $inquiry = Inquiry::create([
            'user_id'  => Auth::id(),
            'title'    => $request->input('title'),
            'category' => $request->input('category'),
            'status'   => 'pending',
        ]);

        $this->storeMessage($inquiry, $request, $request->input('body'));

        // 관리자에게 채팅 알림 전송
        $this->notifyAdmins(
            $inquiry,
            "[새 문의] {$inquiry->title}\n\n{$request->input('body')}"
        );

        return response()->json([
            'success'    => true,
            'message'    => '문의가 등록되었습니다.',
            'inquiry_id' => $inquiry->id,
        ], 201);
    }

    // ── GET /api/inquiries/{id} ───────────────────────────
    /** 문의 상세 + 메시지 목록 */
    public function show(Inquiry $inquiry): JsonResponse
    {
        if ($inquiry->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '접근 권한이 없습니다.'], 403);
        }

        $inquiry->load('messages.user');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $inquiry->id,
                'title'          => $inquiry->title,
                'category'       => $inquiry->category,
                'category_label' => $inquiry->categoryLabel(),
                'status'         => $inquiry->status,
                'created_at'     => $inquiry->created_at->format('Y-m-d H:i'),
                'messages'       => $inquiry->messages->map(fn(InquiryMessage $m) => $this->formatMessage($m)),
            ],
        ]);
    }

    // ── POST /api/inquiries/{id}/messages ─────────────────
    /** 추가 메시지 전송 (사용자) */
    public function addMessage(Request $request, Inquiry $inquiry): JsonResponse
    {
        if ($inquiry->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '접근 권한이 없습니다.'], 403);
        }

        $request->validate([
            'body'       => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (! $request->filled('body') && ! $request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => '내용을 입력하거나 파일을 첨부해주세요.'], 422);
        }

        // 답변 완료된 경우 상태를 pending으로 되돌림 (재문의)
        if ($inquiry->status === 'answered') {
            $inquiry->update(['status' => 'pending']);
        }

        $message = $this->storeMessage($inquiry, $request, $request->input('body'));

        // 관리자에게 재문의 알림 전송
        $this->notifyAdmins(
            $inquiry,
            "[재문의] {$inquiry->title}\n\n{$request->input('body')}"
        );

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message),
        ], 201);
    }

    // ── 내부 헬퍼 ─────────────────────────────────────────

    private function storeMessage(Inquiry $inquiry, Request $request, ?string $body): InquiryMessage
    {
        $path = $name = $size = null;
        $isImage = false;

        if ($request->hasFile('attachment')) {
            $file    = $request->file('attachment');
            $path    = $file->store('inquiry-attachments', 'public');
            $name    = $file->getClientOriginalName();
            $size    = $file->getSize();
            $isImage = str_starts_with($file->getMimeType() ?? '', 'image/');
        }

        return InquiryMessage::create([
            'inquiry_id'      => $inquiry->id,
            'user_id'         => Auth::id(),
            'body'            => $body,
            'attachment_path' => $path,
            'attachment_name' => $name,
            'attachment_size' => $size,
            'is_image'        => $isImage,
        ]);
    }

    /**
     * 모든 활성 관리자에게 1:1 채팅으로 문의 알림 전송
     * - 관리자 ↔ 문의자 사이의 direct 방을 찾거나 생성
     * - Pusher broadcast 로 실시간 알림
     */
    private function notifyAdmins(Inquiry $inquiry, string $body): void
    {
        $userId = $inquiry->user_id;

        $admins = User::where('role', 'admin')
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            if ($admin->id === $userId) continue;

            // 기존 1:1 채팅방 조회
            $room = ChatRoom::where('type', 'direct')
                ->whereHas('users', fn($q) => $q->where('user_id', $admin->id))
                ->whereHas('users', fn($q) => $q->where('user_id', $userId))
                ->first();

            if (! $room) {
                $room = ChatRoom::create(['type' => 'direct', 'name' => null]);
                $room->users()->attach([$admin->id, $userId]);
            }

            $chatMessage = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => $userId,
                'body'         => $body,
            ]);

            try {
                broadcast(new ChatMessageSent($chatMessage))->toOthers();
            } catch (\Throwable $e) {
                \Log::error('[InquiryNotify] broadcast 실패', ['error' => $e->getMessage()]);
            }
        }
    }

    private function formatMessage(InquiryMessage $msg): array
    {
        $msg->loadMissing('user');

        return [
            'id'              => $msg->id,
            'user_id'         => $msg->user_id,
            'user_name'       => $msg->user->name ?? '-',
            'is_admin'        => ($msg->user->role ?? '') === 'admin',
            'body'            => $msg->body,
            'attachment_path' => $msg->attachment_path
                ? Storage::url($msg->attachment_path)
                : null,
            'attachment_name' => $msg->attachment_name,
            'attachment_size' => $msg->attachment_size,
            'is_image'        => $msg->is_image,
            'created_at'      => $msg->created_at->format('Y-m-d H:i'),
        ];
    }
}
