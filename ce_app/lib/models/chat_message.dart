// lib/models/chat_message.dart

class ChatMessage {
  final int     id;

  /// 보낸 사람. 시스템이 남긴 알림(창고 알림·승인 요청)은 보낸 사람이 없어 비어 온다.
  /// 그때 서버는 이름 자리에 「알림」을 담아 보낸다.
  final int?    userId;

  final String  userName;
  final String? body;
  final String? attachmentPath;
  final String? attachmentName;
  final String? attachmentMime;
  final bool    isImage;
  final String  timeLabel;
  final String  createdAt;

  const ChatMessage({
    required this.id,
    this.userId,
    required this.userName,
    this.body,
    this.attachmentPath,
    this.attachmentName,
    this.attachmentMime,
    required this.isImage,
    required this.timeLabel,
    required this.createdAt,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
    id:             (j['id']      as num).toInt(),
    /* 알림에는 보낸 사람이 없다. 예전에는 이 자리에서 형 변환이 터져 목록 전체가
       비어 보였다 — 창고 알림·승인 요청 방이 통째로 빈 방처럼 보이던 까닭이다. */
    userId:         (j['user_id'] as num?)?.toInt(),
    userName:       j['user_name'] as String? ?? '알림',
    body:           j['body'] as String?,
    attachmentPath: j['attachment_path'] as String?,
    attachmentName: j['attachment_name'] as String?,
    attachmentMime: j['attachment_mime'] as String?,
    isImage:        j['is_image'] as bool? ?? false,
    timeLabel:      j['time_label'] as String? ?? '',
    createdAt:      j['created_at'] as String? ?? '',
  );
}
