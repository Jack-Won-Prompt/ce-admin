// lib/screens/settings_screen.dart

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../providers/auth_provider.dart';
import '../providers/notice_provider.dart';
import '../services/update_service.dart';
import '../theme/app_theme.dart';
import '../utils/constants.dart';
import '../widgets/common_widgets.dart';

/// 로그인한 사용자 정보 (이름, 이메일)
///
/// **화면에 들어올 때마다 다시 읽는다** (2026-09-30 실전 시험에서 드러남).
///
/// 그냥 FutureProvider 로 두었더니 앱을 켠 뒤 처음 읽은 값을 앱이 꺼질 때까지
/// 그대로 보여 주었다 — 로그아웃하고 다른 사람으로 다시 들어와도 계정 카드는
/// 먼저 사람 이름과 메일 주소를 그대로 달고 있었다. 헤더의 이름 배지는 제대로
/// 바뀌므로 한 화면에서 두 사람이 보인다.
///
///   헤더 배지  Stella Kim      (로그인할 때 갱신되는 값)
///   계정 카드  관리자 · admin@ce-admin.co.kr   (앱을 켤 때 읽은 값)
///
/// 누구로 들어와 있는지는 서류를 누구 이름으로 올리느냐를 가르므로, 잘못 보이면
/// 사람이 남의 이름으로 올린다. autoDispose 로 두면 화면을 떠날 때 버려지고
/// 다시 들어올 때 새로 읽는다.
final _userInfoProvider =
    FutureProvider.autoDispose<({String name, String email})>((ref) async {
  final prefs = await SharedPreferences.getInstance();
  return (
    name:  prefs.getString(AppConstants.keyUserName)  ?? '',
    email: prefs.getString(AppConstants.keyUserEmail) ?? '',
  );
});

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState    = ref.watch(authNotifierProvider);
    final isLoading    = authState.isLoading;
    final noticeState  = ref.watch(noticeListProvider);
    final unreadNotice = noticeState.unreadCount;

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // ── Header ─────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Container(
              decoration: const BoxDecoration(gradient: AppTheme.darkGradient),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
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
                            child: const Icon(Icons.settings_rounded,
                                color: Colors.white, size: 22),
                          ),
                          const Spacer(),
                          const UserNameBadge(),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        '설정',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 26,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5),
                      ),
                      Text(
                        '앱 설정 및 계정 관리',
                        style: TextStyle(
                            color: Colors.white.withOpacity(0.6),
                            fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // ── Body ───────────────────────────────────────────────────
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                // 프로필 카드
                _ProfileCard(ref: ref),
                const SizedBox(height: 20),

                // 서비스
                _sectionHeader('서비스', AppTheme.primaryGradient),
                const SizedBox(height: 10),
                Container(
                  decoration: AppTheme.cardDecoration(radius: 16),
                  child: Column(
                    children: [
                      _MenuItem(
                        icon: Icons.campaign_rounded,
                        iconGradient: AppTheme.infoGradient,
                        title: '공지사항',
                        trailing: unreadNotice > 0
                            ? Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: AppTheme.danger,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  unreadNotice > 99
                                      ? '99+'
                                      : '$unreadNotice',
                                  style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700),
                                ),
                              )
                            : null,
                        onTap: () => context.push('/notices'),
                      ),
                      const Divider(
                          height: 1,
                          indent: 68,
                          endIndent: 16,
                          color: AppTheme.border),
                      _MenuItem(
                        icon: Icons.notifications_active_outlined,
                        iconGradient: AppTheme.secondaryGradient,
                        title: '알림 이력',
                        onTap: () => context.push('/notifications'),
                      ),
                      const Divider(
                          height: 1,
                          indent: 68,
                          endIndent: 16,
                          color: AppTheme.border),
                      _MenuItem(
                        icon: Icons.contact_support_rounded,
                        iconGradient: AppTheme.accentGradient,
                        title: '문의하기',
                        onTap: () => context.push('/inquiries'),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 20),

                // 앱 정보
                _sectionHeader('앱 정보', AppTheme.secondaryGradient),
                const SizedBox(height: 10),
                Container(
                  decoration: AppTheme.cardDecoration(radius: 16),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          decoration: BoxDecoration(
                            gradient: AppTheme.primaryGradient,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Icon(Icons.medical_services_rounded,
                              color: Colors.white, size: 22),
                        ),
                        const SizedBox(width: 14),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Coloplast CE Admin',
                              style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppTheme.textPrimary),
                            ),
                            const SizedBox(height: 2),
                            /* 깔린 버전을 그대로 읽어 보인다 — 예전에는 글을 박아
                               두어 앱을 올려도 옛 번호가 남아 있었다. */
                            FutureBuilder<String>(
                              future: UpdateService.myVersion(),
                              builder: (ctx, snap) => Text(
                                snap.hasData ? 'v${snap.data}' : '버전 확인 중',
                                style: const TextStyle(
                                    fontSize: 12, color: AppTheme.textMuted),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 10),

                /* 스토어를 쓰지 않으므로 스스로 받을 자리가 있어야 한다
                   (2026-09-29 지시). 안내 창을 닫았거나 아직 받지 못했을 때
                   여기서 확인해 받는다. */
                Container(
                  decoration: AppTheme.cardDecoration(radius: 16),
                  child: _MenuItem(
                    icon: Icons.system_update_rounded,
                    iconGradient: AppTheme.primaryGradient,
                    title: '업데이트 확인',
                    onTap: () => UpdateService.checkNow(context),
                  ),
                ),

                const SizedBox(height: 20),

                // 계정
                _sectionHeader('계정', AppTheme.dangerGradient),
                const SizedBox(height: 10),
                Container(
                  decoration: AppTheme.cardDecoration(radius: 16),
                  child: _MenuItem(
                    icon: Icons.logout_rounded,
                    iconGradient: AppTheme.dangerGradient,
                    title: '로그아웃',
                    titleColor: AppTheme.danger,
                    trailing: isLoading
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppTheme.danger))
                        : null,
                    onTap: isLoading
                        ? null
                        : () async {
                            final confirm = await showConfirmDialog(
                              context,
                              title: '로그아웃',
                              content: '로그아웃 하시겠습니까?',
                              confirmLabel: '로그아웃',
                            );
                            if (confirm != true) return;
                            await ref
                                .read(authNotifierProvider.notifier)
                                .logout();
                            if (context.mounted) context.go('/login');
                          },
                  ),
                ),
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader(String title, LinearGradient gradient) {
    return Row(
      children: [
        Container(
          width: 5,
          height: 18,
          decoration: BoxDecoration(
            gradient: gradient,
            borderRadius: BorderRadius.circular(3),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AppTheme.textSecondary,
            letterSpacing: 0.3,
          ),
        ),
      ],
    );
  }
}

