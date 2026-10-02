// lib/utils/constants.dart
// API 기본 설정

class AppConstants {

  // ── API ──────────────────────────────────────────
  /// 앱이 붙을 서버의 기본값 — **운영**이다 (2026-10-02 지시: 모두 75.2.99.52 사용).
  ///
  /// 여태 이 자리가 `www.ceadmin.co.kr` 이었다. 그러면 `--dart-define` 을 빠뜨린
  /// 빌드가 **조용히 다른 서버를 보는 판**이 된다 — 폰에서는 멀쩡해 보이고,
  /// 자료가 없다는 것만 이상하게 비친다. 기본값을 쓰는 서버로 맞춰 둔다.
  static const String baseUrlDev = 'https://75.2.99.52/api';

  /// 앱이 붙을 서버.
  ///
  /// 빌드할 때 골라 넣을 수 있다. 넣지 않으면 위의 기본값(운영)으로 간다.
  ///
  ///   flutter build apk --release --flavor prod   ///     --dart-define=API_BASE_URL=https://75.2.99.52/api
  ///
  /// 코드에 박아 두지 않는 까닭은, 스토어에 올린 앱이 서버가 바뀌어도 옛 주소를
  /// 계속 보기 때문이다 — 그때는 새 판을 올려야만 옮겨진다.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: baseUrlDev,
  );

  /// 파일 저장소 기본 URL (baseUrl에서 /api 제거)
  static String get storageUrl =>
      baseUrl.endsWith('/api') ? baseUrl.substring(0, baseUrl.length - 4) : baseUrl;

  /// SSO 로그인을 마친 브라우저가 앱을 다시 부를 때 쓰는 주소의 앞머리.
  ///
  /// 운영판과 개발판이 한 폰에 같이 깔리므로 판마다 달라야 한다 — 같으면
  /// 안드로이드가 어느 앱을 부를지 정하지 못한다. 빌드할 때 골라 넣고,
  /// 안드로이드 쪽 값(build.gradle.kts 의 ssoScheme)과 반드시 같아야 한다.
  static const String ssoScheme = String.fromEnvironment(
    'SSO_SCHEME',
    defaultValue: 'ceadmin',
  );

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
