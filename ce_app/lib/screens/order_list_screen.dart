// lib/screens/order_list_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../models/order.dart';
import '../providers/order_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class OrderListScreen extends ConsumerStatefulWidget {
  const OrderListScreen({super.key});

  @override
  ConsumerState<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends ConsumerState<OrderListScreen> {
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  String _statusFilter = '';

  static const _statusOptions = [
    ('',          '전체'),
    ('pending',   '주문 대기'),
    ('confirmed', '주문 확정'),
    ('shipping',  '배송 중'),
    ('delivered', '배송 완료'),
    ('cancelled', '취소'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(orderListProvider.notifier).fetch(refresh: true);
    });
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >=
          _scrollCtrl.position.maxScrollExtent - 200) {
        ref.read(orderListProvider.notifier).fetch();
      }
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderListProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(orderListProvider.notifier).fetch(refresh: true),
        color: AppTheme.primary,
        child: CustomScrollView(
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // ── Header ───────────────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                decoration:
                    const BoxDecoration(gradient: AppTheme.darkGradient),
                child: SafeArea(
                  bottom: false,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
                        child: Row(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                gradient: AppTheme.successGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(Icons.shopping_bag_outlined,
                                  color: Colors.white, size: 22),
                            ),
                            const SizedBox(width: 14),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('주문 목록',
                                    style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 22,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: -0.3)),
                                if (state.orders.isNotEmpty)
                                  Text('${state.orders.length}건',
                                      style: TextStyle(
                                          color:
                                              Colors.white.withOpacity(0.6),
                                          fontSize: 12)),
                              ],
                            ),
                            const Spacer(),
                            const UserNameBadge(),
                          ],
                        ),
                      ),

                      // Search + filter chips
                      Container(
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.vertical(
                              top: Radius.circular(24)),
                        ),
                        child: Column(
                          children: [
                            Padding(
                              padding:
                                  const EdgeInsets.fromLTRB(16, 14, 16, 0),
                              child: TextField(
                                controller: _searchCtrl,
                                style: const TextStyle(
                                    color: AppTheme.textPrimary,
                                    fontSize: 14),
                                decoration: InputDecoration(
                                  hintText: '주문번호 / 환자명 검색',
                                  hintStyle: const TextStyle(
                                      color: AppTheme.textMuted,
                                      fontSize: 14),
                                  prefixIcon: const Icon(Icons.search,
                                      size: 20, color: AppTheme.textMuted),
                                  suffixIcon: _searchCtrl.text.isNotEmpty
                                      ? IconButton(
                                          icon: const Icon(Icons.clear,
                                              size: 18,
                                              color: AppTheme.textMuted),
                                          onPressed: () {
                                            _searchCtrl.clear();
                                            ref
                                                .read(orderListProvider
                                                    .notifier)
                                                .setSearch('');
                                          },
                                        )
                                      : null,
                                  filled: true,
                                  fillColor: AppTheme.background,
                                  contentPadding:
                                      const EdgeInsets.symmetric(
                                          vertical: 10),
                                  border: OutlineInputBorder(
                                    borderRadius:
                                        BorderRadius.circular(12),
                                    borderSide: BorderSide.none,
                                  ),
                                ),
                                onSubmitted: (v) => ref
                                    .read(orderListProvider.notifier)
                                    .setSearch(v),
                              ),
                            ),
                            SingleChildScrollView(
                              scrollDirection: Axis.horizontal,
                              padding:
                                  const EdgeInsets.fromLTRB(16, 12, 16, 12),
                              child: Row(
                                children: _statusOptions.map((opt) {
                                  final selected =
                                      _statusFilter == opt.$1;
                                  return Padding(
                                    padding:
                                        const EdgeInsets.only(right: 8),
                                    child: GestureDetector(
                                      onTap: () {
                                        setState(() =>
                                            _statusFilter = opt.$1);
                                        ref
                                            .read(orderListProvider
                                                .notifier)
                                            .setStatusFilter(opt.$1);
                                      },
                                      child: AnimatedContainer(
                                        duration: const Duration(
                                            milliseconds: 180),
                                        padding:
                                            const EdgeInsets.symmetric(
                                                horizontal: 14,
                                                vertical: 7),
                                        decoration: BoxDecoration(
                                          gradient: selected
                                              ? AppTheme.successGradient
                                              : null,
                                          color: selected
                                              ? null
                                              : Colors.grey.shade100,
                                          borderRadius:
                                              BorderRadius.circular(20),
                                        ),
                                        child: Text(
                                          opt.$2,
                                          style: TextStyle(
                                            fontSize: 12,
                                            fontWeight: selected
                                                ? FontWeight.w700
                                                : FontWeight.w500,
                                            color: selected
                                                ? Colors.white
                                                : AppTheme.textSecondary,
                                          ),
                                        ),
                                      ),
                                    ),
                                  );
                                }).toList(),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // ── Body ─────────────────────────────────────────────
            if (state.isLoading && state.orders.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.error != null && state.orders.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline_rounded,
                          size: 48, color: AppTheme.danger),
                      const SizedBox(height: 12),
                      const Text('데이터를 불러오지 못했습니다.',
                          style: TextStyle(color: AppTheme.textMuted)),
                      const SizedBox(height: 12),
                      GestureDetector(
                        onTap: () => ref
                            .read(orderListProvider.notifier)
                            .fetch(refresh: true),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 10),
                          decoration: BoxDecoration(
                            gradient: AppTheme.primaryGradient,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text('다시 시도',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else if (state.orders.isEmpty)
              const SliverFillRemaining(
                child: EmptyWidget(
                    message: '주문이 없습니다.',
                    icon: Icons.inbox_outlined),
              )
            else
              SliverPadding(
                padding:
                    const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == state.orders.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(
                              child: CircularProgressIndicator(
                                  color: AppTheme.primary)),
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _OrderCard(order: state.orders[i]),
                      );
                    },
                    childCount:
                        state.orders.length + (state.hasMore ? 1 : 0),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final Order order;
  const _OrderCard({required this.order});

  static const _statusColors = {
    'pending':   Color(0xFF9E9E9E),
    'confirmed': AppTheme.primary,
    'shipping':  AppTheme.secondary,
    'delivered': AppTheme.success,
    'cancelled': AppTheme.danger,
  };

  static const _statusIcons = {
    'pending':   Icons.hourglass_empty_rounded,
    'confirmed': Icons.check_circle_outline_rounded,
    'shipping':  Icons.local_shipping_outlined,
    'delivered': Icons.done_all_rounded,
    'cancelled': Icons.cancel_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final color = _statusColors[order.status] ?? Colors.grey;
    final icon  = _statusIcons[order.status] ?? Icons.circle_outlined;
    final fmt   = NumberFormat('#,###', 'ko_KR');

    return Container(
      decoration: AppTheme.cardDecoration(radius: 16),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, size: 18, color: color),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    order.orderNumber,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: color.withOpacity(0.3)),
                  ),
                  child: Text(
                    order.statusLabel,
                    style: TextStyle(
                      fontSize: 11,
                      color: color,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Product
            Text(
              '${order.productName}  ×${order.quantity}',
              style: const TextStyle(
                  fontSize: 13,
                  color: AppTheme.textSecondary,
                  fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 8),
            const Divider(height: 1, color: AppTheme.border),
            const SizedBox(height: 8),

            // Footer
            Row(
              children: [
                const Icon(Icons.person_outline,
                    size: 13, color: AppTheme.textMuted),
                const SizedBox(width: 4),
                Text(
                  order.patientName ?? '-',
                  style: const TextStyle(
                      fontSize: 12, color: AppTheme.textSecondary),
                ),
                const Spacer(),
                Text(
                  '본인부담 ${fmt.format(order.patientCopay)}원',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.primary,
                  ),
                ),
              ],
            ),
            if (order.trackingNumber != null) ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(Icons.local_shipping_outlined,
                      size: 13, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    '운송장: ${order.trackingNumber}',
                    style: const TextStyle(
                        fontSize: 11, color: AppTheme.textSecondary),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.bottomRight,
              child: Text(
                order.createdAt,
                style: const TextStyle(
                    fontSize: 10, color: AppTheme.textMuted),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
