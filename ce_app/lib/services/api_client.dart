// lib/services/api_client.dart
// Dio HTTP 클라이언트 — CE Admin Laravel API 연동

import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../providers/auth_provider.dart';
import '../router/app_router.dart';
import '../utils/constants.dart';
import 'api_auth_interceptor.dart';
import 'sso/sso_authenticator.dart';
import 'sso/sso_config.dart';

/// SSO 로그인 창구.
///
/// 라이브러리(msal_auth · flutter_appauth)가 정해지면 여기만 갈아 끼운다 —
/// 부르는 쪽은 SsoAuthenticator 모양만 안다. 지금은 붙지 않은 구현체라
/// isEnabled 가 늘 거짓이고, 앱은 예전처럼 이메일·비밀번호로 로그인한다.
final ssoAuthenticatorProvider = Provider<SsoAuthenticator>(
  (ref) => UnavailableSsoAuthenticator(SsoConfig.fromEnvironment()),
);

/// Dio 인스턴스 Provider
final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(BaseOptions(
    baseUrl:        AppConstants.baseUrl,
    connectTimeout: AppConstants.connectTimeout,
    receiveTimeout: AppConstants.receiveTimeout,
    headers: {
      'Accept':       'application/json',
      'Content-Type': 'application/json',
    },
  ));

  // SSL 인증서 검증 우회 (사내 전용 앱)
  (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
    final client = HttpClient();
    client.badCertificateCallback = (cert, host, port) => true;
    return client;
  };

  // 요청/응답 로그 (디버그 확인용)
  dio.interceptors.add(LogInterceptor(
    requestBody:  true,
    responseBody: true,
    logPrint: (o) => debugPrint('[DIO] $o'),
  ));

  /* 토큰을 붙이고 401 을 다루는 일은 ApiAuthInterceptor 가 맡는다.
     여기서 바로 쓰지 않고 따로 둔 것은 시험 때문이다 — Riverpod 도 기기 저장소도
     없이 401 재시도만 떼어 확인할 수 있어야 한다. */
  dio.interceptors.add(ApiAuthInterceptor(
    sso: ref.read(ssoAuthenticatorProvider),

    legacyToken: () async {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(AppConstants.keyAccessToken);
    },

    /* 서버가 토큰을 더 이상 받지 않으면 그 자리에서 로그인 화면으로 돌린다.
       이 앱은 한 계정에 한 기기만 허용해서, 다른 기기에서 로그인하면 이쪽 토큰이
       지워진다. 그대로 두면 앱은 스스로를 로그인 상태로 알고 화면마다 「불러오지
       못했습니다」만 띄운다 — 쓰는 사람은 무엇을 해야 할지 알 수 없다. */
    onSessionExpired: () async {
      await ref.read(authNotifierProvider.notifier).sessionExpired();
      ref.read(routerProvider).go('/login');
    },

    retry: (options) => dio.fetch(options),
  ));

  return dio;
});

/// 공통 API 응답 파싱
class ApiResponse<T> {
  final bool    success;
  final String? message;
  final T?      data;

  const ApiResponse({
    required this.success,
    this.message,
    this.data,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic) fromData,
  ) {
    return ApiResponse(
      success: json['success'] as bool? ?? false,
      message: json['message'] as String?,
      data:    json['data'] != null ? fromData(json['data']) : null,
    );
  }
}
