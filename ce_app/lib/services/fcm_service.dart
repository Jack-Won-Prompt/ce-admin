// lib/services/fcm_service.dart
// FCM 토큰 관리 + 알림 탭 처리

import 'package:dio/dio.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'chat_notification_service.dart';

class FcmService {
  FcmService._();
  static final instance = FcmService._();

  Future<void> init(Dio dio) async {
    final messaging = FirebaseMessaging.instance;

    // iOS 알림 권한 요청
    await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    // Android 13+ 알림 권한은 flutter_local_notifications에서 이미 처리됨

    // FCM 토큰 조회 후 서버 전송
    final token = await messaging.getToken();
    if (token != null) {
      debugPrint('[FCM] 토큰: $token');
      await _sendToken(dio, token);
    }

    // 토큰 갱신 시 서버 업데이트
    messaging.onTokenRefresh.listen((newToken) {
      debugPrint('[FCM] 토큰 갱신: $newToken');
      _sendToken(dio, newToken);
    });

    // 앱 완전 종료 후 알림 탭으로 실행된 경우
    final initial = await messaging.getInitialMessage();
    if (initial != null) _handleTap(initial);

    // 앱 백그라운드 상태에서 알림 탭
    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);
  }

  void _handleTap(RemoteMessage message) {
    final roomId = int.tryParse(message.data['room_id'] ?? '');
    if (roomId != null) {
      ChatNotificationService.instance.onTap?.call(roomId);
    }
  }

  Future<void> _sendToken(Dio dio, String token) async {
    try {
      await dio.post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (e) {
      debugPrint('[FCM] 토큰 전송 실패: $e');
    }
  }
}
