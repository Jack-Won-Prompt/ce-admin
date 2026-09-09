// lib/services/sso/sso_authenticator.dart
// SSO 로그인 창구 — 어떤 라이브러리를 쓰든 이 모양으로 부른다.

import 'sso_config.dart';

/// SSO 로그인으로 받아 온 것.
class SsoSession {
  final String    accessToken;
  final DateTime? expiresAt;

  /// 로그인한 사람. 서버는 이 값(UPN)으로 사용자를 가린다.
  final String?   email;

  const SsoSession({
    required this.accessToken,
    this.expiresAt,
    this.email,
  });

  bool get isExpired =>
      expiresAt != null && !expiresAt!.isAfter(DateTime.now());
}

class SsoException implements Exception {
  final String message;
  const SsoException(this.message);

  @override
  String toString() => message;
}

/// 아직 붙일 수 없다 — 꺼져 있거나 설정값이 모자란다.
class SsoNotAvailable extends SsoException {
  const SsoNotAvailable([super.message = 'SSO 로그인이 준비되지 않았습니다.']);
}

/// 조용히 받아 올 수 없다 — 사람이 한 번 로그인해야 한다.
class SsoInteractiveRequired extends SsoException {
  const SsoInteractiveRequired([super.message = '다시 로그인해 주세요.']);
}

/// SSO 로그인 창구.
///
/// 라이브러리(msal_auth · flutter_appauth)는 아직 정해지지 않았다 —
/// 본사의 Conditional Access 정책 답변에 따라 갈린다(지시서 rev.2 §3).
/// 그래서 부르는 쪽은 이 모양만 알고, 구현체는 나중에 갈아 끼운다.
///
/// 이름을 AuthService 로 하지 않은 것은 이미 같은 이름이 있기 때문이다
/// (lib/services/auth_service.dart — 이메일·비밀번호 로그인). 지시서가 든
/// 예시 이름이지만, 겹치면 기존 흐름을 건드릴 위험이 있다.
abstract class SsoAuthenticator {
  SsoConfig get config;

  /// 지금 이 빌드에서 SSO 를 쓸 수 있는가.
  bool get isEnabled => config.isReady;

  /// 사람을 세우지 않고 받아 온다. 받아 올 수 없으면 null.
  Future<SsoSession?> signInSilently();

  /// 시스템 브라우저를 열어 로그인한다.
  Future<SsoSession> signIn();

  /// 지금 쓸 토큰. 없으면 null.
  ///
  /// [forceRefresh] 가 참이면 담아 둔 것을 쓰지 않고 다시 받아 온다 —
  /// 401 을 받은 뒤 한 번 더 해 보는 자리에서 쓴다.
  Future<String?> getAccessToken({bool forceRefresh = false});

  Future<void> signOut();
}

/// 아직 아무것도 붙이지 않은 상태의 구현체.
///
/// 라이브러리가 정해지기 전까지 이것이 쓰인다. 켜져 있지 않다고 답하고,
/// 조용히 받아 오는 길은 null 을 돌려준다 — 부르는 쪽이 기존 로그인으로
/// 그대로 흘러가야 하기 때문이다.
class UnavailableSsoAuthenticator implements SsoAuthenticator {
  @override
  final SsoConfig config;

  const UnavailableSsoAuthenticator(this.config);

  @override
  bool get isEnabled => false;

  @override
  Future<SsoSession?> signInSilently() async => null;

  @override
  Future<SsoSession> signIn() async =>
      throw const SsoNotAvailable('SSO 로그인이 아직 준비되지 않았습니다.');

  @override
  Future<String?> getAccessToken({bool forceRefresh = false}) async => null;

  @override
  Future<void> signOut() async {}
}
