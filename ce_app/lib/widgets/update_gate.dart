// lib/widgets/update_gate.dart
// 어느 화면에 있든 새 판이 나왔는지 지켜보다가 알린다.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../router/app_router.dart';
import '../services/app_update_service.dart';
import '../theme/app_theme.dart';

/// 앱 전체를 감싸 판을 지켜본다.
///
/// 화면마다 확인을 붙이지 않는다 — 붙이는 자리를 하나라도 빠뜨리면 그 화면에
/// 머무는 사람은 새 판이 나온 줄 모른다. 여기 하나로 어디에 있든 알린다.
///
/// 확인하는 때는 둘이다. 앱을 켤 때, 그리고 다른 앱에 갔다 돌아올 때.
/// 돌아올 때를 넣은 까닭은 스토어에서 업데이트를 마치고 돌아오는 길이 그
/// 자리이기 때문이다.
class UpdateGate extends ConsumerStatefulWidget {
  final Widget child;
  const UpdateGate({super.key, required this.child});

  @override
  ConsumerState<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends ConsumerState<UpdateGate>
    with WidgetsBindingObserver {
  /// 되돌아올 때마다 묻지 않는다 — 잠깐 다른 앱을 봤다 오는 일이 잦다.
  static const _interval = Duration(minutes: 30);

  DateTime? _lastChecked;
  bool _dialogOpen = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _check());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _check();
  }

  Future<void> _check({bool force = false}) async {
    if (_dialogOpen) return;

    final last = _lastChecked;
    if (!force && last != null && DateTime.now().difference(last) < _interval) {
      return;
    }
    _lastChecked = DateTime.now();

    final service = ref.read(appUpdateServiceProvider);
    final info    = await service.fetch();
    if (info == null || !mounted) return;

    final current = await service.currentVersion();
    final verdict = AppUpdateService.verdictFor(current, info);
    if (verdict == UpdateVerdict.none || !mounted) return;

    await _showDialog(verdict, info, current);
  }

  Future<void> _showDialog(
      UpdateVerdict verdict, AppUpdateInfo info, String current) async {
    final ctx = rootNavigatorKey.currentContext;
    if (ctx == null) return;

    final mustUpdate = verdict == UpdateVerdict.required;
    _dialogOpen = true;

    await showDialog<void>(
      context: ctx,
      // 넘길 수 없는 안내는 바깥을 눌러 닫지 못하게 한다
      barrierDismissible: !mustUpdate,
      builder: (dialogCtx) => PopScope(
        canPop: !mustUpdate,
        child: AlertDialog(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Text(
            mustUpdate ? '업데이트가 필요합니다' : '새 판이 나왔습니다',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                mustUpdate
                    ? '지금 판으로는 더 이상 이용할 수 없습니다.\n스토어에서 업데이트해 주세요.'
                    : '더 나은 판이 스토어에 올라와 있습니다.',
                style: const TextStyle(fontSize: 14, height: 1.6),
              ),
              if ((info.notice ?? '').isNotEmpty) ...[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.background,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(info.notice!,
                      style: const TextStyle(
                          fontSize: 13, height: 1.6,
                          color: AppTheme.textSecondary)),
                ),
              ],
              const SizedBox(height: 12),
              Text(
                '지금 $current  →  새 판 ${info.latest ?? info.min ?? ''}',
                style: const TextStyle(
                    fontSize: 12, color: AppTheme.textMuted),
              ),
            ],
          ),
          actions: [
            if (!mustUpdate)
              TextButton(
                onPressed: () => Navigator.pop(dialogCtx),
                child: const Text('나중에'),
              ),
            TextButton(
              onPressed: () async {
                await _openStore(info.storeUrl);
                // 넘길 수 없는 안내는 스토어를 열어도 그대로 둔다 — 돌아왔을 때
                // 아직 옛 판이면 다시 막아야 한다.
                if (!mustUpdate && dialogCtx.mounted) Navigator.pop(dialogCtx);
              },
              child: const Text('업데이트',
                  style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ],
        ),
      ),
    );

    _dialogOpen = false;

    /* 넘길 수 없는 안내를 닫고 나왔다면(기기 뒤로 가기 등) 곧바로 다시 세운다.
       여기서 놓아 주면 못 쓰는 판으로 자료를 올리게 된다. */
    if (mustUpdate && mounted) {
      WidgetsBinding.instance
          .addPostFrameCallback((_) => _check(force: true));
    }
  }

  Future<void> _openStore(String? url) async {
    if (url == null || url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
