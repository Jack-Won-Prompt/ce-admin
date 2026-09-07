// lib/providers/prescription_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../models/prescription.dart';
import '../services/prescription_service.dart';

class PrescriptionListState {
  final List<Prescription> items;
  final int                total;
  final bool               isLoading;
  final bool               hasMore;
  final String             statusFilter;
  final String             nameFilter;
  final String?            dateFrom;   // 'yyyy-MM-dd'
  final String?            dateTo;     // 'yyyy-MM-dd'
  final String?            error;

  const PrescriptionListState({
    this.items        = const [],
    this.total        = 0,
    this.isLoading    = false,
    this.hasMore      = false,
    this.statusFilter = '',
    this.nameFilter   = '',
    this.dateFrom,
    this.dateTo,
    this.error,
  });

  PrescriptionListState copyWith({
    List<Prescription>? items,
    int?                total,
    bool?               isLoading,
    bool?               hasMore,
    String?             statusFilter,
    String?             nameFilter,
    String?             dateFrom,
    String?             dateTo,
    bool                clearDateRange = false,
    String?             error,
  }) => PrescriptionListState(
    items:        items        ?? this.items,
    total:        total        ?? this.total,
    isLoading:    isLoading    ?? this.isLoading,
    hasMore:      hasMore      ?? this.hasMore,
    statusFilter: statusFilter ?? this.statusFilter,
    nameFilter:   nameFilter   ?? this.nameFilter,
    dateFrom:     clearDateRange ? null : (dateFrom ?? this.dateFrom),
    dateTo:       clearDateRange ? null : (dateTo   ?? this.dateTo),
    error:        error,
  );
}

class PrescriptionListNotifier extends StateNotifier<PrescriptionListState> {
  final PrescriptionService _service;

  /* 처음 열면 오늘 하루만 본다. 기간을 비워 두면 서버가 올린 것을 모두 내려보내,
     오래 쓴 담당자일수록 첫 화면이 느리고 오늘 올린 것이 뒤로 밀렸다. */
  static String get today => DateFormat('yyyy-MM-dd').format(DateTime.now());

  PrescriptionListNotifier(this._service)
      : super(PrescriptionListState(dateFrom: today, dateTo: today));

  Future<void> load({bool refresh = false}) async {
    if (state.isLoading) return;
    if (!refresh && !state.hasMore && state.items.isNotEmpty) return;

    state = state.copyWith(isLoading: true);
    try {
      final page = refresh ? 1 : (state.items.length ~/ 15) + 1;
      final r = await _service.getList(
        page:     page,
        status:   state.statusFilter.isEmpty ? null : state.statusFilter,
        name:     state.nameFilter.isEmpty   ? null : state.nameFilter,
        dateFrom: state.dateFrom,
        dateTo:   state.dateTo,
      );
      state = state.copyWith(
        items:     refresh ? r.items : [...state.items, ...r.items],
        total:     r.total,
        hasMore:   r.hasMore,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// 상태 칩 선택 — 이름/기간 필터는 그대로 유지한 채 상태만 바꾼다
  void setStatusFilter(String status) {
    state = state.copyWith(statusFilter: status);
    load(refresh: true);
  }

  /// 이름 검색어 반영 — 입력 중 매번 다시 부르지 않도록 화면에서 디바운스해서 호출한다
  void setNameFilter(String name) {
    state = state.copyWith(nameFilter: name);
    load(refresh: true);
  }

  /// 업로드 날짜 기간 선택. 둘 다 null 이면 기본값(오늘 하루)으로 되돌린다 —
  /// 지웠을 때 전체가 나오면 기본값을 둔 뜻이 없다.
  void setDateRange(String? from, String? to) {
    final reset = from == null && to == null;
    state = state.copyWith(
      dateFrom: reset ? today : from,
      dateTo:   reset ? today : to,
    );
    load(refresh: true);
  }
}

final prescriptionListProvider =
    StateNotifierProvider<PrescriptionListNotifier, PrescriptionListState>(
  (ref) => PrescriptionListNotifier(ref.read(prescriptionServiceProvider)),
);
