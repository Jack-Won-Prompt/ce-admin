// lib/models/patient.dart
// 처방자료 업로드 시 검색·선택하는 환자

class PatientOption {
  final int    id;
  final String name;
  final String mobile;

  const PatientOption({
    required this.id,
    required this.name,
    required this.mobile,
  });

  factory PatientOption.fromJson(Map<String, dynamic> j) => PatientOption(
        id:     (j['id'] as num).toInt(),
        name:   j['name']   as String,
        mobile: j['mobile'] as String? ?? '-',
      );
}
