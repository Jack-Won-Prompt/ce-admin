// lib/services/update_service.dart

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../router/app_router.dart';
import '../theme/app_theme.dart';
import '../utils/constants.dart';

/// 새 버전이 나왔는지 서버에 묻고 안내한다 (2026-09-29 지시).
///
/// 스토어를 쓰지 않고 APK 를 직접 나눠 주기로 했다. 그러면 스토어가 해 주던
/// 「새 버전이 나왔습니다」를 우리가 해야 한다 — 서버의 **환경 설정 › 모바일 앱** 에
/// 적어 둔 버전과 받는 주소를 읽어, 낡은 버전이면 그 자리에서 알린다.
///
///   · 최소 버전보다 낮으면 → 「업데이트가 필요합니다」
///   · 최신 버전보다 낮으면 → 「새 버전이 있습니다」
///
/// 어느 쪽이든 **닫을 수 있다** (2026-09-29 지시). 급한 일을 하는 중에 창이 길을
/// 막으면 그게 더 큰 일이 된다 — 대신 다시 열 때마다 또 묻고, 설정 화면에
/// 「업데이트」 자리를 두어 언제든 스스로 받을 수 있게 한다.
///
/// 받기는 주소를 브라우저로 넘긴다. 내려받은 APK 를 눌러 깔면 된다 — 앱이 직접
/// 설치하려면 「출처를 알 수 없는 앱」 권한이 필요해서, 그 권한을 심지 않는 쪽을
/// 골랐다.
class UpdateService {
  static bool _running = false;

  /// 「나중에」를 누르면 앱이 켜져 있는 동안 다시 묻지 않는다.
  /// 최소 버전 미만이면 이 값을 보지 않는다 — 그때는 다시 열 때마다 알린다.
  static bool _snoozed = false;

  // ── 앱이 열릴 때ㆍ다시 앞으로 올 때 스스로 묻는 길 ──────────────

  static Future<void> checkAndUpdate() async {
    if (kIsWeb || _running) return;
    _running = true;

    try {
      final server = await _fetch();
      if (server == null) return;

      final me = await _myVersion();

      final blocked = _older(me, server.min);
      final newer   = _older(me, server.latest);

      if (!blocked && (!newer || _snoozed)) return;

      final context = await _waitForScreen();
      if (context == null) return;

      await _show(context,
          current: me,
          target:  blocked ? server.min! : server.latest!,
          url:     server.url,
          notice:  server.notice,
          blocked: blocked);
    } catch (_) {
      // 못 물어봤으면 조용히 지나간다 — 다음에 앞으로 올 때 다시 묻는다
    } finally {
      _running = false;
    }
  }

  // ── 설정 화면에서 손으로 누르는 길 (2026-09-29 지시) ─────────────

  /// 설정 › 업데이트. 스스로 묻는 길과 달리 **결과를 늘 알린다** —
  /// 눌렀는데 아무 일도 없으면 눌린 것인지 알 수 없다.
  static Future<void> checkNow(BuildContext context) async {
    try {
      final server = await _fetch();
      final me     = await _myVersion();

      if (!context.mounted) return;

      if (server == null || (server.latest == null && server.min == null)) {
        _tell(context, '등록된 새 버전이 없습니다. (현재 $me)');
        return;
      }

      final blocked = _older(me, server.min);
      final newer   = _older(me, server.latest);

      if (!blocked && !newer) {
        _tell(context, '최신 버전입니다. (현재 $me)');
        return;
      }

      await _show(context,
          current: me,
          target:  blocked ? server.min! : server.latest!,
          url:     server.url,
          notice:  server.notice,
          blocked: blocked);
    } catch (_) {
      if (context.mounted) {
        _tell(context, '버전을 확인하지 못했습니다. 잠시 후 다시 시도해 주십시오.');
      }
    }
  }

  /// 지금 깔린 버전 (예: 1.3.3)
  static Future<String> myVersion() => _myVersion();

  static Future<String> _myVersion() async =>
      (await PackageInfo.fromPlatform()).version;

  // ── 내부 ───────────────────────────────────────────────────────

