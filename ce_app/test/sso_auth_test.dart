// SSO 설정 판정과 401 재시도 — 지시서 rev.2 §6.
//
// 라이브러리가 정해지기 전이라 실제 로그인은 확인할 수 없다. 대신 부르는 쪽의
// 규칙을 굳혀 둔다: 꺼져 있을 때 기존 흐름이 그대로인가, 켜져 있을 때 401 을
// 한 번만 다시 시도하는가.

import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:ce_app/services/api_auth_interceptor.dart';
import 'package:ce_app/services/sso/sso_authenticator.dart';
import 'package:ce_app/services/sso/sso_config.dart';

/// 정해진 상태 코드를 차례로 돌려주는 가짜 통신 계층.
class _FakeAdapter implements HttpClientAdapter {
  final List<int> statuses;
  final List<RequestOptions> seen = [];

  _FakeAdapter(this.statuses);

  @override
  Future<ResponseBody> fetch(RequestOptions options,
      Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    seen.add(options);
    final status =
        seen.length <= statuses.length ? statuses[seen.length - 1] : 200;

    return ResponseBody.fromString(
      '{"ok":true}',
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// 붙은 셈 치는 구현체.
class _FakeSso implements SsoAuthenticator {
  @override
  final SsoConfig config;

  final bool enabled;
  String? token;
  String? refreshedToken;
  int refreshCalls = 0;

  _FakeSso({
    required this.enabled,
    this.token,
    this.refreshedToken,
    SsoConfig? config,
  }) : config = config ??
            const SsoConfig(
              enabled: true,
              tenantId: 't',
              clientId: 'c',
              redirectUri: 'r',
              scopes: ['openid'],
            );

  @override
  bool get isEnabled => enabled;

  @override
  Future<String?> getAccessToken({bool forceRefresh = false}) async {
    if (forceRefresh) {
      refreshCalls++;
      return refreshedToken;
    }
    return token;
  }

  @override
  Future<SsoSession> signIn() async => throw const SsoNotAvailable();

  @override
  Future<SsoSession?> signInSilently() async => null;

  @override
  Future<void> signOut() async {}
}

/// 인터셉터를 단 Dio 하나를 세운다.
({Dio dio, _FakeAdapter adapter, List<String> expired}) _build({
  required _FakeSso sso,
  required List<int> statuses,
  String? legacy,
}) {
  final adapter = _FakeAdapter(statuses);
  final expired = <String>[];

  final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api'))
    ..httpClientAdapter = adapter;

  dio.interceptors.add(ApiAuthInterceptor(
    sso: sso,
    legacyToken: () async => legacy,
    onSessionExpired: () async => expired.add('called'),
    retry: (options) => dio.fetch(options),
  ));

  return (dio: dio, adapter: adapter, expired: expired);
}

void main() {
  group('SsoConfig', () {
    test('아무것도 넣지 않으면 꺼진 채로 나온다', () {
      const c = SsoConfig(
          enabled: false, tenantId: '', clientId: '', redirectUri: '', scopes: []);
      expect(c.isReady, isFalse);
    });

    test('켜기만 하고 값을 빠뜨리면 쓰지 않는다 — 무엇이 없는지 알려 준다', () {
      const c = SsoConfig(
          enabled: true, tenantId: '', clientId: 'c', redirectUri: '', scopes: []);
      expect(c.isReady, isFalse);
      expect(c.missing, ['SSO_TENANT_ID', 'SSO_REDIRECT_URI']);
    });

    test('값이 다 있으면 준비된 것으로 본다', () {
      const c = SsoConfig(
          enabled: true,
          tenantId: 'tid',
          clientId: 'cid',
          redirectUri: 'msauth://pkg/hash',
          scopes: ['openid']);
      expect(c.isReady, isTrue);
      expect(c.missing, isEmpty);
      expect(c.authority, 'https://login.microsoftonline.com/tid');
    });

    test('빌드에 아무 값도 없으면 꺼진 설정이 나온다', () {
      expect(SsoConfig.fromEnvironment().isReady, isFalse);
    });
  });

  group('붙지 않은 구현체', () {
    test('켜졌다고 하지 않고, 조용히 받아 오는 길은 비어 온다', () async {
      const c = SsoConfig(
          enabled: true, tenantId: 't', clientId: 'c', redirectUri: 'r', scopes: []);
      const a = UnavailableSsoAuthenticator(c);

      expect(a.isEnabled, isFalse);
      expect(await a.signInSilently(), isNull);
      expect(await a.getAccessToken(), isNull);
      expect(() => a.signIn(), throwsA(isA<SsoNotAvailable>()));
    });
  });

  group('토큰 첨부', () {
    test('SSO 가 꺼져 있으면 기기에 담아 둔 토큰을 쓴다', () async {
      final sso = _FakeSso(enabled: false, token: 'sso-token');
      final t = _build(sso: sso, statuses: [200], legacy: 'legacy-token');

      await t.dio.get('/prescriptions');

      expect(t.adapter.seen.single.headers['Authorization'],
          'Bearer legacy-token');
    });

    test('SSO 가 켜져 있으면 SSO 토큰을 쓴다', () async {
      final sso = _FakeSso(enabled: true, token: 'sso-token');
      final t = _build(sso: sso, statuses: [200], legacy: 'legacy-token');

      await t.dio.get('/prescriptions');

      expect(t.adapter.seen.single.headers['Authorization'], 'Bearer sso-token');
    });
  });

  group('401 을 받았을 때', () {
    test('SSO 가 꺼져 있으면 다시 시도하지 않고 재로그인으로 보낸다', () async {
      final sso = _FakeSso(enabled: false);
      final t = _build(sso: sso, statuses: [401], legacy: 'legacy-token');

      await expectLater(t.dio.get('/prescriptions'), throwsA(isA<DioException>()));

      expect(t.adapter.seen, hasLength(1));
      expect(t.expired, hasLength(1));
    });

    test('SSO 가 켜져 있으면 다시 받아 한 번 더 해 본다', () async {
      final sso = _FakeSso(
          enabled: true, token: 'old-token', refreshedToken: 'new-token');
      final t = _build(sso: sso, statuses: [401, 200]);

      final res = await t.dio.get('/prescriptions');

      expect(res.statusCode, 200);
      expect(sso.refreshCalls, 1);
      expect(t.adapter.seen, hasLength(2));
      expect(t.adapter.seen.last.headers['Authorization'], 'Bearer new-token');
      expect(t.expired, isEmpty, reason: '되살렸으므로 재로그인으로 보내지 않는다');
    });

    test('다시 받아도 401 이면 그때 재로그인으로 보낸다 — 되풀이하지 않는다', () async {
      final sso = _FakeSso(
          enabled: true, token: 'old-token', refreshedToken: 'new-token');
      final t = _build(sso: sso, statuses: [401, 401]);

      await expectLater(t.dio.get('/prescriptions'), throwsA(isA<DioException>()));

      expect(t.adapter.seen, hasLength(2), reason: '재시도는 한 번뿐이다');
      expect(t.expired, hasLength(1));
    });

    test('토큰을 다시 받아 오지 못하면 곧장 재로그인으로 보낸다', () async {
      final sso = _FakeSso(enabled: true, token: 'old-token');
      final t = _build(sso: sso, statuses: [401]);

      await expectLater(t.dio.get('/prescriptions'), throwsA(isA<DioException>()));

      expect(t.adapter.seen, hasLength(1));
      expect(t.expired, hasLength(1));
    });

    test('로그인하러 가는 길의 401 은 비밀번호가 틀린 것이다', () async {
      final sso = _FakeSso(enabled: true, refreshedToken: 'new-token');
      final t = _build(sso: sso, statuses: [401]);

      await expectLater(
          t.dio.post('/auth/login'), throwsA(isA<DioException>()));

      expect(t.expired, isEmpty);
      expect(sso.refreshCalls, 0);
    });
  });
}
