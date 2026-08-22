// lib/services/patient_service.dart
// 환자 검색/등록 — 처방자료 업로드 시 "이름 찾기"에서 사용

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/patient.dart';
import 'api_client.dart';

class PatientService {
  final Dio _dio;
  PatientService(this._dio);

  /// 이름 또는 연락처로 환자 검색 (2글자 이상)
  Future<List<PatientOption>> search(String query) async {
    try {
      final res = await _dio.get('/patients/search', queryParameters: {'q': query});
      final body = res.data as Map<String, dynamic>;
      if (body['success'] != true) {
        throw Exception(body['message']?.toString() ?? '검색에 실패했습니다.');
      }
      final list = body['patients'] as List;
      return list
          .map((e) => PatientOption.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    } on DioException catch (e) {
      final msg = (e.response?.data is Map)
          ? e.response!.data['message']?.toString()
          : null;
      throw Exception(msg ?? '네트워크 오류 (${e.type.name})');
    }
  }

  /// 검색 결과가 없을 때 새 환자 등록 — 등록 즉시 선택 가능한 값을 반환
  Future<PatientOption> create({required String name, String? residentNo}) async {
    try {
      final res = await _dio.post('/patients', data: {
        'name': name,
        if (residentNo != null && residentNo.isNotEmpty) 'resident_no': residentNo,
      });
      final body = res.data as Map<String, dynamic>;
      final p = body['patient'] as Map<String, dynamic>;
      return PatientOption(id: (p['id'] as num).toInt(), name: p['name'] as String, mobile: '-');
    } on DioException catch (e) {
      final msg = (e.response?.data is Map)
          ? e.response!.data['message']?.toString()
          : null;
      throw Exception(msg ?? '네트워크 오류 (${e.type.name})');
    }
  }
}

final patientServiceProvider = Provider<PatientService>(
  (ref) => PatientService(ref.read(dioProvider)),
);
