<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Helpers\FcmHelper;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    private const SCREEN_TAG_PATTERN = '/(?:^|\R)\[[^\]\r\n]+\]\s*(.+)$/u';

    /** GET /chat/rooms — 내 채팅방 목록 */
    public function rooms(): JsonResponse
    {
        $me = Auth::id();

        $rooms = ChatRoom::whereHas('users', fn($q) => $q->where('user_id', $me))
            ->with(['users', 'latestMessage.user'])
            ->get()
            ->map(function (ChatRoom $room) use ($me) {
                $latest = $room->latestMessage;

                $shopInfo = null;
                if ($room->shop_user_name) {
                    $patient = \DB::table('patients')
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($room) {
                            $q->where('name', $room->shop_user_name);
                            if ($room->shop_user_phone) {
                                $q->where(function ($q2) use ($room) {
                                    $q2->where('mobile', $room->shop_user_phone)
                                       ->orWhere('phone', $room->shop_user_phone);
                                });
                            }
                        })
                        ->select('id', 'name', 'mobile', 'phone')
                        ->first();

                    $shopInfo = [
                        'name'       => $room->shop_user_name,
                        'phone'      => $room->shop_user_phone,
                        'email'      => $room->shop_user_email,
                        'patient_id' => $patient?->id,
                    ];
                }

                return [
                    'id'           => $room->id,
                    'type'         => $room->type,
                    'category'     => $this->resolveRoomCategory($room, $me),
                    'name'         => $room->displayName($me),
                    'unread'       => $room->unreadCount($me),
                    'latest_body'  => $this->buildLatestPreview($latest, $me),
                    'latest_time'  => $latest?->created_at?->format('H:i'),
                    'members'      => $room->users->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
                    'shop_info'    => $shopInfo,
                ];
            })
            ->sortByDesc(fn($r) => $r['latest_time'])
            ->values();

        // 대화 가능한 전체 사용자 목록
        $users = User::where('id', '!=', $me)->select('id', 'name', 'role')->get();

        return response()->json(['rooms' => $rooms, 'users' => $users]);
    }

    /** POST /chat/rooms — 1:1 방 생성 or 기존 방 반환 */
    public function createRoom(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'required|in:direct,group',
            'user_ids' => 'required|array|min:1',
            'name'     => 'nullable|string|max:80',
        ]);

        $me      = Auth::id();
        $userIds = array_unique(array_merge([$me], $request->user_ids));

        if ($request->type === 'direct' && count($userIds) === 2) {
            $otherId = collect($userIds)->first(fn($id) => $id !== $me);

            // 이미 1:1 방이 있으면 재사용
            $existing = ChatRoom::where('type', 'direct')
                ->whereHas('users', fn($q) => $q->where('user_id', $me))
                ->whereHas('users', fn($q) => $q->where('user_id', $otherId))
                ->first();

            if ($existing) {
                return response()->json(['room_id' => $existing->id]);
            }
        }

        $room = ChatRoom::create([
            'type' => $request->type,
            'name' => $request->type === 'group' ? $request->name : null,
        ]);
        $room->users()->attach($userIds);

        return response()->json(['room_id' => $room->id]);
    }

    /** GET /chat/rooms/{room}/messages — 메시지 목록 (페이징) */
    public function messages(ChatRoom $room, Request $request): JsonResponse
    {
        $this->authorizeRoom($room);

        /* 지운 메시지도 함께 가져온다 — 답글이 매달려 있으면 원본이 사라질 때 대화가 끊긴다.
           본문 대신 '삭제된 메시지입니다' 자리만 남긴다. */
        $messages = $room->messages()
            ->withTrashed()
            ->with(['user', 'replyTo.user'])
            ->threadOrdered('desc')
            ->paginate(40);

        // 읽음 처리
        $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        $items = collect($messages->items())->reverse()->values()
            ->map(fn (ChatMessage $m) => $this->presentMessage($room, $m));

        return response()->json([
            'messages'   => $items,
            'has_more'   => $messages->hasMorePages(),
            'next_page'  => $messages->currentPage() + 1,
        ]);
    }

    /** PUT /chat/messages/{message} — 내가 보낸 메시지 고치기 */
    public function updateMessage(ChatMessage $message, Request $request): JsonResponse
    {
        $room = $message->room;
        $this->authorizeRoom($room);
        $this->authorizeOwnMessage($message);

        $request->validate(['body' => 'required|string|max:5000']);

        $message->update([
            'body'      => $request->body,
            'edited_at' => now(),
        ]);

        $body = $this->stripScreenTag($message->body);

        try {
            broadcast(new ChatMessageChanged($message, 'edited', $body))->toOthers();
        } catch (\Throwable $e) {
            \Log::error('[Chat] 수정 broadcast 실패', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok'          => true,
            'id'          => $message->id,
            'body'        => $body,
            'edited_at'   => $message->edited_at->format('Y-m-d H:i:s'),
            'edited_label'=> '수정됨',
        ]);
    }

    /** DELETE /chat/messages/{message} — 내가 보낸 메시지 지우기 */
    public function deleteMessage(ChatMessage $message): JsonResponse
    {
        $room = $message->room;
        $this->authorizeRoom($room);
        $this->authorizeOwnMessage($message);

        // 소프트 삭제. 답글이 가리키는 원본이 통째로 사라지면 대화 맥락이 끊긴다.
        $message->delete();

        try {
            broadcast(new ChatMessageChanged($message, 'deleted'))->toOthers();
        } catch (\Throwable $e) {
            \Log::error('[Chat] 삭제 broadcast 실패', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'id' => $message->id]);
    }

    /** 화면에 내려보내는 메시지 한 건의 모양 — 목록·전송·답글이 모두 이 모양을 쓴다 */
    private function presentMessage(ChatRoom $room, ChatMessage $m): array
    {
        $deleted = (bool) $m->deleted_at;
        $parent  = $m->replyTo;

        return [
            'id'              => $m->id,
            'user_id'         => $m->user_id,
            'screen_name'     => $deleted ? null : $this->resolveSenderScreenName($room, $m),
            /* 보낸 사람이 없는 메시지는 둘 중 하나다 — CE샵 손님이 남긴 것이거나,
               우리가 남긴 알림이거나. 방을 보면 갈린다. */
            'user_name'       => $m->user?->name ?? ($room->shop_user_name ? 'CE샵 고객' : '알림'),
            'body'            => $deleted ? null : $this->stripScreenTag($m->body),
            'attachment_path' => $deleted ? null : $m->attachment_path,
            'attachment_name' => $deleted ? null : $m->attachment_name,
            'attachment_mime' => $deleted ? null : $m->attachment_mime,
            'attachment_size' => $deleted ? null : $m->attachment_size,
            'is_image'        => $deleted ? false : $m->isImage(),
            'is_deleted'      => $deleted,
            'edited_at'       => $m->edited_at?->format('Y-m-d H:i:s'),
            'reply_to_id'     => $m->reply_to_id,
            'reply_to'        => $parent ? [
                'id'        => $parent->id,
                'user_name' => $parent->user?->name ?? 'CE샵 고객',
                'body'      => $parent->deleted_at
                    ? null
                    : ($this->stripScreenTag($parent->body) ?: ($parent->attachment_name ? '📎 '.$parent->attachment_name : null)),
                'is_deleted'=> (bool) $parent->deleted_at,
            ] : null,
            'time_label'      => $m->created_at->format('H:i'),
            'created_at'      => $m->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /** 남의 메시지는 고치거나 지울 수 없다 */
    private function authorizeOwnMessage(ChatMessage $message): void
    {
        abort_unless($message->user_id === Auth::id(), 403, '본인이 보낸 메시지만 수정·삭제할 수 있습니다.');
    }

    /** POST /chat/rooms/{room}/messages — 메시지 전송 */
    public function sendMessage(ChatRoom $room, Request $request): JsonResponse
    {
        $this->authorizeRoom($room);

        $request->validate([
            'body'          => 'nullable|string|max:5000',
            'attachment'    => 'nullable|file|max:20480', // 20MB
            // 여러 장을 한 번에 올린다(2026-09-02 요청서). 한 메시지에 한 장이라는
            // 지금 모양은 그대로 두고, 장수만큼 메시지를 세운다 — 표를 고치지 않아도
            // 그리는 쪽과 알림이 그대로 돈다.
            'attachments'   => 'nullable|array|max:20',
            'attachments.*' => 'file|max:20480',
            'reply_to_id'   => 'nullable|integer|exists:chat_messages,id',
        ]);

        // 답글은 같은 방 안에서만. 다른 방 메시지 id 를 넣어 방을 넘나드는 것을 막는다.
        $replyToId = null;
        if ($request->filled('reply_to_id')) {
            $parent = ChatMessage::withTrashed()->find($request->reply_to_id);
            if (!$parent || $parent->chat_room_id !== $room->id) {
                return response()->json(['error' => '답글 대상을 찾을 수 없습니다.'], 422);
            }
            $replyToId = $parent->id;
        }

        $data = [
            'chat_room_id' => $room->id,
            'user_id'      => Auth::id(),
            'body'         => $request->body,
            'reply_to_id'  => $replyToId,
        ];

        /* 올라온 파일을 한 줄로 모은다 — 예전 이름(attachment) 한 장과 새 이름
           (attachments[]) 여러 장을 함께 받는다. 앱이나 옛 화면이 예전 이름으로
           보내는 것을 끊지 않는다. */
        $files = array_values(array_filter(array_merge(
            $request->hasFile('attachment') ? [$request->file('attachment')] : [],
            $request->file('attachments') ?? []
        )));

        if (empty($data['body']) && ! $files) {
            return response()->json(['error' => '내용을 입력해주세요.'], 422);
        }

        /* 첫 장이 글을 싣는다. 뒷장은 글 없이 파일만 — 같은 글이 장수만큼 되풀이되면
           읽는 사람은 무엇이 새 말인지 가릴 수 없다. */
        $messages = [];

        foreach ($files ?: [null] as $i => $file) {
            $row = $data;
            if ($i > 0) {
                $row['body'] = null;
            }

            if ($file) {
                $path = $file->store('chat_attachments', 'public');
                $row += [
                    'attachment_path' => $path,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getMimeType(),
                    'attachment_size' => $file->getSize(),
                ];
            }

            $messages[] = ChatMessage::create($row);
        }

        /* 뒷장들은 여기서 마무리한다 — 묶음ㆍ방송ㆍ알림은 아래가 첫 장에 대고 한다.
           알림을 장수만큼 보내면 받는 사람 화면이 같은 소식으로 도배된다. */
        foreach (array_slice($messages, 1) as $extra) {
            ChatMessage::attachToThread($extra);

            try {
                broadcast(new ChatMessageSent($extra))->toOthers();
            } catch (\Throwable $e) {
                \Log::error('[Chat] broadcast 실패', ['error' => $e->getMessage()]);
            }
        }

        $message = $messages[0];

        // 묶음 정보를 정하고, 답글이면 그 대화 묶음을 통째로 최근 위치로 끌어올린다.
        ChatMessage::attachToThread($message);

        // 읽음 처리 (발신자)
        $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        try {
            broadcast(new ChatMessageSent($message))->toOthers();
            \Log::info('[Chat] broadcast 성공', ['room' => $room->id, 'msg' => $message->id]);
        } catch (\Throwable $e) {
            \Log::error('[Chat] broadcast 실패', ['error' => $e->getMessage()]);
        }

        // FCM 푸시 알림 — 앱이 백그라운드/종료 상태인 수신자에게 전송
        try {
            $senderId   = Auth::id();
            $msgBody    = $this->stripScreenTag($message->body) ?? '📎 첨부파일';
            $senderName = $message->user->name;

            $room->users()
                ->where('user_id', '!=', $senderId)
                ->whereNotNull('fcm_token')
                ->get()
                ->each(function ($member) use ($senderName, $msgBody, $room) {
                    FcmHelper::sendChatMessage(
                        $member->fcm_token,
                        $senderName,
                        $msgBody,
                        $room->id
                    );
                });
        } catch (\Throwable $e) {
            \Log::warning('[Chat] FCM 전송 건너뜀', ['error' => $e->getMessage()]);
        }

        $message->load(['user', 'replyTo.user']);

        /* 여러 장을 보냈으면 다 돌려준다. 첫 장만 돌려주면 보낸 사람 화면에 나머지가
           안 보이고, 새로 고쳐야 나타난다. 한 장이면 예전 그대로 낱개로 돌려준다 —
           앱이 그 모양을 읽는다. */
        if (count($messages) > 1) {
            return response()->json(collect($messages)
                ->map(fn ($m) => $this->presentMessage($room, $m->load(['user', 'replyTo.user'])))
                ->all());
        }

        return response()->json($this->presentMessage($room, $message));
    }

    /**
     * GET /chat/rooms/{room}/attachments — 그 방의 첨부를 통째로 받는다.
     *
     * 여러 장을 주고받는 방에서 하나씩 눌러 받으려면 스무 번을 누른다(2026-09-02
     * 요청서). 한 벌로 묶어 준다.
     *
     * 지워진 메시지의 첨부는 담지 않는다 — 지운 것을 다시 꺼내 주는 셈이다.
     * 파일이 서버에 없는 줄도 건너뛴다(발행은 다른 서버에서 한 건이 있다).
     */
    public function downloadAttachments(ChatRoom $room)
    {
        $this->authorizeRoom($room);

        $rows = ChatMessage::where('chat_room_id', $room->id)
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->get(['id', 'attachment_path', 'attachment_name', 'created_at']);

        $disk  = Storage::disk('public');
        $ready = $rows->filter(fn ($m) => $disk->exists($m->attachment_path));

        if ($ready->isEmpty()) {
            return back()->withErrors(['chat' => '받을 첨부가 없습니다.']);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'chat_');
        $zip     = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['chat' => '묶음을 만들지 못했습니다.']);
        }

        /* 같은 이름이 여럿이면 뒤엣것이 앞엣것을 덮어쓴다 — 메시지 번호를 앞에 붙여
           가른다. 받은 사람이 언제 온 것인지도 그 번호로 알 수 있다. */
        foreach ($ready as $m) {
            $name = sprintf('%05d_%s', $m->id, $m->attachment_name ?: basename($m->attachment_path));
            $zip->addFile($disk->path($m->attachment_path), $name);
        }

        $zip->close();

        /* 방 이름이 파일 이름이 된다 — 윈도우가 싫어하는 글자를 밑줄로 바꾼다.
           이름 없는 방(1:1 대화)은 번호로 부른다. */
        $name = trim((string) $room->name) ?: ('방' . $room->id);
        $name = str_replace(['\\', '/', ':', '*', '?', '\"', '<', '>', '|'], '_', $name);

        $file = sprintf('채팅첨부_%s_%s.zip', $name, now()->format('Ymd_His'));

        return response()->download($zipPath, $file)->deleteFileAfterSend(true);
    }

    /** POST /chat/rooms/{room}/read — 읽음 처리 */
    public function markRead(ChatRoom $room): JsonResponse
    {
        $this->authorizeRoom($room);
        $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /** 채팅방 접근 권한 확인 */
    private function authorizeRoom(ChatRoom $room): void
    {
        abort_unless(
            $room->users()->where('user_id', Auth::id())->exists(),
            403,
            '채팅방 접근 권한이 없습니다.'
        );
    }

    private function buildLatestPreview(?ChatMessage $message, int $viewerId): ?string
    {
        if (! $message) {
            return null;
        }

        $body = $this->stripScreenTag($message->body);
        if (blank($body) && $message->attachment_name) {
            $body = '📎 '.$message->attachment_name;
        }

        if (blank($body)) {
            return null;
        }

        $senderName = $message->user_id !== $viewerId ? ($message->user?->name ?? null) : null;

        return $senderName ? "{$senderName}: {$body}" : $body;
    }

    private function resolveSenderScreenName(ChatRoom $room, ChatMessage $message): ?string
    {
        $companyRoles = ['admin', 'manager', 'super_admin', 'operations_admin', 'company_admin', 'approver'];
        $senderRole = $message->user?->role;

        if ($senderRole && in_array($senderRole, $companyRoles, true)) {
            return null;
        }

        if ($message->user && $room->shop_user_name && $message->user->name === $room->shop_user_name) {
            return null;
        }

        return $this->extractScreenName($message->body);
    }

    private function extractScreenName(?string $body): ?string
    {
        if (blank($body)) {
            return null;
        }

        if (! preg_match(self::SCREEN_TAG_PATTERN, $body, $matches)) {
            return null;
        }

        $screenName = trim((string) ($matches[1] ?? ''));

        return $screenName !== '' ? $screenName : null;
    }

    private function stripScreenTag(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        return rtrim((string) preg_replace(self::SCREEN_TAG_PATTERN, '', $body));
    }

    private function resolveRoomCategory(ChatRoom $room, int $myUserId): string
    {
        if ($room->shop_user_name || $room->shop_user_phone || $room->shop_user_email) {
            return 'customer';
        }

        $companyRoles = ['admin', 'manager', 'super_admin', 'operations_admin', 'company_admin', 'approver'];
        $otherUsers = $room->users->where('id', '!=', $myUserId);

        if ($otherUsers->isEmpty()) {
            return 'company';
        }

        $hasCustomerUser = $otherUsers->contains(function ($user) use ($companyRoles) {
            return ! in_array($user->role, $companyRoles, true);
        });

        return $hasCustomerUser ? 'customer' : 'company';
    }
}
