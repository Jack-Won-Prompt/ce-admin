// lib/providers/prescription_provider.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/prescription.dart';
import '../services/prescription_service.dart';

class PrescriptionListState {
  final List<Prescription> items;
  final int                total;
  final bool               isLoading;
  final bool               hasMore;
  final String             statusFilter;
  final String?            error;

  const PrescriptionListState({
    this.items        = const [],
    this.total        = 0,
    this.isLoading    = false,
    this.hasMore      = false,
    this.statusFilter = '',
    this.error,
  });

  PrescriptionListState copyWith({
    List<Prescription>? items,
    int?                total,
    bool?               isLoading,
    bool?               hasMore,
    String?             statusFilter,
    String?             error,
  }) => PrescriptionListState(
    items:        items        ?? this.items,
    total:        total        ?? this.total,
    isLoading:    isLoading    ?? this.isLoading,
    hasMore:      hasMore      ?? this.hasMore,
    statusFilter: statusFilter ?? this.statusFilter,
    error:        error,
  );
}

class PrescriptionListNotifier extends StateNotifier<PrescriptionListState> {
  final PrescriptionService _service;
  PrescriptionListNotifier(this._service)
      : super(const PrescriptionListState());

  Future<void> load({bool refresh = false}) async {
    if (state.isLoading) return;
    if (!refresh && !state.hasMore && state.items.isNotEmpty) return;

    state = state.copyWith(isLoading: true);
    try {
      final page = refresh ? 1 : (state.items.length ~/ 15) + 1;
      final r = await _service.getList(
        page:   page,
        status: state.statusFilter.isEmpty ? null : state.statusFilter,
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

  void setStatusFilter(String status) {
    state = PrescriptionListState(statusFilter: status);
    load(refresh: true);
  }
}

final prescriptionListProvider =
    StateNotifierProvider<PrescriptionListNotifier, PrescriptionListState>(
  (ref) => PrescriptionListNotifier(ref.read(prescriptionServiceProvider)),
);
