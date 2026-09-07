// lib/services/auth_service.dart
// 인증 서비스 — 로그인 2단계 (OTP) / 로그아웃 / 토큰 관리

import 'dart:math';

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

  /// 이 기기를 가리는 값. 없으면 만들어 담아 둔다.
  ///
  /// 서버는 이 값으로 토큰 이름을 지어, 같은 계정이라도 기기마다 따로 쥔다.
  /// 앱을 지웠다 깔면 새 값이 되고, 서버에는 옛 기기의 토큰이 남는다 —
  /// 그 계정으로 다시 로그인할 때 같은 이름이 아니므로 지워지지 않지만,
  /// 지워진 앱은 이미 토큰을 잃었으니 쓰이지 않는다.
  Future<String> _deviceId() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(AppConstants.keyDeviceId);
    if (saved != null && saved.isNotEmpty) return saved;

    final rnd = Random.secure();
    final id  = List.generate(16, (_) => rnd.nextInt(256))
        .map((b) => b.toRadixString(16).padLeft(2, '0'))
        .join();

    await prefs.setString(AppConstants.keyDeviceId, id);
    return id;
  }

  /// 아이디·비밀번호로 들어가는 길이 열려 있는가(설정 › 서비스 연동 설정 › 로그인).
  /// 못 물어보면 열려 있다고 본다 — 잠깐 서버가 안 열렸다고 들어갈 길까지
  /// 감추면, 정작 쓸 수 있는 사람이 아무것도 못 한다.
  Future<bool> passwordLoginEnabled() async {
    try {
      final res  = await _dio.get('/auth/options');
      final data = res.data;
      if (data is Map && data['password_login'] is bool) {
        return data['password_login'] as bool;
      }
    } catch (_) {}
    return true;
  }

  /// 로그인 — 서버가 직접 토큰을 반환하거나 OTP를 요구하는 두 케이스 처리
  Future<LoginResult> login(String email, String password) async {
    try {
      final res = await _dio.post('/auth/login', data: {
        'email':     email,
        'password':  password,
        'device_id': await _deviceId(),
      });

      final data = res.data;
      if (data is! Map) {
        throw Exception('서버 응답 형식 오류 (${res.statusCode})');
      }

      // 서버가 문자 인증을 끈 경우 — 토큰이 바로 온다
      if (data['token'] != null) {
        await _persistSession(data);
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
        'device_id':     await _deviceId(),
      });
      await _persistSession(res.data as Map);
    } on DioException catch (e) {
      throw Exception(_extractMessage(e));
    }
  }

  /// 로그인 응답을 기기에 담는다.
  /// 문자 인증을 거친 로그인과 건너뛴 로그인이 똑같은 값을 남겨야
  /// 로그인 뒤 화면·실시간 알림이 어느 쪽으로 들어왔는지 몰라도 된다.
  Future<void> _persistSession(Map data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(AppConstants.keyAccessToken, data['token'].toString());

    final user = data['user'];
    if (user is Map) {
      final id = user['id'];
      if (id is num) await prefs.setInt(AppConstants.keyUserId, id.toInt());
      final name  = user['name']?.toString();
      final email = user['email']?.toString();
      if (name  != null) await prefs.setString(AppConstants.keyUserName,  name);
      if (email != null) await prefs.setString(AppConstants.keyUserEmail, email);
    }

    // Pusher 설정 저장
    final pusher = data['pusher'];
    if (pusher is Map) {
      final key     = pusher['key']?.toString();
      final cluster = pusher['cluster']?.toString();
      if (key     != null) await prefs.setString(AppConstants.keyPusherKey,     key);
      if (cluster != null) await prefs.setString(AppConstants.keyPusherCluster, cluster);
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
      await clearSession();
    }
  }

  /// 기기에 담아 둔 것만 비운다. 서버는 부르지 않는다 —
  /// 서버가 이미 토큰을 버린 뒤(401)라면 불러 봐야 또 거절당한다.
  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(AppConstants.keyAccessToken);
    await prefs.remove(AppConstants.keyUserId);
    await prefs.remove(AppConstants.keyUserName);
    await prefs.remove(AppConstants.keyUserEmail);
    await prefs.remove(AppConstants.keyPusherKey);
    await prefs.remove(AppConstants.keyPusherCluster);
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
