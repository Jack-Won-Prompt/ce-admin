// lib/models/chat_message.dart

class ChatMessage {
  final int     id;
  final int     userId;
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
    required this.userId,
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
    userId:         (j['user_id'] as num).toInt(),
    userName:       j['user_name'] as String,
    body:           j['body'] as String?,
    attachmentPath: j['attachment_path'] as String?,
    attachmentName: j['attachment_name'] as String?,
    attachmentMime: j['attachment_mime'] as String?,
    isImage:        j['is_image'] as bool? ?? false,
    timeLabel:      j['time_label'] as String? ?? '',
    createdAt:      j['created_at'] as String? ?? '',
  );
}
