// lib/services/notice_service.dart

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/notice.dart';
import 'api_client.dart';

class NoticeService {
  final Dio _dio;
  NoticeService(this._dio);

  /// 공지사항 목록 + 미읽음 수
  Future<({List<Notice> notices, int unreadCount, bool hasMore, int lastPage})>
      getList({int page = 1, String? q}) async {
    final params = <String, dynamic>{'page': page};
    if (q != null && q.isNotEmpty) params['q'] = q;

    final res  = await _dio.get('/notices', queryParameters: params);
    final body = res.data as Map<String, dynamic>;
    final data = body['data'] as List;
    final meta = body['meta'] as Map<String, dynamic>;

    return (
      notices:     data.map((e) => Notice.fromJson(e as Map<String, dynamic>)).toList(),
      unreadCount: meta['unread_count'] as int? ?? 0,
      hasMore:     (meta['current_page'] as int) < (meta['last_page'] as int),
      lastPage:    meta['last_page']    as int,
    );
  }

  /// 공지사항 상세 + 읽음 처리
  Future<({NoticeDetail notice, int unreadCount})> getDetail(int id) async {
    final res  = await _dio.get('/notices/$id');
    final body = res.data as Map<String, dynamic>;
    return (
      notice:      NoticeDetail.fromJson(body['data'] as Map<String, dynamic>),
      unreadCount: body['unread_count'] as int? ?? 0,
    );
  }
}

final noticeServiceProvider = Provider<NoticeService>(
  (ref) => NoticeService(ref.read(dioProvider)),
);
