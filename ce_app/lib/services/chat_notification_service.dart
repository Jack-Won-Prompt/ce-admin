// lib/services/chat_notification_service.dart
// 앱 전역 채팅 실시간 알림 서비스 (싱글턴)

import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/chat_message.dart';
import '../utils/constants.dart';

class ChatNotificationService {
  ChatNotificationService._();
  static final instance = ChatNotificationService._();

  final _localNotif = FlutterLocalNotificationsPlugin();
  PusherChannelsFlutter? _pusher;
  final Set<int> _subscribed = {};

  /// 로그인한 사용자 ID — 자기 자신이 보낸 메시지는 알림 제외
  int? _currentUserId;

  /// 현재 화면에서 열려있는 방 ID (이 방은 알림 생략)
  int? activeRoomId;

  /// 알림 탭 → 해당 방으로 이동 콜백
  void Function(int roomId)? onTap;

  /// 현재 열린 채팅방에 메시지 전달 콜백
  void Function(ChatMessage msg)? onActiveRoomMessage;

  /// 백그라운드 방(현재 열리지 않은 방)에 메시지 수신 시 콜백
  void Function(int roomId, String preview, String time)? onBackgroundMessage;

  // reconnection
  String? _token;

  // ── 앱 시작 시 1회 초기화 ───────────────────────────────────
  Future<void> init() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios     = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    await _localNotif.initialize(
      const InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: (details) {
        final roomId = int.tryParse(details.payload ?? '');
        if (roomId != null) onTap?.call(roomId);
      },
    );

