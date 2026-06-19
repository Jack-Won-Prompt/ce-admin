// lib/services/inquiry_service.dart

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/inquiry.dart';
import 'api_client.dart';

class InquiryService {
  final Dio _dio;
  InquiryService(this._dio);

  /// 내 문의 목록
  Future<({List<Inquiry> inquiries, bool hasMore})> getList({
    int page = 1,
    String? status,
  }) async {
    final params = <String, dynamic>{'page': page};
    if (status != null) params['status'] = status;

    final res  = await _dio.get('/inquiries', queryParameters: params);
    final body = res.data as Map<String, dynamic>;
    final data = body['data'] as List;
    final meta = body['meta'] as Map<String, dynamic>;

    return (
      inquiries: data.map((e) => Inquiry.fromJson(e as Map<String, dynamic>)).toList(),
      hasMore:   (meta['current_page'] as int) < (meta['last_page'] as int),
    );
  }

  /// 문의 상세 + 메시지 목록
  Future<InquiryDetail> getDetail(int id) async {
    final res  = await _dio.get('/inquiries/$id');
    final body = res.data as Map<String, dynamic>;
    return InquiryDetail.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// 새 문의 등록 (파일 첨부 선택)
  Future<int> create({
    required String title,
    required String category,
    required String body,
    String? attachmentPath,
    String? attachmentName,
  }) async {
    final fd = FormData.fromMap({
      'title':    title,
      'category': category,
      if (body.isNotEmpty) 'body': body,
      if (attachmentPath != null)
        'attachment': await MultipartFile.fromFile(
          attachmentPath,
          filename: attachmentName,
        ),
    });
    final res = await _dio.post('/inquiries', data: fd);
    return (res.data as Map<String, dynamic>)['inquiry_id'] as int;
  }

  /// 추가 메시지 전송 (재문의, 파일 첨부 선택)
  Future<InquiryMessage> addMessage(
    int inquiryId,
    String body, {
    String? attachmentPath,
    String? attachmentName,
  }) async {
    final fd = FormData.fromMap({
      if (body.isNotEmpty) 'body': body,
      if (attachmentPath != null)
        'attachment': await MultipartFile.fromFile(
          attachmentPath,
          filename: attachmentName,
        ),
    });
    final res = await _dio.post('/inquiries/$inquiryId/messages', data: fd);
    final respBody = res.data as Map<String, dynamic>;
    return InquiryMessage.fromJson(
        respBody['message'] as Map<String, dynamic>);
  }
}

final inquiryServiceProvider = Provider<InquiryService>(
  (ref) => InquiryService(ref.read(dioProvider)),
);
