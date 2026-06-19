// lib/screens/main_shell.dart
// 하단 네비게이션 바 쉘

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/chat_provider.dart';
import '../providers/notice_provider.dart';
import '../services/chat_notification_service.dart';
import '../theme/app_theme.dart';

class MainShell extends ConsumerStatefulWidget {
  final StatefulNavigationShell navigationShell;
  const MainShell({super.key, required this.navigationShell});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(chatRoomsProvider.notifier).load();
      ref.read(noticeListProvider.notifier).load();
    });

    ChatNotificationService.instance.onBackgroundMessage =
        (roomId, preview, time) {
      ref.read(chatRoomsProvider.notifier).updatePreview(roomId, preview, time);
    };

    ChatNotificationService.instance.onTap = (roomId) {
      widget.navigationShell.goBranch(2);
      Future.delayed(const Duration(milliseconds: 200), () {
        if (mounted) {
          context.push('/chat/$roomId',
              extra: {'name': '채팅', 'type': 'direct'});
        }
      });
    };
  }

  @override
  void dispose() {
    ChatNotificationService.instance.onBackgroundMessage = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final totalUnread = ref.watch(
      chatRoomsProvider.select(
        (s) => s.rooms.fold(0, (sum, r) => sum + r.unread),
      ),
    );
    final noticeUnread = ref.watch(
      noticeListProvider.select((s) => s.unreadCount),
    );
    final currentIndex = widget.navigationShell.currentIndex;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final shouldExit = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20)),
            title: const Text('앱 종료',
                style: TextStyle(
                    fontWeight: FontWeight.w800, fontSize: 18)),
            content: const Text('앱을 종료하시겠습니까?',
                style: TextStyle(color: AppTheme.textSecondary)),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('취소',
                    style: TextStyle(color: AppTheme.textSecondary)),
              ),
              TextButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('종료',
                    style: TextStyle(
                        color: AppTheme.danger,
                        fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        );
        if (shouldExit == true) SystemNavigator.pop();
      },
      child: Scaffold(
        body: widget.navigationShell,
        bottomNavigationBar: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.08),
                blurRadius: 20,
                offset: const Offset(0, -4),
              ),
            ],
          ),
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              child: Row(
                children: [
                  _NavItem(
                    index: 0,
                    currentIndex: currentIndex,
                    icon: Icons.description_outlined,
                    selectedIcon: Icons.description_rounded,
                    label: '처방전',
                    onTap: () => _goBranch(0),
                  ),
                  _NavItem(
                    index: 1,
                    currentIndex: currentIndex,
                    icon: Icons.upload_file_outlined,
                    selectedIcon: Icons.upload_file,
                    label: '업로드',
                    onTap: () => _goBranch(1),
                  ),
                  _NavItem(
                    index: 2,
                    currentIndex: currentIndex,
                    icon: Icons.chat_bubble_outline_rounded,
                    selectedIcon: Icons.chat_bubble_rounded,
                    label: '채팅',
                    badgeCount: totalUnread,
                    onTap: () => _goBranch(2),
                  ),
                  _NavItem(
                    index: 3,
                    currentIndex: currentIndex,
                    icon: Icons.settings_outlined,
                    selectedIcon: Icons.settings,
                    label: '설정',
                    badgeCount: noticeUnread,
                    onTap: () => _goBranch(3),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _goBranch(int index) {
    widget.navigationShell.goBranch(
      index,
      initialLocation: true,
    );
  }
}

class _NavItem extends StatelessWidget {
  final int index;
  final int currentIndex;
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int badgeCount;
  final VoidCallback onTap;

  const _NavItem({
    required this.index,
    required this.currentIndex,
    required this.icon,
    required this.selectedIcon,
    required this.label,
    this.badgeCount = 0,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isSelected = index == currentIndex;

    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOutCubic,
          margin: const EdgeInsets.symmetric(horizontal: 4),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: isSelected
              ? BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withOpacity(0.3),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                )
              : BoxDecoration(
                  color: Colors.transparent,
                  borderRadius: BorderRadius.circular(16),
                ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Icon(
                    isSelected ? selectedIcon : icon,
                    color: isSelected
                        ? Colors.white
                        : AppTheme.textMuted,
                    size: 24,
                  ),
                  if (badgeCount > 0)
                    Positioned(
                      top: -4,
                      right: -8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 4, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.danger,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                              color: Colors.white, width: 1.5),
                        ),
                        constraints:
                            const BoxConstraints(minWidth: 16),
                        child: Text(
                          badgeCount > 99 ? '99+' : '$badgeCount',
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected
                      ? FontWeight.w700
                      : FontWeight.w500,
                  color: isSelected
                      ? Colors.white
                      : AppTheme.textMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
