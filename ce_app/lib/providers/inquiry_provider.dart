// lib/providers/inquiry_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/inquiry.dart';
import '../services/inquiry_service.dart';

// ── 목록 ────────────────────────────────────────────────────

class InquiryListState {
  final List<Inquiry> inquiries;
  final bool          isLoading;
  final bool          hasMore;
  final String?       error;

  const InquiryListState({
    this.inquiries = const [],
    this.isLoading = false,
    this.hasMore   = false,
    this.error,
  });

  InquiryListState copyWith({
    List<Inquiry>? inquiries,
    bool?          isLoading,
    bool?          hasMore,
    String?        error,
  }) => InquiryListState(
    inquiries: inquiries ?? this.inquiries,
    isLoading: isLoading ?? this.isLoading,
    hasMore:   hasMore   ?? this.hasMore,
    error:     error,
  );
}

class InquiryListNotifier extends StateNotifier<InquiryListState> {
  final InquiryService _service;
  InquiryListNotifier(this._service) : super(const InquiryListState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true);
    try {
      final r = await _service.getList(page: 1);
      state = InquiryListState(
        inquiries: r.inquiries,
        hasMore:   r.hasMore,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoading: true);
    try {
      final nextPage = (state.inquiries.length ~/ 20) + 1;
      final r = await _service.getList(page: nextPage);
      state = state.copyWith(
        inquiries: [...state.inquiries, ...r.inquiries],
        hasMore:   r.hasMore,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<int> create({
    required String title,
    required String category,
    required String body,
    String? attachmentPath,
    String? attachmentName,
  }) async {
    final id = await _service.create(
      title:          title,
      category:       category,
      body:           body,
      attachmentPath: attachmentPath,
      attachmentName: attachmentName,
    );
    await load();
    return id;
  }
}

final inquiryListProvider =
    StateNotifierProvider<InquiryListNotifier, InquiryListState>((ref) {
  return InquiryListNotifier(ref.read(inquiryServiceProvider));
});

// ── 상세 ────────────────────────────────────────────────────

class InquiryDetailState {
  final InquiryDetail? detail;
  final bool           isLoading;
  final String?        error;

  const InquiryDetailState({
    this.detail,
    this.isLoading = false,
    this.error,
  });

  InquiryDetailState copyWith({
    InquiryDetail? detail,
    bool?          isLoading,
    String?        error,
  }) => InquiryDetailState(
    detail:    detail    ?? this.detail,
    isLoading: isLoading ?? this.isLoading,
    error:     error,
  );
}

class InquiryDetailNotifier extends StateNotifier<InquiryDetailState> {
  final InquiryService _service;
  final int            _inquiryId;

  InquiryDetailNotifier(this._service, this._inquiryId)
      : super(const InquiryDetailState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true);
    try {
      final detail = await _service.getDetail(_inquiryId);
      state = InquiryDetailState(detail: detail);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> sendMessage(
    String body, {
    String? attachmentPath,
    String? attachmentName,
  }) async {
    final msg = await _service.addMessage(
      _inquiryId,
      body,
      attachmentPath: attachmentPath,
      attachmentName: attachmentName,
    );
    if (state.detail == null) return;

    final updated = InquiryDetail(
      id:            state.detail!.id,
      title:         state.detail!.title,
      category:      state.detail!.category,
      categoryLabel: state.detail!.categoryLabel,
      status:        'pending',
      createdAt:     state.detail!.createdAt,
      messages:      [...state.detail!.messages, msg],
    );
    state = state.copyWith(detail: updated);
  }
}

final inquiryDetailProvider = StateNotifierProvider.family<
    InquiryDetailNotifier, InquiryDetailState, int>((ref, id) {
  return InquiryDetailNotifier(ref.read(inquiryServiceProvider), id);
});
