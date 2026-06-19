// lib/models/inquiry.dart

class Inquiry {
  final int    id;
  final String title;
  final String category;
  final String categoryLabel;
  final String status;
  final String createdAt;

  const Inquiry({
    required this.id,
    required this.title,
    required this.category,
    required this.categoryLabel,
    required this.status,
    required this.createdAt,
  });

  bool get isPending  => status == 'pending';
  bool get isAnswered => status == 'answered';

  factory Inquiry.fromJson(Map<String, dynamic> j) => Inquiry(
        id:            (j['id'] as num).toInt(),
        title:         j['title']          as String,
        category:      j['category']       as String,
        categoryLabel: j['category_label'] as String,
        status:        j['status']         as String,
        createdAt:     j['created_at']     as String,
      );
}

class InquiryDetail {
  final int                  id;
  final String               title;
  final String               category;
  final String               categoryLabel;
  final String               status;
  final String               createdAt;
  final List<InquiryMessage> messages;

  const InquiryDetail({
    required this.id,
    required this.title,
    required this.category,
    required this.categoryLabel,
    required this.status,
    required this.createdAt,
    required this.messages,
  });

  bool get isPending  => status == 'pending';
  bool get isAnswered => status == 'answered';

  factory InquiryDetail.fromJson(Map<String, dynamic> j) => InquiryDetail(
        id:            (j['id'] as num).toInt(),
        title:         j['title']          as String,
        category:      j['category']       as String,
        categoryLabel: j['category_label'] as String,
        status:        j['status']         as String,
        createdAt:     j['created_at']     as String,
        messages: (j['messages'] as List? ?? [])
            .map((m) => InquiryMessage.fromJson(m as Map<String, dynamic>))
            .toList(),
      );
}

class InquiryMessage {
  final int     id;
  final int     userId;
  final String  userName;
  final bool    isAdmin;
  final String? body;
  final String? attachmentPath;
  final String? attachmentName;
  final int?    attachmentSize;
  final bool    isImage;
  final String  createdAt;

  const InquiryMessage({
    required this.id,
    required this.userId,
    required this.userName,
    required this.isAdmin,
    this.body,
    this.attachmentPath,
    this.attachmentName,
    this.attachmentSize,
    required this.isImage,
    required this.createdAt,
  });

  factory InquiryMessage.fromJson(Map<String, dynamic> j) => InquiryMessage(
        id:             (j['id']             as num).toInt(),
        userId:         (j['user_id']        as num).toInt(),
        userName:       j['user_name']       as String,
        isAdmin:        j['is_admin']        as bool? ?? false,
        body:           j['body']            as String?,
        attachmentPath: j['attachment_path'] as String?,
        attachmentName: j['attachment_name'] as String?,
        attachmentSize: (j['attachment_size'] as num?)?.toInt(),
        isImage:        j['is_image']         as bool? ?? false,
        createdAt:      j['created_at']       as String,
      );
}
