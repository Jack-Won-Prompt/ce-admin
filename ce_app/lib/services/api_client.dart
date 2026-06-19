// lib/services/api_client.dart
// Dio HTTP 클라이언트 — CE Admin Laravel API 연동

import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../utils/constants.dart';

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
    onError: (error, handler) {
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
