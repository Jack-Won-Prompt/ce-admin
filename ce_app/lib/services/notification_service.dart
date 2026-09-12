// lib/services/notification_service.dart
// 앱으로 보낸 알림 이력.

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/app_notification.dart';
import 'api_client.dart';

class NotificationPage {
  final List<AppNotification> items;
  final int  total;
  final int  unread;
  final bool hasMore;

  const NotificationPage({
    required this.items,
    required this.total,
    required this.unread,
    required this.hasMore,
  });
}

class NotificationService {
  final Dio _dio;
  NotificationService(this._dio);

  Future<NotificationPage> list({int page = 1, bool unreadOnly = false}) async {
    try {
      final res = await _dio.get('/notifications', queryParameters: {
        'page': page,
        if (unreadOnly) 'unread': 1,
      });

      final body = res.data as Map<String, dynamic>;
      final meta = (body['meta'] as Map?) ?? const {};

      return NotificationPage(
        items: ((body['data'] as List?) ?? const [])
            .map((e) =>
                AppNotification.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        total:   (meta['total']  as num?)?.toInt() ?? 0,
        unread:  (meta['unread'] as num?)?.toInt() ?? 0,
        hasMore: ((meta['current_page'] as num?)?.toInt() ?? 1) <
                 ((meta['last_page'] as num?)?.toInt() ?? 1),
      );
    } on DioException catch (e) {
      final body = e.response?.data;
      throw Exception((body is Map ? body['message'] as String? : null) ??
          '알림을 불러오지 못했습니다. (${e.type.name})');
    }
  }

  /// 읽음 표시는 조용히 한다 — 실패해도 화면을 막지 않는다.
  Future<void> markRead(int id) async {
    try {
      await _dio.post('/notifications/$id/read');
    } catch (_) {}
  }

  Future<void> markAllRead() async {
    try {
      await _dio.post('/notifications/read-all');
    } catch (_) {}
  }
}

final notificationServiceProvider = Provider<NotificationService>(
  (ref) => NotificationService(ref.read(dioProvider)),
);
