// lib/screens/notification_list_screen.dart
// 앱으로 온 알림 이력. 눌러서 그 화면으로 간다.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../models/app_notification.dart';
import '../services/notification_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common_widgets.dart';

class NotificationListScreen extends ConsumerStatefulWidget {
  const NotificationListScreen({super.key});

  @override
  ConsumerState<NotificationListScreen> createState() =>
      _NotificationListScreenState();
}

class _NotificationListScreenState
    extends ConsumerState<NotificationListScreen> {
  final _scrollCtrl = ScrollController();

  final List<AppNotification> _items = [];
  int  _page    = 1;
  int  _unread  = 0;
  bool _hasMore = false;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load(refresh: true);
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >=
          _scrollCtrl.position.maxScrollExtent - 200) {
        _load();
      }
    });
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading && !refresh) return;
    if (!refresh && !_hasMore) return;

    setState(() {
      _loading = true;
      if (refresh) _error = null;
    });

    try {
      final page = refresh ? 1 : _page + 1;
      final res  = await ref.read(notificationServiceProvider).list(page: page);

      setState(() {
        if (refresh) _items.clear();
        _items.addAll(res.items);
        _page    = page;
        _unread  = res.unread;
        _hasMore = res.hasMore;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _error   = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  /// 알림을 누르면 그 화면으로 간다.
  ///
  /// 갈 곳이 없는 알림(앱이 모르는 새 갈래 등)은 누를 수 없게 보인다 —
  /// 눌러도 아무 일이 없으면 고장으로 읽힌다.
  Future<void> _open(AppNotification n) async {
    if (!n.isRead) {
      await ref.read(notificationServiceProvider).markRead(n.id);
      if (mounted) {
        setState(() => _unread = _unread > 0 ? _unread - 1 : 0);
      }
    }
    if (!mounted) return;

    switch (n.type) {
      case 'chat':
        context.push('/chat/${n.targetKey}',
            extra: {'name': n.title, 'type': 'group'});
        break;
      case 'rx_reupload':
        context.push('/prescriptions/${n.targetKey}');
        break;
    }

    // 돌아왔을 때 읽음 표시가 반영되도록 다시 읽는다
    if (mounted) _load(refresh: true);
  }

  Future<void> _markAllRead() async {
    await ref.read(notificationServiceProvider).markAllRead();
    if (mounted) _load(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('알림 이력'),
        actions: [
          if (_unread > 0)
            TextButton(
              onPressed: _markAllRead,
              child: const Text('모두 읽음',
                  style: TextStyle(color: Colors.white, fontSize: 13)),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _load(refresh: true),
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading && _items.isEmpty) return const LoadingWidget();

    if (_error != null && _items.isEmpty) {
      return ListView(children: [
        const SizedBox(height: 80),
        Center(
          child: Text(_error!,
              style: const TextStyle(color: AppTheme.danger)),
        ),
      ]);
    }

    if (_items.isEmpty) {
      return ListView(children: const [
        SizedBox(height: 100),
        Icon(Icons.notifications_none_rounded,
            size: 44, color: AppTheme.textMuted),
        SizedBox(height: 10),
        Center(
          child: Text('받은 알림이 없습니다.',
              style: TextStyle(color: AppTheme.textMuted, fontSize: 14)),
        ),
      ]);
    }

    return ListView.separated(
      controller: _scrollCtrl,
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      itemCount: _items.length + (_hasMore ? 1 : 0),
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        if (i >= _items.length) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 16),
            child: LoadingWidget(),
          );
        }
        return _NotificationTile(
          item: _items[i],
          onTap: _items[i].hasTarget ? () => _open(_items[i]) : null,
        );
      },
    );
  }
}

class _NotificationTile extends StatelessWidget {
  final AppNotification item;
  final VoidCallback?   onTap;

  const _NotificationTile({required this.item, required this.onTap});

  /// 갈래마다 다른 표식. 무엇에 대한 알림인지 글을 읽기 전에 알린다.
  static const _icons = {
    'chat':        Icons.chat_bubble_outline_rounded,
    'rx_reupload': Icons.upload_file_outlined,
  };

  static const _labels = {
    'chat':        '채팅',
    'rx_reupload': '자료 재요청',
  };

  @override
  Widget build(BuildContext context) {
    final unread = !item.isRead;

    return Material(
      color: unread ? AppTheme.primary.withOpacity(0.05) : AppTheme.surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: unread
                  ? AppTheme.primary.withOpacity(0.25)
                  : AppTheme.border,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: AppTheme.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(
                  _icons[item.type] ?? Icons.notifications_none_rounded,
                  size: 18,
                  color: AppTheme.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        if (_labels[item.type] != null) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 6, vertical: 1),
                            decoration: BoxDecoration(
                              color: AppTheme.background,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(_labels[item.type]!,
                                style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                    color: AppTheme.textSecondary)),
                          ),
                          const SizedBox(width: 6),
                        ],
                        if (unread)
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: AppTheme.primary,
                              shape: BoxShape.circle,
                            ),
                          ),
                        const Spacer(),
                        Text(item.createdAt,
                            style: const TextStyle(
                                fontSize: 11, color: AppTheme.textMuted)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(item.title,
                        style: TextStyle(
                            fontSize: 14,
                            fontWeight:
                                unread ? FontWeight.w800 : FontWeight.w700,
                            color: AppTheme.textPrimary)),
                    if ((item.body ?? '').isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(item.body!,
                          style: const TextStyle(
                              fontSize: 13,
                              height: 1.5,
                              color: AppTheme.textSecondary)),
                    ],
                    // 보내려 했으나 기기까지 닿지 못한 것도 이력에 남는다.
                    // 알림이 오지 않았다고 할 때 짚을 근거가 된다.
                    if (!item.sent) ...[
                      const SizedBox(height: 6),
                      const Text('이 알림은 기기로 전달되지 못했습니다.',
                          style: TextStyle(
                              fontSize: 11, color: AppTheme.danger)),
                    ],
                  ],
                ),
              ),
              if (onTap != null)
                const Padding(
                  padding: EdgeInsets.only(left: 4, top: 2),
                  child: Icon(Icons.chevron_right_rounded,
                      size: 18, color: AppTheme.textMuted),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
