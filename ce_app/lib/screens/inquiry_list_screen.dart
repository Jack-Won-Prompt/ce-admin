// lib/screens/inquiry_list_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/inquiry_provider.dart';
import '../models/inquiry.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class InquiryListScreen extends ConsumerStatefulWidget {
  const InquiryListScreen({super.key});

  @override
  ConsumerState<InquiryListScreen> createState() =>
      _InquiryListScreenState();
}

class _InquiryListScreenState extends ConsumerState<InquiryListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(inquiryListProvider.notifier).load();
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(inquiryListProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(inquiryListProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/inquiries/create'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.edit_outlined),
        label: const Text('문의 등록',
            style: TextStyle(fontWeight: FontWeight.w700)),
        elevation: 4,
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(inquiryListProvider.notifier).load(),
        color: AppTheme.primary,
        child: CustomScrollView(
          controller: _scrollController,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // ── Header ───────────────────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                decoration:
                    const BoxDecoration(gradient: AppTheme.darkGradient),
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
                    child: Row(
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                gradient: AppTheme.accentGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(
                                  Icons.contact_support_rounded,
                                  color: Colors.white,
                                  size: 22),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              '문의하기',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 26,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.5),
                            ),
                            Text(
                              '총 ${state.inquiries.length}개',
                              style: TextStyle(
                                  color: Colors.white.withOpacity(0.6),
                                  fontSize: 13),
                            ),
                          ],
                        ),
                        const Spacer(),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const UserNameBadge(),
                            const SizedBox(height: 8),
                            GestureDetector(
                              onTap: () =>
                                  ref.read(inquiryListProvider.notifier).load(),
                              child: Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                      color: Colors.white.withOpacity(0.3)),
                                ),
                                child: const Icon(Icons.refresh_rounded,
                                    color: Colors.white, size: 18),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            // ── Body ─────────────────────────────────────────────────
            if (state.isLoading && state.inquiries.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.inquiries.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: AppTheme.accent.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(Icons.contact_support_outlined,
                            color: AppTheme.accent, size: 28),
                      ),
                      const SizedBox(height: 12),
                      const Text('등록된 문의가 없습니다.',
                          style: TextStyle(
                              fontSize: 14,
                              color: AppTheme.textMuted,
                              fontWeight: FontWeight.w500)),
                      const SizedBox(height: 20),
                      GestureDetector(
                        onTap: () => context.push('/inquiries/create'),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 12),
                          decoration: BoxDecoration(
                            gradient: AppTheme.accentGradient,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.edit_outlined,
                                  color: Colors.white, size: 18),
                              SizedBox(width: 6),
                              Text('문의 등록하기',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 14)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == state.inquiries.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(
                              child: CircularProgressIndicator(
                                  color: AppTheme.primary)),
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _InquiryItem(
                          inquiry: state.inquiries[i],
                          onTap: () => context.push(
                              '/inquiries/${state.inquiries[i].id}'),
                        ),
                      );
                    },
                    childCount: state.inquiries.length +
                        (state.hasMore ? 1 : 0),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _InquiryItem extends StatelessWidget {
  final Inquiry inquiry;
  final VoidCallback onTap;

  const _InquiryItem({required this.inquiry, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isAnswered = inquiry.isAnswered;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: AppTheme.cardDecoration(radius: 16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  gradient: isAnswered
                      ? AppTheme.successGradient
                      : AppTheme.warningGradient,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  isAnswered
                      ? Icons.check_circle_outline_rounded
                      : Icons.hourglass_empty_rounded,
                  size: 18,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color:
                                AppTheme.textMuted.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            inquiry.categoryLabel,
                            style: const TextStyle(
                                fontSize: 11,
                                color: AppTheme.textSecondary,
                                fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isAnswered
                                ? AppTheme.success.withOpacity(0.1)
                                : AppTheme.warning.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isAnswered ? '답변완료' : '답변대기',
                            style: TextStyle(
                              fontSize: 11,
                              color: isAnswered
                                  ? AppTheme.success
                                  : AppTheme.warning,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      inquiry.title,
                      style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.textPrimary),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      inquiry.createdAt,
                      style: const TextStyle(
                          fontSize: 11, color: AppTheme.textMuted),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded,
                  color: AppTheme.textMuted, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}
