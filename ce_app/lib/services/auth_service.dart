// lib/services/auth_service.dart
// 인증 서비스 — 로그인 2단계 (OTP) / 로그아웃 / 토큰 관리

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_client.dart';
import '../utils/constants.dart';

final authServiceProvider = Provider<AuthService>((ref) {
  return AuthService(ref.read(dioProvider));
});

/// 로그인 결과 — OTP 필요 또는 직접 로그인
class LoginResult {
  final bool   otpRequired;
  final String? pendingToken;
  final String? maskedPhone;

  const LoginResult.otp({required this.pendingToken, required this.maskedPhone})
      : otpRequired = true;

  const LoginResult.direct()
      : otpRequired = false, pendingToken = null, maskedPhone = null;
}

class AuthService {
  final Dio _dio;
  AuthService(this._dio);

  /// 로그인 — 서버가 직접 토큰을 반환하거나 OTP를 요구하는 두 케이스 처리
  Future<LoginResult> login(String email, String password) async {
    try {
      final res = await _dio.post('/auth/login', data: {
        'email':    email,
        'password': password,
      });

      final data = res.data;
      if (data is! Map) {
        throw Exception('서버 응답 형식 오류 (${res.statusCode})');
      }

      // 직접 토큰 반환 (OTP 없음)
      final token = data['token']?.toString();
      if (token != null) {
        final userId = data['user']?['id'] as int? ?? 0;
        final prefs  = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.keyAccessToken, token);
        await prefs.setInt(AppConstants.keyUserId, userId);
        return const LoginResult.direct();
      }

      // OTP 플로우
      final pendingToken = data['pending_token']?.toString();
      final maskedPhone  = data['masked_phone']?.toString();
      if (pendingToken == null || maskedPhone == null) {
        throw Exception(data['message']?.toString() ?? '알 수 없는 응답 형식');
      }
      return LoginResult.otp(pendingToken: pendingToken, maskedPhone: maskedPhone);

    } on DioException catch (e) {
      throw Exception(_extractMessage(e));
    }
  }

  /// 2단계: OTP 검증 → Sanctum Bearer 토큰 + Pusher 설정 저장
  Future<void> verifyOtp(String pendingToken, String code) async {
    try {
      final res = await _dio.post('/auth/verify-otp', data: {
        'pending_token': pendingToken,
        'code':          code,
      });
      final data   = res.data as Map<String, dynamic>;
      final token  = data['token'] as String;
      final userId = (data['user']?['id'] as num).toInt();
      final prefs  = await SharedPreferences.getInstance();
      await prefs.setString(AppConstants.keyAccessToken, token);
      await prefs.setInt(AppConstants.keyUserId, userId);
      final userName  = data['user']?['name']?.toString();
      final userEmail = data['user']?['email']?.toString();
      if (userName  != null) await prefs.setString(AppConstants.keyUserName,  userName);
      if (userEmail != null) await prefs.setString(AppConstants.keyUserEmail, userEmail);

      // Pusher 설정 저장
      final pusher = data['pusher'];
      if (pusher is Map) {
        final key     = pusher['key']?.toString();
        final cluster = pusher['cluster']?.toString();
        if (key     != null) await prefs.setString(AppConstants.keyPusherKey,     key);
        if (cluster != null) await prefs.setString(AppConstants.keyPusherCluster, cluster);
      }
    } on DioException catch (e) {
      throw Exception(_extractMessage(e));
    }
  }

  /// OTP 재발송 → 새 pending_token 반환
  Future<String> resendOtp(String pendingToken) async {
    try {
      final res = await _dio.post('/auth/resend-otp', data: {
        'pending_token': pendingToken,
      });
      return res.data['pending_token'] as String;
    } on DioException catch (e) {
      throw Exception(_extractMessage(e));
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } finally {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.keyAccessToken);
      await prefs.remove(AppConstants.keyUserId);
      await prefs.remove(AppConstants.keyUserName);
      await prefs.remove(AppConstants.keyUserEmail);
      await prefs.remove(AppConstants.keyPusherKey);
      await prefs.remove(AppConstants.keyPusherCluster);
    }
  }

  Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.containsKey(AppConstants.keyAccessToken);
  }

  /// DioException → 사용자 친화적 메시지 추출
  String _extractMessage(DioException e) {
    // 서버 JSON 응답의 message 필드 우선 사용
    final serverMsg = e.response?.data is Map
        ? e.response!.data['message'] as String?
        : null;
    if (serverMsg != null && serverMsg.isNotEmpty) return serverMsg;

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return '서버 응답 시간이 초과되었습니다. 네트워크를 확인해주세요.';
      case DioExceptionType.connectionError:
        return '서버에 연결할 수 없습니다. 네트워크를 확인해주세요.\n(${e.error})';
      case DioExceptionType.badResponse:
        return '서버 오류가 발생했습니다. (${e.response?.statusCode})';
      case DioExceptionType.badCertificate:
        return 'SSL 인증서 오류가 발생했습니다.\n(${e.error})';
      case DioExceptionType.unknown:
        return '네트워크 오류: ${e.error}';
      default:
        return '오류: ${e.type} — ${e.error}';
    }
  }
}
