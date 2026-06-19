// lib/main.dart

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'router/app_router.dart';
import 'services/chat_notification_service.dart';

/// 앱이 완전히 종료된 상태에서 FCM 메시지 수신 핸들러
/// OS가 자동으로 알림 표시 — 별도 처리 불필요
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Firebase 초기화
  await Firebase.initializeApp();

  // 백그라운드/종료 상태 FCM 핸들러 등록
  FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

  // 채팅 로컬 알림 초기화 (1회)
  await ChatNotificationService.instance.init();

  runApp(
    const ProviderScope(
      child: CeAdminApp(),
    ),
  );
}

class CeAdminApp extends ConsumerWidget {
  const CeAdminApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'CE Admin',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1565C0),
          brightness: Brightness.light,
        ),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF1565C0),
          foregroundColor: Colors.white,
          elevation: 0,
          centerTitle: false,
        ),
        inputDecorationTheme: const InputDecorationTheme(
          border: OutlineInputBorder(),
        ),
      ),
      routerConfig: router,
    );
  }
}
