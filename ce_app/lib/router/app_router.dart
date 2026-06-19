// lib/router/app_router.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../screens/splash_screen.dart';
import '../screens/login_screen.dart';
import '../screens/otp_screen.dart';
import '../screens/main_shell.dart';
import '../screens/prescription_list_screen.dart';
import '../screens/prescription_detail_screen.dart';
import '../screens/prescription_upload_screen.dart';
import '../screens/chat_list_screen.dart';
import '../screens/chat_room_screen.dart';
import '../screens/notice_list_screen.dart';
import '../screens/notice_detail_screen.dart';
import '../screens/inquiry_list_screen.dart';
import '../screens/inquiry_create_screen.dart';
import '../screens/inquiry_detail_screen.dart';
import '../screens/settings_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/',
    redirect: (context, state) async {
      final authState = ref.read(authNotifierProvider);
      final loc       = state.matchedLocation;

      if (loc == '/') return null;

      // /login 또는 /login/otp는 인증 전 접근 허용
      final loggingIn = loc.startsWith('/login');
      return authState.when(
        data: (isLoggedIn) {
          if (!isLoggedIn && !loggingIn) return '/login';
          if (isLoggedIn  &&  loggingIn) return '/prescriptions';
          return null;
        },
        loading: () => null,
        error:   (_, __) => loggingIn ? null : '/login',
      );
    },
    routes: [
      GoRoute(
        path: '/',
        builder: (ctx, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (ctx, state) => const LoginScreen(),
        routes: [
          // 2단계 OTP 인증 화면
          GoRoute(
            path: 'otp',
            builder: (ctx, state) {
              final extra  = state.extra as Map<String, dynamic>? ?? {};
              final token  = extra['pendingToken'] as String? ?? '';
              final phone  = extra['maskedPhone']  as String? ?? '';
              // extra가 없을 때는 otpPendingProvider에서 읽음 (딥링크 대비)
              return OtpScreen(pendingToken: token, maskedPhone: phone);
            },
          ),
        ],
      ),

      // ── 하단 탭 쉘 ─────────────────────────────────────────
      StatefulShellRoute.indexedStack(
        builder: (ctx, state, shell) => MainShell(navigationShell: shell),
        branches: [
          // 탭 0 — 처방전 목록
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/prescriptions',
              builder: (ctx, state) => const PrescriptionListScreen(),
              routes: [
                GoRoute(
                  path: ':rxNumber',
                  builder: (ctx, state) {
                    final rxNumber = state.pathParameters['rxNumber']!;
                    return PrescriptionDetailScreen(rxNumber: rxNumber);
                  },
                ),
              ],
            ),
          ]),

          // 탭 1 — 처방전 업로드
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/upload',
              builder: (ctx, state) => const PrescriptionUploadScreen(),
            ),
          ]),

          // 탭 2 — 채팅
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/chat',
              builder: (ctx, state) => const ChatListScreen(),
              routes: [
                GoRoute(
                  path: ':roomId',
                  builder: (ctx, state) {
                    final roomId = int.parse(state.pathParameters['roomId']!);
                    final extra  = state.extra as Map<String, dynamic>?;
                    final name   = extra?['name'] as String? ?? '채팅';
                    return ChatRoomScreen(roomId: roomId, roomName: name);
                  },
                ),
              ],
            ),
          ]),

          // 탭 3 — 설정 (공지사항 · 문의하기 허브)
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/settings',
              builder: (ctx, state) => const SettingsScreen(),
            ),
          ]),
        ],
      ),

      // ── 공지사항 (셸 밖 — push 방식) ───────────────────────
      GoRoute(
        path: '/notices',
        builder: (ctx, state) => const NoticeListScreen(),
        routes: [
          GoRoute(
            path: ':noticeId',
            builder: (ctx, state) {
              final id = int.parse(state.pathParameters['noticeId']!);
              return NoticeDetailScreen(noticeId: id);
            },
          ),
        ],
      ),

      // ── 문의하기 (셸 밖 — push 방식) ───────────────────────
      GoRoute(
        path: '/inquiries',
        builder: (ctx, state) => const InquiryListScreen(),
        routes: [
          GoRoute(
            path: 'create',
            builder: (ctx, state) => const InquiryCreateScreen(),
          ),
          GoRoute(
            path: ':inquiryId',
            builder: (ctx, state) {
              final id = int.parse(state.pathParameters['inquiryId']!);
              return InquiryDetailScreen(inquiryId: id);
            },
          ),
        ],
      ),
    ],
  );
});