    final androidPlugin = _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();
    if (androidPlugin != null) {
      await androidPlugin.createNotificationChannel(
        const AndroidNotificationChannel(
          'chat_messages',
          '채팅 메시지',
          description: '실시간 채팅 알림',
          importance: Importance.high,
          playSound: true,
        ),
      );
      await androidPlugin.requestNotificationsPermission();
    }
  }

  // ── 로그인 후 Pusher 연결 + 전체 방 구독 ──────────────────────
  Future<void> connectAndSubscribe() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.keyAccessToken);
    if (token == null) return;

    _token = token;
    _currentUserId = prefs.getInt(AppConstants.keyUserId);

    // 이미 연결된 경우 재사용
    if (_pusher != null) {
      await _fetchAndSubscribeAll(token);
      return;
    }

    final pusherKey     = prefs.getString(AppConstants.keyPusherKey)     ?? AppConstants.pusherKeyFallback;
    final pusherCluster = prefs.getString(AppConstants.keyPusherCluster) ?? AppConstants.pusherClusterFallback;

    _pusher = PusherChannelsFlutter.getInstance();

    try {
      await _pusher!.init(
        apiKey:  pusherKey,
        cluster: pusherCluster,
        onConnectionStateChange: (cur, prev) async {
          debugPrint('[ChatNotif] $prev → $cur');
          // 연결될 때마다(최초 + 재연결) 구독 목록 초기화 후 전체 재구독
          if (cur == 'CONNECTED' && _token != null) {
            debugPrint('[ChatNotif] Connected — 채널 구독 시작');
            _subscribed.clear();
            await _fetchAndSubscribeAll(_token!);
          }
        },
        onError: (msg, code, e) =>
            debugPrint('[ChatNotif] 오류: $msg (code: $code)'),
        onAuthorizer: (channelName, socketId, opts) async {
          try {
            final client  = HttpClient()
              ..badCertificateCallback = (cert, host, port) => true;
            final uri     = Uri.parse('${AppConstants.baseUrl}/broadcasting/auth');
            final request = await client.postUrl(uri);
            request.headers.set('Authorization', 'Bearer $_token');
            request.headers.set('Accept', 'application/json');
            request.headers.contentType =
                ContentType('application', 'x-www-form-urlencoded');
            request.add(utf8.encode(
              'socket_id=${Uri.encodeComponent(socketId)}'
              '&channel_name=${Uri.encodeComponent(channelName)}',
            ));
            final response = await request.close();
            final body     = await response.transform(utf8.decoder).join();
            client.close();
            debugPrint('[ChatNotif] auth[$channelName] ${response.statusCode}: $body');
            if (response.statusCode != 200) return null;
            return jsonDecode(body);
          } catch (e) {
            debugPrint('[ChatNotif] auth 오류: $e');
            return null;
          }
        },
      );
      await _pusher!.connect();
      // 구독은 onConnectionStateChange(CONNECTED 이벤트)에서 처리됨
    } catch (e) {
      debugPrint('[ChatNotif] 연결 실패: $e');
      _pusher = null;
    }
  }

  // ── 방 목록 조회 후 전체 구독 ─────────────────────────────────
  Future<void> _fetchAndSubscribeAll(String token) async {
    try {
      final client  = HttpClient()
        ..badCertificateCallback = (cert, host, port) => true;
      final uri     = Uri.parse('${AppConstants.baseUrl}/chat/rooms');
      final request = await client.getUrl(uri);
      request.headers.set('Authorization', 'Bearer $token');
      request.headers.set('Accept', 'application/json');
      final response = await request.close();
      final body     = await response.transform(utf8.decoder).join();
      client.close();

      if (response.statusCode != 200) {
        debugPrint('[ChatNotif] 방 목록 오류: ${response.statusCode}');
        return;
      }
      final data  = jsonDecode(body) as Map<String, dynamic>;
      final rooms = (data['rooms'] as List?) ?? [];
      debugPrint('[ChatNotif] 구독할 방: ${rooms.length}개');
      for (final r in rooms) {
        await subscribeRoom(r['id'] as int);
      }
    } catch (e) {
      debugPrint('[ChatNotif] 방 목록 로드 실패: $e');
    }
  }

  // ── 개별 방 구독 (새 방 생성 / 채팅방 진입 시 호출) ───────────
  Future<void> subscribeRoom(int roomId) async {
    if (_pusher == null || _subscribed.contains(roomId)) return;
    _subscribed.add(roomId);  // 동시 중복 구독 방지
    debugPrint('[ChatNotif] 채널 구독 시도: private-chat.$roomId');

    try {
      await _pusher!.subscribe(
        channelName: 'private-chat.$roomId',
        onEvent: (event) {
          debugPrint('[ChatNotif] 이벤트: ${event.eventName} (방 $roomId)');
          if (event.eventName != 'message.sent') return;
          try {
            final raw  = event.data;
            final data = raw is String
                ? jsonDecode(raw) as Map<String, dynamic>
                : raw as Map<String, dynamic>;
            _onMessage(ChatMessage.fromJson(data), roomId);
          } catch (e) {
            debugPrint('[ChatNotif] 파싱 오류: $e');
          }
        },
      );
      debugPrint('[ChatNotif] 채널 구독 완료: private-chat.$roomId');
    } catch (e) {
      _subscribed.remove(roomId);  // 실패 시 재시도 가능하도록 해제
      debugPrint('[ChatNotif] 채널 구독 실패: private-chat.$roomId — $e');
    }
  }

  // ── 메시지 수신 처리 ─────────────────────────────────────────
  void _onMessage(ChatMessage msg, int roomId) {
    if (_currentUserId != null && msg.userId == _currentUserId) return;

    if (roomId == activeRoomId) {
      onActiveRoomMessage?.call(msg);
      return;
    }

    final preview = msg.body?.isNotEmpty == true
        ? msg.body!
        : msg.attachmentName != null
            ? '📎 ${msg.attachmentName}'
            : '새 메시지';

    onBackgroundMessage?.call(roomId, preview, msg.timeLabel);
    _show(id: roomId, title: msg.userName, body: preview, payload: '$roomId');
  }

  Future<void> _show({
    required int    id,
    required String title,
    required String body,
    required String payload,
  }) async {
    try {
      await _localNotif.show(
        id,
        title,
        body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            'chat_messages', '채팅 메시지',
            channelDescription: '실시간 채팅 알림',
            importance: Importance.high,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
            styleInformation: BigTextStyleInformation(body),
          ),
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
        payload: payload,
      );
    } catch (e) {
      debugPrint('[ChatNotif] 알림 표시 실패: $e');
    }
  }

  // ── 로그아웃 시 해제 ─────────────────────────────────────────
  Future<void> disconnect() async {
    _currentUserId = null;
    _token = null;
    for (final id in _subscribed) {
      try {
        await _pusher?.unsubscribe(channelName: 'private-chat.$id');
      } catch (_) {}
    }
    _subscribed.clear();
    try {
      await _pusher?.disconnect();
    } catch (_) {}
    _pusher = null;
  }
}
