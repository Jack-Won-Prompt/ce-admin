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

/// Dio 인스턴스 Provider
final dioProvider = Provider<Dio>((ref) {
  // 401 이 여러 요청에서 한꺼번에 올 때 로그인 화면으로 여러 번 보내지 않는다
  var expired = false;

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

  // 인터셉터: 저장된 토큰을 모든 요청 헤더에 자동 첨부
  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) async {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(AppConstants.keyAccessToken);
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }
      handler.next(options);
    },
    /* 서버가 토큰을 더 이상 받지 않으면(401) 그 자리에서 로그인 화면으로 돌린다.
       이 앱은 한 계정에 한 기기만 허용해서, 다른 기기에서 로그인하면 이쪽 토큰이
       지워진다. 그대로 두면 앱은 스스로를 로그인 상태로 알고 화면마다 「불러오지
       못했습니다」만 띄운다 — 쓰는 사람은 무엇을 해야 할지 알 수 없다. */
    onError: (error, handler) async {
      if (error.response?.statusCode == 401 && !expired) {
        final path = error.requestOptions.path;

        /* 로그인하러 가는 길에서 온 401 은 「비밀번호가 틀렸다」는 뜻이다.
           그것까지 세션 만료로 보면 로그인 화면을 다시 로그인 화면으로 보낸다. */
        const loginPaths = ['/auth/login', '/auth/verify-otp', '/auth/resend-otp'];
        if (!loginPaths.contains(path)) {
          expired = true;
          await ref.read(authNotifierProvider.notifier).sessionExpired();
          ref.read(routerProvider).go('/login');
          expired = false;
        }
      }
      handler.next(error);
    },
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
