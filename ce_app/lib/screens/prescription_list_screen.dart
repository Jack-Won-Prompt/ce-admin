// lib/screens/prescription_list_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../models/prescription.dart';
import '../providers/prescription_provider.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class PrescriptionListScreen extends ConsumerStatefulWidget {
  const PrescriptionListScreen({super.key});

  @override
  ConsumerState<PrescriptionListScreen> createState() =>
      _PrescriptionListScreenState();
}

class _PrescriptionListScreenState
    extends ConsumerState<PrescriptionListScreen> {
  final _scrollCtrl = ScrollController();
  String _statusFilter = '';

  static const _statusOptions = [
    ('',               '전체'),
    ('ocr_processing', 'OCR 처리중'),
    ('ocr_done',       'OCR 완료'),
    ('review_needed',  '검수 필요'),
    ('approved',       '검수 완료'),
    ('rejected',       '반려'),
    ('ordered',        '주문 완료'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(prescriptionListProvider.notifier).load(refresh: true);
    });
    _scrollCtrl.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 200) {
      ref.read(prescriptionListProvider.notifier).load();
    }
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(prescriptionListProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(prescriptionListProvider.notifier).load(refresh: true),
        color: AppTheme.primary,
        child: CustomScrollView(
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // ── Dark gradient header ──────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
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
                                gradient: AppTheme.primaryGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(Icons.description_rounded,
                                  color: Colors.white, size: 22),
                            ),
                            const SizedBox(width: 14),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  '내 처방전',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 22,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -0.3),
                                ),
                                Text(
                                  state.total > 0
                                      ? '내가 올린 처방전 ${state.total}건'
                                      : '내가 올린 처방전',
                                  style: TextStyle(
                                      color: Colors.white.withOpacity(0.6),
                                      fontSize: 12),
                                ),
                              ],
                            ),
                            const Spacer(),
                            const UserNameBadge(),
                          ],
                        ),
                      ),

                      // Filter chips
                      Container(
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.vertical(top: Radius.circular(24)),
                        ),
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
                          child: Row(
                            children: _statusOptions.map((opt) {
                              final selected = _statusFilter == opt.$1;
                              return Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: GestureDetector(
                                  onTap: () {
                                    setState(
                                        () => _statusFilter = opt.$1);
                                    ref
                                        .read(prescriptionListProvider
                                            .notifier)
                                        .setStatusFilter(opt.$1);
                                  },
                                  child: AnimatedContainer(
                                    duration:
                                        const Duration(milliseconds: 180),
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 14, vertical: 7),
                                    decoration: BoxDecoration(
                                      gradient: selected
                                          ? AppTheme.primaryGradient
                                          : null,
                                      color: selected
                                          ? null
                                          : Colors.grey.shade100,
                                      borderRadius:
                                          BorderRadius.circular(20),
                                      boxShadow: selected
                                          ? [
                                              BoxShadow(
                                                color: AppTheme.primary
                                                    .withOpacity(0.3),
                                                blurRadius: 8,
                                                offset: const Offset(0, 3),
                                              )
                                            ]
                                          : null,
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
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // ── Body ──────────────────────────────────────────────────
            if (state.isLoading && state.items.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.error != null && state.items.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: AppTheme.danger.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(Icons.error_outline_rounded,
                            color: AppTheme.danger, size: 28),
                      ),
                      const SizedBox(height: 12),
                      const Text('데이터를 불러오지 못했습니다.',
                          style: TextStyle(
                              color: AppTheme.textMuted,
                              fontWeight: FontWeight.w500)),
                      if (state.error != null)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(24, 6, 24, 0),
                          child: Text(
                            state.error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.danger),
                          ),
                        ),
                      const SizedBox(height: 16),
                      GestureDetector(
                        onTap: () => ref
                            .read(prescriptionListProvider.notifier)
                            .load(refresh: true),
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
            else if (state.items.isEmpty)
              const SliverFillRemaining(
                child: EmptyWidget(
                  message: '업로드한 처방전이 없습니다.',
                  icon: Icons.description_outlined,
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == state.items.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(
                              child: CircularProgressIndicator(
                                  color: AppTheme.primary)),
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _PrescriptionCard(
                          prescription: state.items[i],
                          onTap: () => context.push(
                              '/prescriptions/${state.items[i].rxNumber}'),
                        ),
                      );
                    },
                    childCount:
                        state.items.length + (state.hasMore ? 1 : 0),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// ── Prescription Card ─────────────────────────────────────────────────────────
class _PrescriptionCard extends StatelessWidget {
  final Prescription prescription;
  final VoidCallback onTap;

  const _PrescriptionCard({
    required this.prescription,
    required this.onTap,
  });

  static const _statusColors = {
    'pending':        Color(0xFF9E9E9E),
    'ocr_processing': AppTheme.warning,
    'ocr_done':       AppTheme.secondary,
    'review_needed':  AppTheme.danger,
    'approved':       AppTheme.success,
    'rejected':       Color(0xFFB71C1C),
    'ordered':        AppTheme.primary,
  };

  static const _statusIcons = {
    'pending':        Icons.hourglass_empty_rounded,
    'ocr_processing': Icons.auto_fix_high_rounded,
    'ocr_done':       Icons.check_circle_outline_rounded,
    'review_needed':  Icons.warning_amber_rounded,
    'approved':       Icons.verified_rounded,
    'rejected':       Icons.cancel_outlined,
    'ordered':        Icons.shopping_bag_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final color = _statusColors[prescription.status] ?? Colors.grey;
    final icon  = _statusIcons[prescription.status] ?? Icons.circle_outlined;

    return GestureDetector(
      onTap: onTap,
      child: Container(
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
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(icon, size: 20, color: color),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      prescription.rxNumber,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                          color: AppTheme.textPrimary),
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
                      prescription.statusLabel,
                      style: TextStyle(
                        fontSize: 11,
                        color: color,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Info row
              Row(
                children: [
                  _InfoChip(
                    icon: Icons.person_outline,
                    text: prescription.patientName ?? '-',
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _InfoChip(
                      icon: Icons.local_hospital_outlined,
                      text: prescription.hospital ?? '-',
                    ),
                  ),
                ],
              ),

              if (prescription.diseaseName != null) ...[
                const SizedBox(height: 6),
                _InfoChip(
                  icon: Icons.medical_information_outlined,
                  text: prescription.diseaseName!,
                ),
              ],

              const SizedBox(height: 10),
              const Divider(height: 1, color: AppTheme.border),
              const SizedBox(height: 10),

              // Footer
              Row(
                children: [
                  if (prescription.issuedDate != null) ...[
                    const Icon(Icons.calendar_today_outlined,
                        size: 11, color: AppTheme.textMuted),
                    const SizedBox(width: 3),
                    Text(
                      '발급 ${prescription.issuedDate}',
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted),
                    ),
                    const SizedBox(width: 10),
                  ],
                  if (prescription.ocrConfidence != null) ...[
                    _ConfidenceBadge(prescription.ocrConfidence!),
                    const SizedBox(width: 10),
                  ],
                  const Spacer(),
                  Text(
                    prescription.createdAt,
                    style: const TextStyle(
                        fontSize: 11, color: AppTheme.textMuted),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String text;

  const _InfoChip({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 13, color: AppTheme.textMuted),
      const SizedBox(width: 4),
      Flexible(
        child: Text(
          text,
          style: const TextStyle(
              fontSize: 13, color: AppTheme.textSecondary),
          overflow: TextOverflow.ellipsis,
        ),
      ),
    ],
  );
}

class _ConfidenceBadge extends StatelessWidget {
  final int confidence;
  const _ConfidenceBadge(this.confidence);

  @override
  Widget build(BuildContext context) {
    final color = confidence >= 85
        ? AppTheme.success
        : confidence >= 60
            ? AppTheme.warning
            : AppTheme.danger;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Text(
        'OCR $confidence%',
        style: TextStyle(
            fontSize: 10, color: color, fontWeight: FontWeight.w700),
      ),
    );
  }
}
