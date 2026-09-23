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

  /// 이름ㆍ생년월일로 찾았을 때만 채워진다 (2026-09-23).
  /// 목록(내가 올린 것)에서는 비어 있고, 찾기 결과에서는 누구 건인지 알려 준다.
  final String? birthDate;
  final String? ownerName;
  final bool    isMine;
  final int?    fileCount;

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
    this.birthDate,
    this.ownerName,
    this.isMine = true,
    this.fileCount,
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
        birthDate:     j['birth_date']     as String?,
        ownerName:     j['owner_name']     as String?,
        // 목록에는 내 것만 오므로, 알려 주지 않으면 내 것으로 본다
        isMine:        j['is_mine']        as bool? ?? true,
        fileCount:     (j['file_count'] as num?)?.toInt(),
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

  /// 처방전 그림을 지우고 다시 올릴 수 있는가 — 이 건을 올린 사람만 참이다.
  final bool editable;

  /// 서류를 보탤 수 있는가 (2026-09-23). 검수를 마치기 전이면 남의 건에도 참이다.
  final bool canAdd;

  /// 내가 올린 건인가. 아니면 누가 올렸는지 [ownerName] 에 적힌다.
  final bool    isMine;
  final String? ownerName;

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
    this.canAdd = false,
    this.isMine = true,
    this.ownerName,
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
        /* 옛 서버는 can_add 를 보내지 않는다 — 그때는 editable 이 보태기 권한도
           겸했으므로 그 값으로 본다. */
        canAdd:        j['can_add']  as bool? ?? (j['editable'] as bool? ?? false),
        isMine:        j['is_mine']  as bool? ?? true,
        ownerName:     j['owner_name'] as String?,
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

  /// 누가 올렸는지 (2026-09-23). 한 건에 여러 사람이 붙을 수 있게 되며 생겼다.
  final String? uploader;

  /// 내가 지울 수 있는가 — 내가 올린 서류만 참이다.
  final bool canDelete;

  const PrescriptionFile({
    required this.id,
    required this.docLabel,
    required this.fileName,
    required this.url,
    required this.isPdf,
    this.uploader,
    this.canDelete = true,
  });

  factory PrescriptionFile.fromJson(Map<String, dynamic> j) => PrescriptionFile(
        id:       (j['id'] as num).toInt(),
        docLabel: j['doc_label'] as String? ?? '기타',
        fileName: j['file_name'] as String? ?? '',
        url:      j['url'] as String? ?? '',
        isPdf:    j['is_pdf'] as bool? ?? false,
        uploader: j['uploader'] as String?,
        // 옛 서버는 이 칸을 보내지 않는다 — 그때는 내 건의 서류만 내려왔다
        canDelete: j['can_delete'] as bool? ?? true,
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
