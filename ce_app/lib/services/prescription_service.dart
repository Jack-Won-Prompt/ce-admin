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
    String? name,
    String? dateFrom,
    String? dateTo,
  }) async {
    final params = <String, dynamic>{'page': page};
    if (status   != null && status.isNotEmpty)   params['status']    = status;
    if (name     != null && name.isNotEmpty)     params['name']      = name;
    if (dateFrom != null && dateFrom.isNotEmpty) params['date_from'] = dateFrom;
    if (dateTo   != null && dateTo.isNotEmpty)   params['date_to']   = dateTo;

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

  /// 업로드에서 고를 수 있는 서류 유형 — 웹 업로드 화면과 같은 목록.
  ///
  /// 못 받아 오면 null. 부르는 쪽이 기본 목록으로 버틴다 — 목록 때문에 업로드를
  /// 못 하게 되면 안 된다.
  Future<List<(String, String)>?> getDocTypes() async {
    try {
      final res  = await _dio.get('/prescriptions/doc-types');
      final list = (res.data as Map)['data'] as List?;
      if (list == null || list.isEmpty) return null;
      return list
          .map((e) => ((e as Map)['code'] as String, e['label'] as String))
          .toList();
    } catch (_) {
      return null;
    }
  }

  /// 처방전 그림을 지운다.
  ///
  /// 레코드는 남고 그림만 비므로, 같은 환자로 처방전을 다시 올리면 이 건이
  /// 다시 채워진다 — 건이 둘로 갈리지 않는다.
  Future<String> deleteImage(String rxNumber) =>
      _delete('/prescriptions/$rxNumber/image');

  Future<String> deleteAttachment(String rxNumber, int id) =>
      _delete('/prescriptions/$rxNumber/attachments/$id');

  /// 지우고 나서 서버가 건넨 말을 그대로 돌려준다 — 막힌 까닭도 서버가 안다
  /// (검수를 지났는지, 남의 건인지).
  Future<String> _delete(String path) async {
    try {
      final res = await _dio.delete(path);
      final body = res.data;
      return (body is Map ? body['message'] as String? : null) ?? '지웠습니다.';
    } on DioException catch (e) {
      final body = e.response?.data;
      throw Exception((body is Map ? body['message'] as String? : null) ??
          '지우지 못했습니다. (${e.type.name})');
    }
  }
}

final prescriptionServiceProvider = Provider<PrescriptionService>(
  (ref) => PrescriptionService(ref.read(dioProvider)),
);
