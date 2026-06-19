// lib/providers/order_provider.dart

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/order.dart';
import '../services/api_client.dart';

class OrderListState {
  final List<Order> orders;
  final int         currentPage;
  final int         lastPage;
  final int         total;
  final bool        isLoading;
  final bool        isLoadingMore;
  final String?     error;
  final String      searchQuery;
  final String      statusFilter;

  const OrderListState({
    this.orders        = const [],
    this.currentPage   = 0,
    this.lastPage      = 1,
    this.total         = 0,
    this.isLoading     = false,
    this.isLoadingMore = false,
    this.error,
    this.searchQuery   = '',
    this.statusFilter  = '',
  });

  bool get hasMore => currentPage < lastPage;

  OrderListState copyWith({
    List<Order>? orders,
    int?         currentPage,
    int?         lastPage,
    int?         total,
    bool?        isLoading,
    bool?        isLoadingMore,
    String?      error,
    String?      searchQuery,
    String?      statusFilter,
  }) =>
      OrderListState(
        orders:        orders        ?? this.orders,
        currentPage:   currentPage   ?? this.currentPage,
        lastPage:      lastPage      ?? this.lastPage,
        total:         total         ?? this.total,
        isLoading:     isLoading     ?? this.isLoading,
        isLoadingMore: isLoadingMore ?? this.isLoadingMore,
        error:         error,
        searchQuery:   searchQuery   ?? this.searchQuery,
        statusFilter:  statusFilter  ?? this.statusFilter,
      );
}

final orderListProvider =
    StateNotifierProvider<OrderListNotifier, OrderListState>(
  (ref) => OrderListNotifier(ref.read(dioProvider)),
);

class OrderListNotifier extends StateNotifier<OrderListState> {
  final Dio _dio;
  OrderListNotifier(this._dio) : super(const OrderListState());

  Future<void> fetch({bool refresh = false}) async {
    if (state.isLoading || state.isLoadingMore) return;

    final nextPage = refresh ? 1 : state.currentPage + 1;
    if (!refresh && !state.hasMore) return;

    state = state.copyWith(
      isLoading:     refresh || state.orders.isEmpty,
      isLoadingMore: !refresh && state.orders.isNotEmpty,
    );

    try {
      final resp = await _dio.get('/orders', queryParameters: {
        'page':   nextPage,
        if (state.searchQuery.isNotEmpty) 'q':      state.searchQuery,
        if (state.statusFilter.isNotEmpty) 'status': state.statusFilter,
      });

      final body = resp.data as Map<String, dynamic>;
      final meta = body['meta'] as Map<String, dynamic>;
      final list = (body['data'] as List)
          .map((e) => Order.fromJson(e as Map<String, dynamic>))
          .toList();

      state = state.copyWith(
        orders:        refresh ? list : [...state.orders, ...list],
        currentPage:   meta['current_page'] as int,
        lastPage:      meta['last_page']    as int,
        total:         meta['total']        as int,
        isLoading:     false,
        isLoadingMore: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading:     false,
        isLoadingMore: false,
        error:         e.toString(),
      );
    }
  }

  void setSearch(String query) {
    state = state.copyWith(searchQuery: query, currentPage: 0, lastPage: 1, orders: []);
    fetch(refresh: true);
  }

  void setStatusFilter(String status) {
    state = state.copyWith(statusFilter: status, currentPage: 0, lastPage: 1, orders: []);
    fetch(refresh: true);
  }
}
