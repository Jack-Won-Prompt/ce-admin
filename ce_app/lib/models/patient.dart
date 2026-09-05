// lib/models/patient.dart
// 처방자료 업로드 시 검색·선택하는 환자

class PatientOption {
  final int    id;
  final String name;
  final String mobile;

  /// 생년월일(YYYY-MM-DD). 동명이인을 가리는 자리다.
  /// 서버에 생년월일도 주민번호도 없으면 비어 온다.
  final String? birthDate;

  const PatientOption({
    required this.id,
    required this.name,
    required this.mobile,
    this.birthDate,
  });

  factory PatientOption.fromJson(Map<String, dynamic> j) => PatientOption(
        id:        (j['id'] as num).toInt(),
        name:      j['name']   as String,
        mobile:    j['mobile'] as String? ?? '-',
        birthDate: j['birth_date'] as String?,
      );
}
