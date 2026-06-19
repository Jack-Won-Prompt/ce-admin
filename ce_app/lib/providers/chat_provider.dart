// lib/providers/chat_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/chat_room.dart';
import '../models/chat_message.dart';
import '../services/chat_service.dart';
import '../services/chat_notification_service.dart';

// ── 방 목록 ──────────────────────────────────────────────────
class ChatRoomsState {
  final List<ChatRoom>             rooms;
  final List<Map<String, dynamic>> users;  // 대화 가능 사용자
  final bool                       isLoading;
  final String?                    error;

  const ChatRoomsState({
    this.rooms    = const [],
    this.users    = const [],
    this.isLoading = false,
    this.error,
  });

  ChatRoomsState copyWith({
    List<ChatRoom>?             rooms,
    List<Map<String, dynamic>>? users,
    bool?                       isLoading,
    String?                     error,
  }) => ChatRoomsState(
    rooms:     rooms     ?? this.rooms,
    users:     users     ?? this.users,
    isLoading: isLoading ?? this.isLoading,
    error:     error,
  );
}

class ChatRoomsNotifier extends StateNotifier<ChatRoomsState> {
  final ChatService _service;
  ChatRoomsNotifier(this._service) : super(const ChatRoomsState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true);
    try {
      final data  = await _service.getRooms();
      final rooms = (data['rooms'] as List)
          .map((r) => ChatRoom.fromJson(r as Map<String, dynamic>))
          .toList();
      final users = (data['users'] as List)
          .map((u) => u as Map<String, dynamic>)
          .toList();
      state = ChatRoomsState(rooms: rooms, users: users);
      // 신규 채팅방을 포함해 Pusher 채널 구독 (이미 구독된 방은 무시됨)
      for (final room in rooms) {
        ChatNotificationService.instance.subscribeRoom(room.id);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<int> createRoom({
    required String type,
    required List<int> userIds,
    String? name,
  }) async {
    final roomId = await _service.createRoom(
        type: type, userIds: userIds, name: name);
    await load();
    return roomId;
  }

  /// 특정 방의 미읽음 배지 제거
  void clearUnread(int roomId) {
    state = state.copyWith(
      rooms: state.rooms.map((r) =>
        r.id == roomId
          ? ChatRoom(id: r.id, type: r.type, name: r.name, unread: 0,
                     latestBody: r.latestBody, latestTime: r.latestTime, members: r.members)
          : r,
      ).toList(),
    );
  }

  /// 실시간 수신 메시지로 방 목록 프리뷰 갱신
  void updatePreview(int roomId, String preview, String time) {
    state = state.copyWith(
      rooms: state.rooms.map((r) {
        if (r.id != roomId) return r;
        return ChatRoom(id: r.id, type: r.type, name: r.name,
          unread: r.unread + 1, latestBody: preview, latestTime: time,
          members: r.members);
      }).toList(),
    );
  }
}

final chatRoomsProvider =
    StateNotifierProvider<ChatRoomsNotifier, ChatRoomsState>((ref) {
  return ChatRoomsNotifier(ref.read(chatServiceProvider));
});

// ── 현재 방 메시지 ───────────────────────────────────────────
class ChatMessagesState {
  final int              roomId;
  final List<ChatMessage> messages;
  final bool             isLoading;
  final bool             hasMore;
  final int              page;

  const ChatMessagesState({
    required this.roomId,
    this.messages  = const [],
    this.isLoading = false,
    this.hasMore   = false,
    this.page      = 1,
  });

  ChatMessagesState copyWith({
    List<ChatMessage>? messages,
    bool?              isLoading,
    bool?              hasMore,
    int?               page,
  }) => ChatMessagesState(
    roomId:    roomId,
    messages:  messages  ?? this.messages,
    isLoading: isLoading ?? this.isLoading,
    hasMore:   hasMore   ?? this.hasMore,
    page:      page      ?? this.page,
  );
}

class ChatMessagesNotifier extends StateNotifier<ChatMessagesState> {
  final ChatService _service;

  ChatMessagesNotifier(this._service, int roomId)
      : super(ChatMessagesState(roomId: roomId));

  Future<void> load() async {
    state = state.copyWith(isLoading: true);
    try {
      final data = await _service.getMessages(state.roomId);
      final msgs = (data['messages'] as List)
          .map((m) => ChatMessage.fromJson(m as Map<String, dynamic>))
          .toList();
      state = state.copyWith(
        messages:  msgs,
        hasMore:   data['has_more'] as bool? ?? false,
        page:      1,
        isLoading: false,
      );
      await _service.markRead(state.roomId);
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoading: true);
    try {
      final nextPage = state.page + 1;
      final data = await _service.getMessages(state.roomId, page: nextPage);
      final older = (data['messages'] as List)
          .map((m) => ChatMessage.fromJson(m as Map<String, dynamic>))
          .toList();
      state = state.copyWith(
        messages:  [...older, ...state.messages],
        hasMore:   data['has_more'] as bool? ?? false,
        page:      nextPage,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> sendText(String body) async {
    final msg = await _service.sendText(state.roomId, body);
    state = state.copyWith(messages: [...state.messages, msg]);
  }

  Future<void> sendFile(String path, String name) async {
    final msg = await _service.sendFile(state.roomId, path, name);
    state = state.copyWith(messages: [...state.messages, msg]);
  }

  void addMessage(ChatMessage msg) {
    if (state.messages.any((m) => m.id == msg.id)) return;
    state = state.copyWith(messages: [...state.messages, msg]);
  }
}

final chatMessagesProvider = StateNotifierProvider.family<
    ChatMessagesNotifier, ChatMessagesState, int>((ref, roomId) {
  return ChatMessagesNotifier(ref.read(chatServiceProvider), roomId);
});
