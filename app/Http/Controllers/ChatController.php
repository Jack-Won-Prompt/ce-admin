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

        $messages = $room->messages()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(40);

        // 읽음 처리
        $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        $items = collect($messages->items())->reverse()->values()->map(fn(ChatMessage $m) => [
            'id'              => $m->id,
            'user_id'         => $m->user_id,
            'screen_name'     => $this->resolveSenderScreenName($room, $m),
            'user_name'       => $m->user?->name ?? 'CE샵 고객',
            'body'            => $this->stripScreenTag($m->body),
            'attachment_path' => $m->attachment_path,
            'attachment_name' => $m->attachment_name,
            'attachment_mime' => $m->attachment_mime,
            'is_image'        => $m->isImage(),
            'time_label'      => $m->created_at->format('H:i'),
            'created_at'      => $m->created_at->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'messages'   => $items,
            'has_more'   => $messages->hasMorePages(),
            'next_page'  => $messages->currentPage() + 1,
        ]);
    }

    /** POST /chat/rooms/{room}/messages — 메시지 전송 */
    public function sendMessage(ChatRoom $room, Request $request): JsonResponse
    {
        $this->authorizeRoom($room);

        $request->validate([
            'body'       => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:20480', // 20MB
        ]);

        $data = [
            'chat_room_id' => $room->id,
            'user_id'      => Auth::id(),
            'body'         => $request->body,
        ];

        if ($request->hasFile('attachment')) {
            $file  = $request->file('attachment');
            $path  = $file->store('chat_attachments', 'public');
            $data += [
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        if (empty($data['body']) && empty($data['attachment_path'])) {
            return response()->json(['error' => '내용을 입력해주세요.'], 422);
        }

        $message = ChatMessage::create($data);

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

        $message->load('user');
        return response()->json([
            'id'              => $message->id,
            'user_id'         => $message->user_id,
            'user_name'       => $message->user->name,
            'screen_name'     => $this->resolveSenderScreenName($room, $message),
            'body'            => $this->stripScreenTag($message->body),
            'attachment_path' => $message->attachment_path,
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'is_image'        => $message->isImage(),
            'time_label'      => $message->created_at->format('H:i'),
            'created_at'      => $message->created_at->format('Y-m-d H:i:s'),
        ]);
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
