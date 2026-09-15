// lib/models/prescription.dart

class Prescription {
  final String  rxNumber;
  final String  status;
  final String  statusLabel;
  final String? patientName;
  final String? hospital;
  final String? diseaseName;
  final String? issuedDate;
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
        imageUrl:      j['image_url']      as String?,
        createdAt:     j['created_at']     as String,
      );
}

class PrescriptionDetail {
  final String  rxNumber;
  final String  status;
  final String  statusLabel;
  final String? imageUrl;
  final String? imageName;
  final OcrResult ocr;

  /// 이 건에 올린 첨부 서류.
  final List<PrescriptionFile> attachments;

  /// 올린 사람이 지우고 다시 올릴 수 있는 상태인가.
  /// 검수 완료 뒤에는 거짓이 되고, 남의 건도 거짓이다.
  final bool editable;

  /// 검수자가 다시 올려 달라고 한 것 중 아직 닫히지 않은 것.
  final List<ReuploadRequest> requests;

  /// 「검수 재요청」 단추를 세울지 — 되물은 자취가 있고 아직 요청하지 않은 내 건.
  final bool canRequestReview;

  const PrescriptionDetail({
    required this.rxNumber,
    required this.status,
    required this.statusLabel,
    this.imageUrl,
    this.imageName,
    required this.ocr,
    this.attachments = const [],
    this.editable = false,
    this.requests = const [],
    this.canRequestReview = false,
  });

  /// 이 서류를 물은 요청. [attachmentId] 가 null 이면 처방전 그림이다.
  ReuploadRequest? requestFor(int? attachmentId) {
    for (final r in requests) {
      if (attachmentId == null
          ? r.isPrescriptionImage
          : r.attachmentId == attachmentId) {
        return r;
      }
    }
    return null;
  }

  factory PrescriptionDetail.fromJson(Map<String, dynamic> j) =>
      PrescriptionDetail(
        rxNumber:      j['prescription_id'] as String,
        status:        j['status']          as String,
        statusLabel:   j['status_label']    as String,
        imageUrl:      j['image_url']       as String?,
        imageName:     j['image_name']      as String?,
        editable:      j['editable']        as bool? ?? false,
        canRequestReview: j['can_request_review'] as bool? ?? false,
        attachments: ((j['attachments'] as List?) ?? const [])
            .map((e) => PrescriptionFile.fromJson(
                Map<String, dynamic>.from(e as Map)))
            .toList(),
        // 옛 서버는 이 칸을 보내지 않는다 — 없으면 요청이 없는 것으로 본다
        requests: ((j['reupload_requests'] as List?) ?? const [])
            .map((e) => ReuploadRequest.fromJson(
                Map<String, dynamic>.from(e as Map)))
            .toList(),
        ocr: OcrResult.fromJson(
            j['ocr_result'] as Map<String, dynamic>? ?? {}),
      );
}

/// 이 건에 올라가 있는 서류 한 장.
class PrescriptionFile {
  final int    id;
  final String docLabel;
  final String fileName;
  final String url;
  final bool   isPdf;

  const PrescriptionFile({
    required this.id,
    required this.docLabel,
    required this.fileName,
    required this.url,
    required this.isPdf,
  });

  factory PrescriptionFile.fromJson(Map<String, dynamic> j) => PrescriptionFile(
        id:       (j['id'] as num).toInt(),
        docLabel: j['doc_label'] as String? ?? '기타',
        fileName: j['file_name'] as String? ?? '',
        url:      j['url'] as String? ?? '',
        isPdf:    j['is_pdf'] as bool? ?? false,
      );
}

/// 검수자가 다시 올려 달라고 한 것 — 서류 한 장마다 하나.
///
/// 다시 올리면 서버가 저절로 닫고, 닫힌 것은 내려오지 않는다.
class ReuploadRequest {
  final int     id;

  /// 무엇을 되물었나. null 이면 처방전 그림이다.
  final int?    attachmentId;
  final String  docLabel;

  /// 고른 사유(이미지가 잘 안 보임 · 서류 유형이 다름 · 그 밖의 사유).
  final String  reason;

  /// 검수자가 적은 비고.
  final String? memo;
  final String? requestedBy;
  final String? requestedAt;

  const ReuploadRequest({
    required this.id,
    this.attachmentId,
    required this.docLabel,
    required this.reason,
    this.memo,
    this.requestedBy,
    this.requestedAt,
  });

  bool get isPrescriptionImage => attachmentId == null;

  factory ReuploadRequest.fromJson(Map<String, dynamic> j) => ReuploadRequest(
        id:           (j['id'] as num).toInt(),
        attachmentId: (j['attachment_id'] as num?)?.toInt(),
        docLabel:     j['doc_label']    as String? ?? '처방전',
        reason:       j['reason']       as String? ?? '',
        memo:         j['memo']         as String?,
        requestedBy:  j['requested_by'] as String?,
        requestedAt:  j['requested_at'] as String?,
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
