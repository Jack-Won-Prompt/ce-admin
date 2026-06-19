// lib/providers/auth_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../services/chat_notification_service.dart';
import '../services/fcm_service.dart';
import '../utils/constants.dart';

/// 현재 로그인한 사용자 이름 (전체 앱에서 공유)
final userNameProvider = StateProvider<String>((ref) => '');

/// 현재 로그인한 사용자 ID (채팅 bubble 구분용)
final userIdProvider = StateProvider<int?>((ref) => null);

// ── OTP 대기 상태 ─────────────────────────────────────────
class OtpPendingData {
  final String pendingToken;
  final String maskedPhone;
  const OtpPendingData({required this.pendingToken, required this.maskedPhone});
}

/// 1단계 로그인 성공 후 채워짐 → LoginScreen이 감지해 /login/otp로 이동
final otpPendingProvider = StateProvider<OtpPendingData?>((ref) => null);

// ── 로그인 상태 ───────────────────────────────────────────

/// 로그인 상태 (null = 미확인, true = 로그인, false = 비로그인)
final authStateProvider = FutureProvider<bool>((ref) async {
  return ref.read(authServiceProvider).isLoggedIn();
});

/// 로그인/로그아웃 액션
final authNotifierProvider =
    AsyncNotifierProvider<AuthNotifier, bool>(AuthNotifier.new);

class AuthNotifier extends AsyncNotifier<bool> {
  @override
  Future<bool> build() async {
    final loggedIn = await ref.read(authServiceProvider).isLoggedIn();
    if (loggedIn) {
      final prefs = await SharedPreferences.getInstance();
      ref.read(userNameProvider.notifier).state =
          prefs.getString(AppConstants.keyUserName) ?? '';
      ref.read(userIdProvider.notifier).state =
          prefs.getInt(AppConstants.keyUserId);
      ChatNotificationService.instance.connectAndSubscribe();
      FcmService.instance.init(ref.read(dioProvider));
    }
    return loggedIn;
  }

  /// 로그인 — 직접 토큰 또는 OTP 플로우 분기
  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    try {
      final result = await ref.read(authServiceProvider).login(email, password);
      if (result.otpRequired) {
        // OTP 화면으로 이동
        ref.read(otpPendingProvider.notifier).state = OtpPendingData(
          pendingToken: result.pendingToken!,
          maskedPhone:  result.maskedPhone!,
        );
        state = const AsyncData(false);
      } else {
        // 직접 로그인 완료
        state = const AsyncData(true);
        await ChatNotificationService.instance.connectAndSubscribe();
      }
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }

  /// 2단계: OTP 검증 → 최종 로그인
  Future<void> verifyOtp(String pendingToken, String code) async {
    state = const AsyncLoading();
    try {
      await ref.read(authServiceProvider).verifyOtp(pendingToken, code);
      ref.read(otpPendingProvider.notifier).state = null;
      final prefs = await SharedPreferences.getInstance();
      ref.read(userNameProvider.notifier).state =
          prefs.getString(AppConstants.keyUserName) ?? '';
      ref.read(userIdProvider.notifier).state =
          prefs.getInt(AppConstants.keyUserId);
      state = const AsyncData(true);
      await ChatNotificationService.instance.connectAndSubscribe();
      await FcmService.instance.init(ref.read(dioProvider));
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }

  /// OTP 재발송 → 새 pendingToken 반환
  Future<String> resendOtp(String pendingToken) async {
    final newToken = await ref.read(authServiceProvider).resendOtp(pendingToken);
    // otpPendingProvider의 pendingToken 갱신
    final prev = ref.read(otpPendingProvider);
    if (prev != null) {
      ref.read(otpPendingProvider.notifier).state = OtpPendingData(
        pendingToken: newToken,
        maskedPhone:  prev.maskedPhone,
      );
    }
    return newToken;
  }

  Future<void> logout() async {
    state = const AsyncLoading();
    try {
      await ChatNotificationService.instance.disconnect();
      await ref.read(authServiceProvider).logout();
      ref.read(userNameProvider.notifier).state = '';
      ref.read(userIdProvider.notifier).state = null;
      state = const AsyncData(false);
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }
}
