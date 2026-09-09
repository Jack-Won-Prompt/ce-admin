// lib/services/sso/sso_config.dart
// Entra ID SSO 설정 — 빌드할 때 --dart-define 으로 넣는다.

/// SSO 설정값.
///
/// 지시서 LTL-UNICORN-20260909-03 rev.2 §A-1-3: Tenant ID·Client ID·scope·
/// redirect URI 는 코드에 넣지 않고 빌드 설정으로 분리한다. 개발용과 운영용
/// App Registration 이 따로 있어(본사 2026-09-07 회신) 값이 환경마다 다르다.
///
/// 빌드 예:
///   flutter build appbundle --release \
///     --dart-define=SSO_APP_ENABLED=true \
///     --dart-define=SSO_TENANT_ID=... \
///     --dart-define=SSO_CLIENT_ID=... \
///     --dart-define=SSO_REDIRECT_URI=msauth://com.coloplast.ceadmin/xxxx%3D
class SsoConfig {
  final bool         enabled;
  final String       tenantId;
  final String       clientId;
  final String       redirectUri;
  final List<String> scopes;

  const SsoConfig({
    required this.enabled,
    required this.tenantId,
    required this.clientId,
    required this.redirectUri,
    required this.scopes,
  });

  /// 빌드에 박힌 값으로 만든다. 아무것도 넣지 않으면 꺼진 채로 나온다.
  factory SsoConfig.fromEnvironment() => SsoConfig(
        enabled:     const bool.fromEnvironment('SSO_APP_ENABLED'),
        tenantId:    const String.fromEnvironment('SSO_TENANT_ID'),
        clientId:    const String.fromEnvironment('SSO_CLIENT_ID'),
        redirectUri: const String.fromEnvironment('SSO_REDIRECT_URI'),
        scopes:      _splitScopes(const String.fromEnvironment(
          'SSO_SCOPES',
          defaultValue: 'openid profile email offline_access',
        )),
      );

  static List<String> _splitScopes(String raw) =>
      raw.split(RegExp(r'[\s,]+')).where((s) => s.isNotEmpty).toList();

  /// 값이 모자라 아직 붙일 수 없는 항목. 비어 있으면 준비가 끝난 것이다.
  List<String> get missing => [
        if (tenantId.isEmpty)    'SSO_TENANT_ID',
        if (clientId.isEmpty)    'SSO_CLIENT_ID',
        if (redirectUri.isEmpty) 'SSO_REDIRECT_URI',
      ];

  /// 켜져 있고 값도 다 있는가.
  ///
  /// 켜기만 하고 값을 빠뜨린 빌드가 나올 수 있다. 그때 SSO 로 붙으려 들면
  /// 로그인 자체가 막히므로, 값이 없으면 꺼진 것으로 본다.
  bool get isReady => enabled && missing.isEmpty;

  /// Entra 권한 서버 주소.
  String get authority => 'https://login.microsoftonline.com/$tenantId';
}
