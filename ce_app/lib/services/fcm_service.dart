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

  /// 알림을 눌러 들어왔을 때 어디로 보낼지.
  ///
  /// 갈래(type)와 그 갈래의 키를 보고 정한다. 서버가 보내는 갈래는 지금 둘이다 —
  /// chat(대화방)과 rx_reupload(다시 올릴 처방전). 모르는 갈래면 아무 곳으로도
  /// 보내지 않는다. 앱은 그냥 열리고, 알림 이력에서 글은 읽을 수 있다.
  void _handleTap(RemoteMessage message) {
    final data = message.data;

    switch (data['type']) {
      case 'rx_reupload':
        final rx = data['rx_number'];
        if (rx != null && rx.isNotEmpty) onPrescriptionTap?.call(rx);
        break;

      default:
        /* 갈래가 없던 옛 알림도 room_id 만 있으면 대화방으로 보낸다 —
           이미 나간 알림을 되돌릴 수 없다. */
        final roomId = int.tryParse(data['room_id'] ?? '');
        if (roomId != null) {
          ChatNotificationService.instance.onTap?.call(roomId);
        }
    }
  }

  /// 처방전 화면으로 보내 달라는 부름. 화면을 아는 쪽(MainShell)이 채운다.
  void Function(String rxNumber)? onPrescriptionTap;

  Future<void> _sendToken(Dio dio, String token) async {
    try {
      await dio.post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (e) {
      debugPrint('[FCM] 토큰 전송 실패: $e');
    }
  }
}
