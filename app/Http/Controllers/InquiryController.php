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
    /**
     * 환자 문의 목록.
     *
     * 예전에는 관리자가 아니면 자기가 올린 것만 보였다. 이 화면은 이제 환자가 올린
     * 문의를 담당자가 처리하는 자리라, 자기 것만 보이면 아무것도 못 한다 — 누가 볼지는
     * 권한 그룹이 정한다.
     */
    public function index(Request $request)
    {
        $query = Inquiry::with(['user', 'patient', 'answeredBy', 'messages']);

        // 날짜는 from~to 로 받는다 — 「지난주 것만」이 가장 잦은 물음이다
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // 이름은 환자에서 먼저 찾고, 앱 계정 이름도 함께 본다
        if ($name = trim((string) $request->input('name'))) {
            $query->where(fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$name}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$name}%")));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->get();

        $pendingCount = $rows->where('status', 'pending')->count();

        /* 시안의 칸 그대로 세운다 — 일시 · 문의자(ID · 이름) · 분류 · 제목 · 문의사항 ·
           회신방식 · 파일첨부 · 연락처 · 답변 · 처리자 · 처리일시. */
        $gridData = $rows->map(fn (Inquiry $i) => [
            'id'         => $i->id,
            'date'       => $i->created_at?->format('Y-m-d H:i') ?? '',
            'asker_id'   => $i->user?->email ?? '',
            'asker'      => $i->askerName(),
            'category'   => $i->categoryLabel(),
            'title'      => $i->title,
            'body'       => \Illuminate\Support\Str::limit($i->bodyText(), 60),
            'channel'    => $i->channelLabel(),
            'files'      => $i->attachmentCount() ?: '',
            'contact'    => $i->contactNumber(),
            'answer'     => \Illuminate\Support\Str::limit((string) $i->answer, 60),
            'status'     => $i->statusLabel(),
            'handler'    => $i->answeredBy?->name ?? '',
            'handled_at' => $i->answered_at?->format('Y-m-d H:i') ?? '',
        ]);

        $total = $gridData->count();

        return view('inquiries.index', compact('gridData', 'total', 'pendingCount'));
    }

    /**
     * 팝업이 읽는 한 건.
     *
     * 목록에서 더블클릭하면 열린다. 화면 이동 없이 답변까지 적으므로, 무엇을 보여
     * 주고 무엇을 적게 할지가 여기서 갈린다 — 하늘색(환자가 올린 것)은 읽기만 한다.
     */
    public function detail(Inquiry $inquiry): JsonResponse
    {
        $inquiry->load(['user', 'patient', 'answeredBy', 'messages']);

        return response()->json([
            'id'         => $inquiry->id,
            'date'       => $inquiry->created_at?->format('Y-m-d H:i'),
            'asker'      => $inquiry->askerName(),
            'asker_id'   => $inquiry->user?->email ?? '',
            'channel'    => $inquiry->channelLabel(),
            'contact'    => $inquiry->contactNumber(),
            'category'   => $inquiry->categoryLabel(),
            'title'      => $inquiry->title,
            'body'       => $inquiry->bodyText(),
            'files'      => $inquiry->messages
                ->filter(fn ($m) => $m->attachment_path)
                ->map(fn ($m) => [
                    'name' => $m->attachment_name,
                    'url'  => \Illuminate\Support\Facades\Storage::url($m->attachment_path),
                ])->values(),
            'status'      => $inquiry->status,
            'answer'      => $inquiry->answer,
            'action_note' => $inquiry->action_note,
            'handler'     => $inquiry->answeredBy?->name ?? '',
            'handled_at'  => $inquiry->answered_at?->format('Y-m-d H:i') ?? '',
        ]);
    }

    /**
     * 팝업에서 처리 내용을 담는다.
     *
     * 답변은 환자 앱·웹에 그대로 나가고 조치사항은 안에서만 본다. 처리자·처리일시는
     * 완료로 옮기는 순간 찍는다 — 적어 두다 만 것을 처리했다고 셈하면 안 된다.
     */
    public function handle(Request $request, Inquiry $inquiry): JsonResponse
    {
        $data = $request->validate([
            'status'      => 'required|in:' . implode(',', array_keys(Inquiry::STATUSES)),
            'answer'      => 'nullable|string',
            'action_note' => 'nullable|string',
        ]);

        $inquiry->update($data + [
            'answered_by' => $data['status'] === 'answered' ? Auth::id() : $inquiry->answered_by,
            'answered_at' => $data['status'] === 'answered' ? ($inquiry->answered_at ?? now()) : $inquiry->answered_at,
        ]);

        activity()->causedBy(Auth::user())->performedOn($inquiry)
            ->log("문의 처리 — {$inquiry->statusLabel()}");

        return response()->json(['ok' => true, 'status' => $inquiry->statusLabel()]);
    }

    /**
     * 환자를 찾는다 — 접수 폼의 조회 창이 쓴다.
     *
     * 이름만 적어 넣게 두면 동명이인을 가릴 수 없다. 고르면 연락처가 함께 채워진다.
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $kw = trim((string) $request->q);

        if (mb_strlen($kw) < 2) {
            return response()->json(['rows' => []]);
        }

        $digits = preg_replace('/[^0-9]/', '', $kw);

        $rows = \App\Models\Patient::where(fn ($q) => $q
                ->where('name', 'like', "%{$kw}%")
                ->when($digits !== '', fn ($s) => $s
                    ->orWhere('mobile', 'like', "%{$digits}%")
                    ->orWhere('phone', 'like', "%{$digits}%")))
            ->orderBy('name')->limit(30)
            ->get(['id', 'name', 'birth_date', 'mobile', 'phone']);

        return response()->json([
            'rows' => $rows->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'birth' => $p->birth_date?->format('Y-m-d') ?? '',
                'phone' => $p->mobile ?: ($p->phone ?? ''),
            ])->values(),
        ]);
    }

    public function create()
    {
        return view('inquiries.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'content'       => 'required|string',
            'category'      => 'required|in:' . implode(',', array_keys(Inquiry::CATEGORIES)),
            // 회신을 어디로 할지는 접수하며 담당자가 고른다 — 나중에 물으면 다시 전화해야 한다
            'reply_channel' => 'required|in:' . implode(',', array_keys(Inquiry::CHANNELS)),
            'patient_id'    => 'nullable|exists:patients,id',
            'contact'       => 'nullable|string|max:30',
            'attachment'    => 'nullable|file|max:10240',
        ]);

        $patient = $request->filled('patient_id')
            ? \App\Models\Patient::find($request->input('patient_id'))
            : null;

        $inquiry = Inquiry::create([
            'user_id'       => Auth::id(),
            'patient_id'    => $patient?->id,
            'title'         => $request->input('title'),
            'category'      => $request->input('category'),
            'reply_channel' => $request->input('reply_channel'),
            // 적어 준 것이 먼저다 — 환자 연락처가 바뀌어도 그때 회신한 곳이 남아야 한다
            'contact'       => $request->input('contact')
                ?: ($patient?->mobile ?: $patient?->phone),
            'content'       => $request->input('content'),
            'status'        => 'pending',
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
            // 이미 나간 앱이 옛 값을 보낸다 — 거절하면 환자가 문의를 못 한다
            'category'   => 'required|in:' . implode(',', array_merge(
                array_keys(Inquiry::CATEGORIES), array_keys(Inquiry::LEGACY_CATEGORIES))),
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
