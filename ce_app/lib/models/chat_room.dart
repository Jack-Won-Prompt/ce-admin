// lib/models/chat_room.dart

class ChatRoomMember {
  final int    id;
  final String name;
  const ChatRoomMember({required this.id, required this.name});

  factory ChatRoomMember.fromJson(Map<String, dynamic> j) =>
      ChatRoomMember(id: (j['id'] as num).toInt(), name: j['name'] as String);
}

class ChatRoom {
  final int                id;
  final String             type;       // 'direct' | 'group'
  final String             name;
  final int                unread;
  final String?            latestBody;
  final String?            latestTime;
  final List<ChatRoomMember> members;

  const ChatRoom({
    required this.id,
    required this.type,
    required this.name,
    required this.unread,
    this.latestBody,
    this.latestTime,
    required this.members,
  });

  factory ChatRoom.fromJson(Map<String, dynamic> j) => ChatRoom(
    id:          (j['id']     as num).toInt(),
    type:        j['type']   as String,
    name:        j['name']   as String,
    unread:      (j['unread'] as num?)?.toInt() ?? 0,
    latestBody:  j['latest_body'] as String?,
    latestTime:  j['latest_time'] as String?,
    members: (j['members'] as List<dynamic>? ?? [])
        .map((m) => ChatRoomMember.fromJson(m as Map<String, dynamic>))
        .toList(),
  );
}