  /// 서버에 묻는다. 로그인 앞이라 토큰을 싣지 않는다 — 낡은 버전은 로그인조차
  /// 못 하는 경우가 있어, 그 자리에서도 안내가 떠야 한다.
  static Future<_Latest?> _fetch() async {
    final dio = Dio(BaseOptions(
      baseUrl:        AppConstants.baseUrl,
      connectTimeout: const Duration(seconds: 6),
      receiveTimeout: const Duration(seconds: 6),
    ));

    final body = (await dio.get('/app/version')).data;
    if (body is! Map) return null;

    return _Latest(
      latest: body['latest']       as String?,
      min:    body['min']          as String?,
      url:    body['download_url'] as String?,
      notice: body['notice']       as String?,
    );
  }

  /// 창을 띄울 자리. 스플래시에서 띄우면 다음 화면으로 넘어갈 때 함께 사라진다.
  static Future<BuildContext?> _waitForScreen() async {
    for (var i = 0; i < 30; i++) {
      final context = rootNavigatorKey.currentContext;

      if (context != null && context.mounted) {
        final path =
            GoRouter.of(context).routeInformationProvider.value.uri.path;
        if (path != '/') return context;
      }

      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
    return null;
  }

  static void _tell(BuildContext context, String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.all(16),
    ));
  }

  /// [me] 가 [other] 보다 낮은가. 1.3.3 / 1.3.10 처럼 마디로 견준다.
  /// [other] 가 비어 있으면 견줄 것이 없으니 거짓이다.
  static bool _older(String me, String? other) {
    if (other == null || other.trim().isEmpty) return false;

    final a = _parts(me);
    final b = _parts(other);

    for (var i = 0; i < 3; i++) {
      if (a[i] != b[i]) return a[i] < b[i];
    }
    return false;
  }

  /// 1.3.3+10ㆍ1.3.3-dev 처럼 뒤에 무엇이 붙어도 앞의 세 마디만 본다.
  static List<int> _parts(String v) {
    final nums = v.split('+').first.split('.');

    return List<int>.generate(
      3,
      (i) => i < nums.length
          ? (int.tryParse(RegExp(r'^\d+').stringMatch(nums[i].trim()) ?? '') ?? 0)
          : 0,
    );
  }

  static Future<void> _show(
    BuildContext context, {
    required String current,
    required String target,
    required String? url,
    required String? notice,
    required bool blocked,
  }) async {
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(blocked ? '업데이트가 필요합니다' : '새 버전이 있습니다',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              blocked
                  ? '현재 버전($current)은 지원하지 않습니다. $target 버전으로 업데이트해 주십시오.'
                  : '$target 버전이 나왔습니다. (현재 $current)',
              style: const TextStyle(fontSize: 14, height: 1.6),
            ),
            if (notice != null && notice.trim().isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.background,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(notice,
                    style: const TextStyle(fontSize: 13, height: 1.6)),
              ),
            ],
            if (url == null) ...[
              const SizedBox(height: 10),
              const Text('받는 주소가 설정되지 않았습니다. 담당자에게 문의해 주십시오.',
                  style: TextStyle(fontSize: 12.5, color: AppTheme.danger)),
            ],
          ],
        ),
        actions: [
          /* 어느 쪽이든 닫을 수 있다 (2026-09-29 지시). 다만 낱말을 달리해 둔다 —
             「나중에」는 이 앱이 켜져 있는 동안 다시 묻지 않고,
             「닫기」는 다시 열 때 또 알린다. */
          TextButton(
            onPressed: () {
              if (!blocked) _snoozed = true;
              Navigator.pop(ctx);
            },
            child: Text(blocked ? '닫기' : '나중에'),
          ),
          if (url != null)
            FilledButton(
              onPressed: () async {
                await launchUrl(Uri.parse(url),
                    mode: LaunchMode.externalApplication);
                if (ctx.mounted) Navigator.pop(ctx);
              },
              child: const Text('지금 받기'),
            ),
        ],
      ),
    );
  }
}

/// 서버가 알려 준 버전과 받는 주소.
class _Latest {
  final String? latest;
  final String? min;
  final String? url;
  final String? notice;

  const _Latest({this.latest, this.min, this.url, this.notice});
}
