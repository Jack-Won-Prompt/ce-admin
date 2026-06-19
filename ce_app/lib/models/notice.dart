// lib/models/notice.dart

class Notice {
  final int     id;
  final String  title;
  final bool    isPinned;
  final int     views;
  final String  author;
  final String  date;
  final bool    isRead;

  const Notice({
    required this.id,
    required this.title,
    required this.isPinned,
    required this.views,
    required this.author,
    required this.date,
    required this.isRead,
  });

  factory Notice.fromJson(Map<String, dynamic> j) => Notice(
        id:       (j['id']    as num).toInt(),
        title:    j['title']  as String,
        isPinned: j['is_pinned'] as bool? ?? false,
        views:    (j['views'] as num?)?.toInt() ?? 0,
        author:   j['author']   as String? ?? '-',
        date:     j['date']     as String,
        isRead:   j['is_read']  as bool? ?? false,
      );
}

class NoticeDetail {
  final int     id;
  final String  title;
  final String  content;
  final bool    isPinned;
  final int     views;
  final String  author;
  final String  date;
  final Map<String, dynamic>? prev;
  final Map<String, dynamic>? next;

  const NoticeDetail({
    required this.id,
    required this.title,
    required this.content,
    required this.isPinned,
    required this.views,
    required this.author,
    required this.date,
    this.prev,
    this.next,
  });

  factory NoticeDetail.fromJson(Map<String, dynamic> j) => NoticeDetail(
        id:       (j['id']    as num).toInt(),
        title:    j['title']  as String,
        content:  j['content'] as String,
        isPinned: j['is_pinned'] as bool? ?? false,
        views:    (j['views'] as num?)?.toInt() ?? 0,
        author:   j['author']  as String? ?? '-',
        date:     j['date']    as String,
        prev:     j['prev']    as Map<String, dynamic>?,
        next:     j['next']    as Map<String, dynamic>?,
      );
}
