// lib/utils/constants.dart
// API 기본 설정

class AppConstants {
  // ── API ──────────────────────────────────────────
  /// 운영: 공인 도메인 서버
  static const String baseUrlProd = 'https://www.ceadmin.co.kr/api';

  static const String baseUrl = baseUrlProd;  // ← 환경에 따라 변경

  /// 파일 저장소 기본 URL (baseUrl에서 /api 제거)
  static String get storageUrl =>
      baseUrl.endsWith('/api') ? baseUrl.substring(0, baseUrl.length - 4) : baseUrl;

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 30);

  // ── Pusher 기본값 (서버에서 못 받아올 경우 폴백) ──────────────
  static const String pusherKeyFallback     = 'a4e358e40addbc2ba946';
  static const String pusherClusterFallback = 'ap3';

  // ── 저장소 키 ─────────────────────────────────────
  static const String keyAccessToken   = 'access_token';
  static const String keyUserInfo      = 'user_info';
  static const String keyUserId        = 'user_id';
  static const String keyUserName      = 'user_name';
  static const String keyUserEmail     = 'user_email';
  static const String keyPusherKey     = 'pusher_key';
  static const String keyPusherCluster = 'pusher_cluster';

  // ── 기타 ─────────────────────────────────────────
  static const String appName = 'CE Admin';
}
