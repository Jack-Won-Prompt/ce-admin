// lib/screens/notice_list_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/notice_provider.dart';
import '../models/notice.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class NoticeListScreen extends ConsumerStatefulWidget {
  const NoticeListScreen({super.key});

  @override
  ConsumerState<NoticeListScreen> createState() => _NoticeListScreenState();
}

class _NoticeListScreenState extends ConsumerState<NoticeListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(noticeListProvider.notifier).load();
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
      ref.read(noticeListProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(noticeListProvider);

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: () => ref.read(noticeListProvider.notifier).load(),
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
                                gradient: AppTheme.infoGradient,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                    color: Colors.white.withOpacity(0.3),
                                    width: 2),
                              ),
                              child: const Icon(Icons.campaign_rounded,
                                  color: Colors.white, size: 22),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              '공지사항',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 26,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.5),
                            ),
                            Text(
                              '총 ${state.notices.length}개',
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
                                  ref.read(noticeListProvider.notifier).load(),
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
            if (state.isLoading && state.notices.isEmpty)
              const SliverFillRemaining(child: LoadingWidget())
            else if (state.notices.isEmpty)
              const SliverFillRemaining(
                child: EmptyWidget(
                    message: '공지사항이 없습니다.',
                    icon: Icons.campaign_outlined),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) {
                      if (i == state.notices.length) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(
                              child: CircularProgressIndicator(
                                  color: AppTheme.primary)),
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _NoticeItem(
                          notice: state.notices[i],
                          onTap: () => context.push(
                              '/notices/${state.notices[i].id}'),
                        ),
                      );
                    },
                    childCount:
                        state.notices.length + (state.hasMore ? 1 : 0),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _NoticeItem extends StatelessWidget {
  final Notice notice;
  final VoidCallback onTap;

  const _NoticeItem({required this.notice, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: notice.isRead
              ? AppTheme.surface
              : AppTheme.primary.withOpacity(0.04),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: notice.isRead
                ? AppTheme.border
                : AppTheme.primary.withOpacity(0.2),
          ),
          boxShadow: const [
            BoxShadow(
                color: AppTheme.cardShadow,
                blurRadius: 12,
                offset: Offset(0, 4)),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Leading icon
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  gradient: notice.isPinned
                      ? AppTheme.warningGradient
                      : AppTheme.infoGradient,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  notice.isPinned
                      ? Icons.push_pin_rounded
                      : Icons.campaign_rounded,
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
                        if (!notice.isRead) ...[
                          Container(
                            width: 7,
                            height: 7,
                            margin: const EdgeInsets.only(right: 6, top: 1),
                            decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                color: AppTheme.primary),
                          ),
                        ],
                        Expanded(
                          child: Text(
                            notice.title,
                            style: TextStyle(
                              fontWeight: notice.isRead
                                  ? FontWeight.w600
                                  : FontWeight.w800,
                              fontSize: 14,
                              color: AppTheme.textPrimary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(Icons.person_outline,
                            size: 11, color: AppTheme.textMuted),
                        const SizedBox(width: 3),
                        Text(notice.author,
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.textMuted)),
                        const SizedBox(width: 10),
                        const Icon(Icons.calendar_today_outlined,
                            size: 11, color: AppTheme.textMuted),
                        const SizedBox(width: 3),
                        Text(notice.date,
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.textMuted)),
                        const Spacer(),
                        const Icon(Icons.visibility_outlined,
                            size: 11, color: AppTheme.textMuted),
                        const SizedBox(width: 3),
                        Text('${notice.views}',
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.textMuted)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
