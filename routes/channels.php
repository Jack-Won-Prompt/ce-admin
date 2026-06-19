<?php

use App\Models\ChatRoom;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Broadcast::channel() 호출 시 Pusher 드라이버가 즉시 초기화되므로
// 설정이 누락된 환경에서도 부팅 오류가 발생하지 않도록 try-catch 처리
try {
    Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });

    // 채팅방 채널 인증 — 방 참여자만 구독 허용
    Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
        return ChatRoom::whereHas(
            'users',
            fn($q) => $q->where('user_id', $user->id)
        )->where('id', $roomId)->exists();
    });

    // 어드민 전용 채널 — 인증된 관리자 전원 구독 허용 (위임동의 알림 등)
    Broadcast::channel('admin', function ($user) {
        return in_array($user->role, [
            'admin', 'manager', 'super_admin',
            'operations_admin', 'company_admin', 'approver',
        ]);
    });
} catch (\Throwable $e) {
    // Broadcasting 미설정 환경 — 채널 등록 건너뜀 (실시간 기능만 비활성화)
    \Illuminate\Support\Facades\Log::warning(
        'Broadcasting channels not registered: ' . $e->getMessage()
    );
}
