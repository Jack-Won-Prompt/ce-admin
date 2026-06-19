// lib/models/prescription.dart

class Prescription {
  final String  rxNumber;
  final String  status;
  final String  statusLabel;
  final String? patientName;
  final String? hospital;
  final String? diseaseName;
  final String? issuedDate;
  final int?    ocrConfidence;
  final String? imageUrl;
  final String  createdAt;

  const Prescription({
    required this.rxNumber,
    required this.status,
    required this.statusLabel,
    this.patientName,
    this.hospital,
    this.diseaseName,
    this.issuedDate,
    this.ocrConfidence,
    this.imageUrl,
    required this.createdAt,
  });

  factory Prescription.fromJson(Map<String, dynamic> j) => Prescription(
        rxNumber:      j['rx_number']      as String,
        status:        j['status']         as String,
        statusLabel:   j['status_label']   as String,
        patientName:   j['patient_name']   as String?,
        hospital:      j['hospital']       as String?,
        diseaseName:   j['disease_name']   as String?,
        issuedDate:    j['issued_date']    as String?,
        ocrConfidence: (j['ocr_confidence'] as num?)?.toInt(),
        imageUrl:      j['image_url']      as String?,
        createdAt:     j['created_at']     as String,
      );
}

class PrescriptionDetail {
  final String  rxNumber;
  final String  status;
  final String  statusLabel;
  final int?    ocrConfidence;
  final String? imageUrl;
  final OcrResult ocr;

  const PrescriptionDetail({
    required this.rxNumber,
    required this.status,
    required this.statusLabel,
    this.ocrConfidence,
    this.imageUrl,
    required this.ocr,
  });

  factory PrescriptionDetail.fromJson(Map<String, dynamic> j) =>
      PrescriptionDetail(
        rxNumber:      j['prescription_id'] as String,
        status:        j['status']          as String,
        statusLabel:   j['status_label']    as String,
        ocrConfidence: (j['ocr_confidence'] as num?)?.toInt(),
        imageUrl:      j['image_url']       as String?,
        ocr: OcrResult.fromJson(
            j['ocr_result'] as Map<String, dynamic>? ?? {}),
      );
}

class OcrResult {
  final String? registrationNo;
  final String? serialNo;
  final bool    isReissue;
  final String? patientName;
  final String? residentNo;
  final String? phone;
  final String? mobile;
  final String? department;
  final String? diseaseName;
  final String? diseaseCode;
  final int?    dailyCount;
  final int?    totalDays;
  final int?    totalCount;
  final String? usagePeriod;
  final String? hospitalName;
  final String? hospitalCode;
  final String? doctorName;
  final String? specialty;
  final String? licenseNo;
  final String? specialistNo;
  final String? issuedDate;

  const OcrResult({
    this.registrationNo,
    this.serialNo,
    this.isReissue = false,
    this.patientName,
    this.residentNo,
    this.phone,
    this.mobile,
    this.department,
    this.diseaseName,
    this.diseaseCode,
    this.dailyCount,
    this.totalDays,
    this.totalCount,
    this.usagePeriod,
    this.hospitalName,
    this.hospitalCode,
    this.doctorName,
    this.specialty,
    this.licenseNo,
    this.specialistNo,
    this.issuedDate,
  });

  factory OcrResult.fromJson(Map<String, dynamic> j) => OcrResult(
        registrationNo: j['registration_no'] as String?,
        serialNo:       j['serial_no']       as String?,
        isReissue:      j['is_reissue']      as bool? ?? false,
        patientName:    j['patient_name']    as String?,
        residentNo:     j['resident_no']     as String?,
        phone:          j['phone']           as String?,
        mobile:         j['mobile']          as String?,
        department:     j['department']      as String?,
        diseaseName:    j['disease_name']    as String?,
        diseaseCode:    j['disease_code']    as String?,
        dailyCount:     (j['daily_count']  as num?)?.toInt(),
        totalDays:      (j['total_days']   as num?)?.toInt(),
        totalCount:     (j['total_count']  as num?)?.toInt(),
        usagePeriod:    j['usage_period']    as String?,
        hospitalName:   j['hospital_name']   as String?,
        hospitalCode:   j['hospital_code']   as String?,
        doctorName:     j['doctor_name']     as String?,
        specialty:      j['specialty']       as String?,
        licenseNo:      j['license_no']      as String?,
        specialistNo:   j['specialist_no']   as String?,
        issuedDate:     j['issued_date']     as String?,
      );
}
