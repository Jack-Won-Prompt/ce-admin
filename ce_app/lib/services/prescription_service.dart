// lib/services/prescription_service.dart

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/prescription.dart';
import 'api_client.dart';

class PrescriptionService {
  final Dio _dio;
  PrescriptionService(this._dio);

  /// 내 처방전 목록
  Future<({List<Prescription> items, int total, bool hasMore})> getList({
    int page = 1,
    String? status,
  }) async {
    final params = <String, dynamic>{'page': page};
    if (status != null && status.isNotEmpty) params['status'] = status;

    try {
      final res  = await _dio.get('/prescriptions', queryParameters: params);
      final body = res.data;

      if (body is! Map) {
        throw Exception('응답 형식 오류: ${res.statusCode}');
      }

      final dataRaw = body['data'];
      final metaRaw = body['meta'];

      if (dataRaw == null || metaRaw == null) {
        throw Exception('응답 필드 누락: ${body.keys.toList()}');
      }

      final meta = metaRaw as Map;
      final items = (dataRaw as List)
          .map((e) => Prescription.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();

      return (
        items:   items,
        total:   (meta['total'] as num).toInt(),
        hasMore: (meta['current_page'] as num).toInt() <
                 (meta['last_page']    as num).toInt(),
      );
    } on DioException catch (e) {
      final msg = (e.response?.data is Map)
          ? e.response!.data['message']?.toString()
          : null;
      throw Exception(msg ?? '네트워크 오류 (${e.type.name})');
    }
  }

  /// 처방전 상세
  Future<PrescriptionDetail> getDetail(String rxNumber) async {
    final res  = await _dio.get('/prescriptions/$rxNumber');
    final body = res.data as Map<String, dynamic>;
    return PrescriptionDetail.fromJson(body['data'] as Map<String, dynamic>);
  }
}

final prescriptionServiceProvider = Provider<PrescriptionService>(
  (ref) => PrescriptionService(ref.read(dioProvider)),
);
