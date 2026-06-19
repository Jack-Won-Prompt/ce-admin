// lib/providers/notice_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/notice.dart';
import '../services/notice_service.dart';

class NoticeListState {
  final List<Notice> notices;
  final int          unreadCount;
  final bool         isLoading;
  final bool         hasMore;
  final String?      error;

  const NoticeListState({
    this.notices     = const [],
    this.unreadCount = 0,
    this.isLoading   = false,
    this.hasMore     = false,
    this.error,
  });

  NoticeListState copyWith({
    List<Notice>? notices,
    int?          unreadCount,
    bool?         isLoading,
    bool?         hasMore,
    String?       error,
  }) => NoticeListState(
    notices:     notices     ?? this.notices,
    unreadCount: unreadCount ?? this.unreadCount,
    isLoading:   isLoading   ?? this.isLoading,
    hasMore:     hasMore     ?? this.hasMore,
    error:       error,
  );
}

class NoticeListNotifier extends StateNotifier<NoticeListState> {
  final NoticeService _service;
  NoticeListNotifier(this._service) : super(const NoticeListState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true);
    try {
      final r = await _service.getList(page: 1);
      state = NoticeListState(
        notices:     r.notices,
        unreadCount: r.unreadCount,
        hasMore:     r.hasMore,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoading: true);
    try {
      final nextPage = (state.notices.length ~/ 20) + 1;
      final r = await _service.getList(page: nextPage);
      state = state.copyWith(
        notices:  [...state.notices, ...r.notices],
        hasMore:  r.hasMore,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  /// 상세 읽음 후 미읽음 카운트 갱신
  void decrementUnread() {
    if (state.unreadCount > 0) {
      state = state.copyWith(unreadCount: state.unreadCount - 1);
    }
  }
}

final noticeListProvider =
    StateNotifierProvider<NoticeListNotifier, NoticeListState>((ref) {
  return NoticeListNotifier(ref.read(noticeServiceProvider));
});
