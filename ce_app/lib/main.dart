// lib/main.dart

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'router/app_router.dart';
import 'services/chat_notification_service.dart';
import 'services/update_service.dart';

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

class CeAdminApp extends ConsumerStatefulWidget {
  const CeAdminApp({super.key});

  @override
  ConsumerState<CeAdminApp> createState() => _CeAdminAppState();
}

class _CeAdminAppState extends ConsumerState<CeAdminApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    /* 새 판이 나왔는지 서버에 묻는다 (2026-09-29 지시) — 스토어를 쓰지 않고 APK 를
       직접 나눠 주므로, 스토어가 해 주던 안내를 우리가 한다. */
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      UpdateService.checkAndUpdate(context);
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // 다시 앞으로 올 때마다 새 판을 다시 묻는다 — 최소 판 미만이면 그때 막힌다
    if (state == AppLifecycleState.resumed) {
      UpdateService.checkAndUpdate(context);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
