<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = Inquiry::with(['user', 'answeredBy']);

        // 관리자: 전체 / 일반 사용자: 본인 것만
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->orderByDesc('created_at')->paginate(15);
        $pendingCount = (clone $query->getQuery())->where('status', 'pending')->count();

        return view('inquiries.index', compact('inquiries', 'pendingCount'));
    }

    public function create()
    {
        return view('inquiries.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'category'   => 'required|in:general,technical,other',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $inquiry = Inquiry::create([
            'user_id'  => Auth::id(),
            'title'    => $request->input('title'),
            'category' => $request->input('category'),
            'status'   => 'pending',
        ]);

        // 첫 번째 메시지(본문 + 첨부파일) 저장
        $this->_storeMessage($inquiry, $request, $request->input('content'));

        return redirect()->route('inquiries.show', $inquiry)->with('success', '문의가 등록되었습니다. 빠른 시일 내에 답변드리겠습니다.');
    }

    public function show(Inquiry $inquiry)
    {
        $user = Auth::user();

        // 본인 또는 관리자만 열람 가능
        if ($user->role !== 'admin' && $inquiry->user_id !== $user->id) {
            abort(403);
        }

        $inquiry->load(['user', 'answeredBy']);
        return view('inquiries.show', compact('inquiry'));
    }

    public function reply(Request $request, Inquiry $inquiry)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, '관리자만 답변할 수 있습니다.');
        }

        $data = $request->validate([
            'answer' => 'required|string',
        ]);

        $inquiry->update([
            'answer'      => $data['answer'],
            'status'      => 'answered',
            'answered_by' => Auth::id(),
            'answered_at' => now(),
        ]);

        return redirect()->route('inquiries.show', $inquiry)->with('success', '답변이 등록되었습니다.');
    }

    public function destroy(Inquiry $inquiry)
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $inquiry->user_id !== $user->id) {
            abort(403);
        }

        $inquiry->delete();

        return redirect()->route('inquiries.index')->with('success', '문의가 삭제되었습니다.');
    }

    // ── 패널 API ──────────────────────────────────────────────

    public function panelList(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Inquiry::with('user');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $inquiries    = $query->orderByDesc('created_at')->limit(60)->get();
        $pendingCount = Inquiry::when($user->role !== 'admin', fn($q) => $q->where('user_id', $user->id))
                               ->where('status', 'pending')->count();

        return response()->json([
            'inquiries'     => $inquiries->map(fn($i) => [
                'id'       => $i->id,
                'title'    => $i->title,
                'category' => $i->categoryLabel(),
                'status'   => $i->status,
                'user'     => $i->user->name ?? '-',
                'date'     => $i->created_at->format('Y.m.d'),
            ]),
            'is_admin'      => $user->role === 'admin',
            'pending_count' => $pendingCount,
        ]);
    }

    public function panelShow(Inquiry $inquiry): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $inquiry->user_id !== $user->id) {
            abort(403);
        }

        $inquiry->load(['user', 'messages.user']);

        return response()->json([
            'inquiry'    => [
                'id'       => $inquiry->id,
                'title'    => $inquiry->title,
                'category' => $inquiry->categoryLabel(),
                'status'   => $inquiry->status,
                'user'     => $inquiry->user->name ?? '-',
                'user_id'  => $inquiry->user_id,
                'date'     => $inquiry->created_at->format('Y년 m월 d일 H:i'),
            ],
            'messages'   => $inquiry->messages->map(fn($m) => $this->_formatMessage($m)),
            'is_admin'   => $user->role === 'admin',
            'can_delete' => $user->role === 'admin' || $inquiry->user_id === $user->id,
        ]);
    }

    /** 신규 문의 + 첫 번째 메시지 등록 */
    public function panelStore(Request $request): JsonResponse
    {
        if (Auth::user()->role === 'admin') {
            return response()->json(['success' => false, 'message' => '관리자는 문의를 등록할 수 없습니다.'], 403);
        }

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

        $this->_storeMessage($inquiry, $request, $request->input('body'));

        return response()->json(['success' => true, 'message' => '문의가 등록되었습니다.', 'inquiry_id' => $inquiry->id]);
    }

    /** 기존 문의에 메시지 추가 (관리자 답변 전용) */
    public function panelAddMessage(Request $request, Inquiry $inquiry): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => '관리자만 답변할 수 있습니다.'], 403);
        }

        $request->validate([
            'body'       => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (! $request->filled('body') && ! $request->hasFile('attachment')) {
            return response()->json(['success' => false, 'message' => '내용을 입력하거나 파일을 첨부해주세요.'], 422);
        }

        // 관리자 답변 → 상태를 answered로 갱신
        $inquiry->update([
            'status'      => 'answered',
            'answered_by' => $user->id,
            'answered_at' => now(),
        ]);

        $message = $this->_storeMessage($inquiry, $request, $request->input('body'));

        // 문의자에게 채팅으로 답변 전송
        $this->_sendInquiryReplyViaChat($inquiry, $request->input('body'));

        return response()->json([
            'success' => true,
            'message' => $this->_formatMessage($message),
            'status'  => $inquiry->status,
        ]);
    }

    public function panelDestroy(Inquiry $inquiry): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $inquiry->user_id !== $user->id) {
            abort(403);
        }

        // 첨부파일 삭제
        foreach ($inquiry->messages as $msg) {
            if ($msg->attachment_path) {
                Storage::disk('public')->delete($msg->attachment_path);
            }
        }

        $inquiry->delete();

        return response()->json(['success' => true]);
    }

    // ── 내부 헬퍼 ──────────────────────────────────────────────

    private function _storeMessage(Inquiry $inquiry, Request $request, ?string $body): InquiryMessage
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

    private function _formatMessage(InquiryMessage $msg): array
    {
        $msg->loadMissing('user');

        return [
            'id'              => $msg->id,
            'user_id'         => $msg->user_id,
            'user_name'       => $msg->user->name ?? '-',
            'user_initial'    => mb_substr($msg->user->name ?? '?', 0, 1),
            'is_admin'        => ($msg->user->role ?? '') === 'admin',
            'body'            => $msg->body,
            'attachment_path' => $msg->attachment_path,
            'attachment_name' => $msg->attachment_name,
            'attachment_size' => $msg->attachment_size,
            'is_image'        => $msg->is_image,
            'time_label'      => $msg->created_at->format('Y.m.d H:i'),
        ];
    }

    /**
     * 관리자 문의 답변을 1:1 채팅으로 자동 전송
     * - 관리자 ↔ 문의자 사이의 direct 채팅방을 찾거나 새로 생성
     * - 답변 내용을 채팅 메시지로 전송 및 broadcast
     */
    private function _sendInquiryReplyViaChat(Inquiry $inquiry, ?string $body): void
    {
        if (empty($body)) {
            return;
        }

        $adminId = Auth::id();
        $userId  = $inquiry->user_id;

        if ($adminId === $userId) {
            return;
        }

        // 기존 1:1 채팅방 조회
        $room = ChatRoom::where('type', 'direct')
            ->whereHas('users', fn($q) => $q->where('user_id', $adminId))
            ->whereHas('users', fn($q) => $q->where('user_id', $userId))
            ->first();

        // 없으면 새로 생성
        if (! $room) {
            $room = ChatRoom::create(['type' => 'direct', 'name' => null]);
            $room->users()->attach([$adminId, $userId]);
        }

        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'user_id'      => $adminId,
            'body'         => "[문의 답변] {$inquiry->title}\n\n{$body}",
        ]);

        // 발신자(admin) 읽음 처리
        $room->users()->updateExistingPivot($adminId, ['last_read_at' => now()]);

        try {
            broadcast(new ChatMessageSent($chatMessage))->toOthers();
        } catch (\Throwable $e) {
            \Log::error('[InquiryReply] chat broadcast 실패', ['error' => $e->getMessage()]);
        }
    }
}
