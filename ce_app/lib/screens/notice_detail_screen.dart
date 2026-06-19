// lib/screens/notice_detail_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../services/notice_service.dart';
import '../providers/notice_provider.dart';
import '../models/notice.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class NoticeDetailScreen extends ConsumerStatefulWidget {
  final int noticeId;
  const NoticeDetailScreen({super.key, required this.noticeId});

  @override
  ConsumerState<NoticeDetailScreen> createState() =>
      _NoticeDetailScreenState();
}

class _NoticeDetailScreenState extends ConsumerState<NoticeDetailScreen> {
  NoticeDetail? _notice;
  bool          _isLoading = true;
  String?       _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _isLoading = true; _error = null; });
    try {
      final r = await ref.read(noticeServiceProvider).getDetail(widget.noticeId);
      setState(() {
        _notice    = r.notice;
        _isLoading = false;
      });
      ref.read(noticeListProvider.notifier).decrementUnread();
    } catch (e) {
      setState(() { _isLoading = false; _error = e.toString(); });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [
            _buildHeader(null),
            const Expanded(child: LoadingWidget()),
          ],
        ),
      );
    }

    if (_error != null || _notice == null) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        body: Column(
          children: [
            _buildHeader(null),
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline_rounded,
                        size: 48, color: AppTheme.danger),
                    const SizedBox(height: 8),
                    Text(_error ?? '불러오기 실패',
                        style: const TextStyle(color: AppTheme.textMuted)),
                    const SizedBox(height: 12),
                    GestureDetector(
                      onTap: _load,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          gradient: AppTheme.primaryGradient,
                          borderRadius: BorderRadius.circular(10),
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
            ),
          ],
        ),
      );
    }

    final notice = _notice!;

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(child: _buildHeader(notice)),

          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                // Content card
                Container(
                  decoration: AppTheme.cardDecoration(radius: 16),
                  padding: const EdgeInsets.all(20),
                  child: Text(
                    notice.content,
                    style: const TextStyle(
                      fontSize: 15,
                      height: 1.7,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ),

                // Nav tiles
                if (notice.prev != null || notice.next != null) ...[
                  const SizedBox(height: 20),
                  Container(
                    decoration: AppTheme.cardDecoration(radius: 16),
                    child: Column(
                      children: [
                        if (notice.next != null) ...[
                          _NavTile(
                            label: '다음 글',
                            icon: Icons.keyboard_arrow_up_rounded,
                            title: notice.next!['title'] as String,
                            onTap: () => context.pushReplacement(
                                '/notices/${notice.next!['id']}'),
                          ),
                          if (notice.prev != null)
                            const Divider(
                                height: 1,
                                indent: 16,
                                endIndent: 16,
                                color: AppTheme.border),
                        ],
                        if (notice.prev != null)
                          _NavTile(
                            label: '이전 글',
                            icon: Icons.keyboard_arrow_down_rounded,
                            title: notice.prev!['title'] as String,
                            onTap: () => context.pushReplacement(
                                '/notices/${notice.prev!['id']}'),
                          ),
                      ],
                    ),
                  ),
                ],
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(NoticeDetail? notice) {
    return Container(
      decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 24, 0),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back_ios_new_rounded,
                        color: Colors.white, size: 20),
                    onPressed: () => context.pop(),
                  ),
                  const Spacer(),
                  const UserNameBadge(),
                  if (notice?.isPinned == true) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppTheme.warning.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                            color: AppTheme.warning.withOpacity(0.4)),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.push_pin_rounded,
                              size: 12, color: AppTheme.warning),
                          SizedBox(width: 4),
                          Text('공지',
                              style: TextStyle(
                                  fontSize: 11,
                                  color: AppTheme.warning,
                                  fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (notice != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      notice.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        const Icon(Icons.person_outline,
                            size: 12,
                            color: Colors.white60),
                        const SizedBox(width: 4),
                        Text(notice.author,
                            style: TextStyle(
                                fontSize: 12,
                                color: Colors.white.withOpacity(0.6))),
                        const SizedBox(width: 12),
                        const Icon(Icons.calendar_today_outlined,
                            size: 12,
                            color: Colors.white60),
                        const SizedBox(width: 4),
                        Text(notice.date,
                            style: TextStyle(
                                fontSize: 12,
                                color: Colors.white.withOpacity(0.6))),
                        const Spacer(),
                        const Icon(Icons.visibility_outlined,
                            size: 12,
                            color: Colors.white60),
                        const SizedBox(width: 4),
                        Text('${notice.views}',
                            style: TextStyle(
                                fontSize: 12,
                                color: Colors.white.withOpacity(0.6))),
                      ],
                    ),
                  ],
                ),
              )
            else
              const Padding(
                padding: EdgeInsets.fromLTRB(24, 4, 24, 24),
                child: Text('공지사항',
                    style: TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w800)),
              ),
          ],
        ),
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  final String label;
  final IconData icon;
  final String title;
  final VoidCallback onTap;

  const _NavTile({
    required this.label,
    required this.icon,
    required this.title,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Icon(icon, size: 18, color: AppTheme.textMuted),
            const SizedBox(width: 10),
            Text('$label  ',
                style: const TextStyle(
                    fontSize: 11,
                    color: AppTheme.textMuted,
                    fontWeight: FontWeight.w600)),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                    fontSize: 13,
                    color: AppTheme.textPrimary,
                    fontWeight: FontWeight.w600),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const Icon(Icons.chevron_right_rounded,
                size: 16, color: AppTheme.textMuted),
          ],
        ),
      ),
    );
  }
}
