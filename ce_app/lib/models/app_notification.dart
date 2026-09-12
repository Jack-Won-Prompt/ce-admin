// lib/models/app_notification.dart
// 앱으로 온 알림 한 건.

class AppNotification {
  final int     id;
  final String  title;
  final String? body;

  /// 갈래 — chat · rx_reupload 등. 모르는 갈래면 글만 보여 준다.
  final String? type;

  /// 이 알림이 가리키는 화면의 키. 갈래마다 키 이름이 다른데 서버가 한 가지
  /// 이름으로 모아 준다 — 앱이 갈래별 키 이름을 다 알 까닭이 없다.
  final String? targetKey;

  /// 보내려 했으나 실패한 것도 이력에는 남는다.
  final bool    sent;
  final bool    isRead;
  final String  createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    this.body,
    this.type,
    this.targetKey,
    required this.sent,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
        id:        (j['id'] as num).toInt(),
        title:     j['title'] as String? ?? '알림',
        body:      j['body'] as String?,
        type:      j['type'] as String?,
        targetKey: j['target_key'] as String?,
        sent:      j['sent'] as bool? ?? true,
        isRead:    j['is_read'] as bool? ?? false,
        createdAt: j['created_at'] as String? ?? '',
      );

  /// 눌러서 갈 곳이 있는가.
  bool get hasTarget =>
      targetKey != null &&
      targetKey!.isNotEmpty &&
      (type == 'chat' || type == 'rx_reupload');
}