// ── Profile Card ──────────────────────────────────────────────────────────────
class _ProfileCard extends StatelessWidget {
  final WidgetRef ref;
  const _ProfileCard({required this.ref});

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(_userInfoProvider);

    return Container(
      decoration: AppTheme.cardDecoration(radius: 16),
      padding: const EdgeInsets.all(16),
      child: userAsync.when(
        loading: () => const SizedBox(
          height: 60,
          child: Center(
              child: CircularProgressIndicator(
                  strokeWidth: 2, color: AppTheme.primary)),
        ),
        error: (e, _) => const SizedBox.shrink(),
        data: (info) => Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Center(
                child: Text(
                  info.name.isNotEmpty
                      ? info.name.substring(0, 1)
                      : '?',
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.w800),
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    info.name.isNotEmpty ? info.name : '-',
                    style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.textPrimary),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    info.email.isNotEmpty ? info.email : '-',
                    style: const TextStyle(
                        fontSize: 13,
                        color: AppTheme.textMuted),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final LinearGradient iconGradient;
  final String title;
  final Color titleColor;
  final Widget? trailing;
  final VoidCallback? onTap;

  const _MenuItem({
    required this.icon,
    required this.iconGradient,
    required this.title,
    this.titleColor = AppTheme.textPrimary,
    this.trailing,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                gradient: iconGradient,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(icon, color: Colors.white, size: 18),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: titleColor,
                ),
              ),
            ),
            if (trailing != null) ...[
              trailing!,
              const SizedBox(width: 6),
            ],
            Icon(Icons.chevron_right_rounded,
                size: 20, color: AppTheme.textMuted),
          ],
        ),
      ),
    );
  }
}
