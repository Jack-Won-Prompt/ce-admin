// lib/services/api_auth_interceptor.dart
// 요청에 토큰을 붙이고, 401 을 받았을 때 무엇을 할지 정한다.

import 'package:dio/dio.dart';

import 'sso/sso_authenticator.dart';

/// 인증 인터셉터.
///
/// 두 가지 인증이 함께 산다. SSO 가 꺼져 있으면 지금까지처럼 기기에 담아 둔
/// Sanctum 토큰을 쓰고, 켜져 있으면 SSO 토큰을 쓴다. 지시서 rev.2 §2-5 가
/// 기존 흐름을 건드리지 말라고 했으므로, 꺼져 있을 때의 동작은 예전과 같다.
///
/// 401 을 받았을 때도 갈린다.
///   SSO 켜짐 — 조용히 다시 받아 한 번만 더 해 본다(§A-1-2). 그래도 안 되면 재로그인.
///   SSO 꺼짐 — 곧장 재로그인. Sanctum 토큰은 다시 받아 올 길이 없다.
class ApiAuthInterceptor extends Interceptor {
  final SsoAuthenticator sso;

  /// 기기에 담아 둔 Sanctum 토큰을 읽어 온다.
  final Future<String?> Function() legacyToken;

  /// 되살릴 수 없을 때 — 담아 둔 것을 비우고 로그인 화면으로 보낸다.
  final Future<void> Function() onSessionExpired;

  /// 같은 요청을 다시 보낸다. 시험에서 갈아 끼울 수 있게 밖에서 받는다.
  final Future<Response<dynamic>> Function(RequestOptions) retry;

  ApiAuthInterceptor({
    required this.sso,
    required this.legacyToken,
    required this.onSessionExpired,
    required this.retry,
  });

  /* 로그인하러 가는 길에서 온 401 은 「비밀번호가 틀렸다」는 뜻이다.
     그것까지 세션 만료로 보면 로그인 화면을 다시 로그인 화면으로 보낸다. */
  static const _loginPaths = <String>{
    '/auth/login',
    '/auth/verify-otp',
    '/auth/resend-otp',
  };

  /// 한 요청을 두 번 넘게 다시 보내지 않기 위한 표.
  static const _retriedKey = 'sso_retried';

  /// 이 요청 때문에 이미 재로그인으로 보냈다는 표.
  ///
  /// 다시 보낸 요청도 401 이면 안쪽에서 한 번, 바깥에서 또 한 번 보내게 된다.
  /// 같은 RequestOptions 를 함께 쓰므로 여기에 적어 두면 두 번 부르지 않는다.
  static const _expiredKey = 'sso_expired_handled';

  /// 401 이 여러 요청에서 한꺼번에 올 때 로그인 화면으로 여러 번 보내지 않는다.
  bool _handlingExpiry = false;

  @override
  Future<void> onRequest(
      RequestOptions options, RequestInterceptorHandler handler) async {
    /* 다시 보내는 요청에는 이미 새로 받은 토큰이 붙어 있다. 여기서 또 붙이면
       조용히 받아 온 것을 덮어써, 같은 토큰으로 두 번 두드리게 된다.
       (Dio 의 fetch 는 요청 인터셉터를 다시 탄다) */
    if (options.extra[_retriedKey] == true) {
      handler.next(options);
      return;
    }

    final token = sso.isEnabled
        ? await sso.getAccessToken()
        : await legacyToken();

    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
      DioException err, ErrorInterceptorHandler handler) async {
    final options = err.requestOptions;

    if (err.response?.statusCode != 401 || _loginPaths.contains(options.path)) {
      handler.next(err);
      return;
    }

    // SSO 라면 조용히 한 번 더 받아 본다
    if (sso.isEnabled && options.extra[_retriedKey] != true) {
      try {
        final fresh = await sso.getAccessToken(forceRefresh: true);
        if (fresh != null && fresh.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $fresh';
          options.extra[_retriedKey] = true;

          final res = await retry(options);
          handler.resolve(res);
          return;
        }
      } catch (_) {
        // 다시 받아 오지 못했다 — 아래 재로그인으로 흘러간다
      }
    }

    await _expire(options);
    handler.next(err);
  }

  /// 재로그인으로 보낸다. 같은 요청에 대해서도, 한꺼번에 몰려온 여러 요청에
  /// 대해서도 한 번만 부른다.
  Future<void> _expire(RequestOptions options) async {
    if (options.extra[_expiredKey] == true || _handlingExpiry) return;

    options.extra[_expiredKey] = true;
    _handlingExpiry = true;
    try {
      await onSessionExpired();
    } finally {
      _handlingExpiry = false;
    }
  }
}
