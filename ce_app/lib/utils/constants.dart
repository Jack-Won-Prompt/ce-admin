// lib/utils/constants.dart
// API 기본 설정

class AppConstants {
  // ── API ──────────────────────────────────────────
  /// 개발·검증 서버. 운영 도메인은 아직 정해지지 않았다.
  static const String baseUrlDev = 'https://www.ceadmin.co.kr/api';

  /// 앱이 붙을 서버.
  ///
  /// 빌드할 때 골라 넣는다. 아무것도 넣지 않으면 개발 서버로 간다 —
  /// 운영 도메인이 정해지기 전까지 그것이 유일한 서버이기 때문이다.
  ///
  ///   flutter build appbundle --release   ///     --dart-define=API_BASE_URL=https://{운영도메인}/api
  ///
  /// 예전에는 이 값이 코드에 박혀 있었다. 그러면 스토어에 올린 앱은 서버가
  /// 바뀌어도 옛 주소를 계속 본다 — 새 판을 올려야만 옮겨진다.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: baseUrlDev,
  );

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

  /// 이 기기를 가리는 값. 앱을 처음 켤 때 한 번 만들어 담아 둔다.
  /// 기기마다 토큰을 따로 두기 위한 것이라, 하드웨어 식별자를 읽지 않는다 —
  /// 그런 값은 지울 수도 바꿀 수도 없어 개인정보로 다뤄야 한다.
  static const String keyDeviceId      = 'device_id';

  // ── 기타 ─────────────────────────────────────────
  static const String appName = 'CE Admin';
}
