// lib/services/update_service.dart

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../theme/app_theme.dart';
import '../utils/constants.dart';

/// 새 판이 나왔는지 서버에 묻고 안내한다 (2026-09-29 지시).
///
/// 스토어를 쓰지 않고 APK 를 직접 나눠 주기로 했다. 그러면 스토어가 해 주던
/// 「새 판이 나왔습니다」를 우리가 해야 한다 — 서버의 **환경 설정 › 모바일 앱** 에
/// 적어 둔 판 번호와 받는 주소를 읽어, 낡은 판이면 그 자리에서 알린다.
///
///   · 최소 판보다 낮으면 → 건너뛸 수 없는 안내. 그 판으로는 쓰지 못한다.
///   · 최신 판보다 낮으면 → 「지금 받기 / 나중에」. 다음에 열 때 다시 묻는다.
///
/// 받기는 주소를 브라우저로 넘긴다. 내려받은 APK 를 눌러 깔면 된다 — 앱이 직접
/// 설치하려면 「출처를 알 수 없는 앱」 권한이 필요해서, 그 권한을 앱에 심지 않는
/// 쪽을 골랐다.
///
/// 서버가 답하지 않거나 값이 비어 있으면 **아무 말도 하지 않는다** — 안내를 못
/// 한다고 앱을 못 쓰게 만들 일은 아니다.
class UpdateService {
  static bool _running = false;

  /// 「나중에」를 누르면 앱이 켜져 있는 동안 다시 묻지 않는다.
  /// 최소 판 미만은 건너뛸 수 없으므로 이 값과 무관하다.
  static bool _snoozed = false;

  static Future<void> checkAndUpdate(BuildContext context) async {
    if (kIsWeb) return;
    if (_running) return;
    _running = true;

    try {
      final server = await _ask();
      if (server == null || !context.mounted) return;

      final info    = await PackageInfo.fromPlatform();
      final current = info.version;                  // 예: 1.3.3

      final min    = server['min']          as String?;
      final latest = server['latest']       as String?;
      final url    = server['download_url'] as String?;
      final notice = server['notice']       as String?;

      final blocked = min    != null && _older(current, min);
      final newer   = latest != null && _older(current, latest);

      if (!blocked && (!newer || _snoozed)) return;
      if (!context.mounted) return;

      await _show(
        context,
        current: current,
        target:  blocked ? min : latest!,
        url:     url,
        notice:  notice,
        blocked: blocked,
      );
    } catch (_) {
      // 못 물어봤으면 조용히 지나간다 — 다음에 앞으로 올 때 다시 묻는다
    } finally {
      _running = false;
    }
  }

  /// 서버에 묻는다. 로그인 앞이라 토큰을 싣지 않는다.
  static Future<Map<String, dynamic>?> _ask() async {
    final dio = Dio(BaseOptions(
      baseUrl:        AppConstants.baseUrl,
      connectTimeout: const Duration(seconds: 6),
      receiveTimeout: const Duration(seconds: 6),
    ));

    final res  = await dio.get('/app/version');
    final body = res.data;

    return body is Map<String, dynamic> ? body : null;
  }

  /// [current] 가 [other] 보다 낮은가. 1.3.3 / 1.3.10 처럼 마디로 견준다.
  static bool _older(String current, String other) {
    final a = _parts(current);
    final b = _parts(other);

    for (var i = 0; i < 3; i++) {
      if (a[i] != b[i]) return a[i] < b[i];
    }
    return false;
  }

  /// 1.3.3+10 처럼 빌드 번호가 붙어 와도 앞의 세 마디만 본다.
  static List<int> _parts(String v) {
    final nums = v.split('+').first.split('.');

    return List<int>.generate(
      3,
      (i) => i < nums.length ? (int.tryParse(nums[i].trim()) ?? 0) : 0,
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
      // 최소 판 미만은 물러날 자리가 없다
      barrierDismissible: !blocked,
      builder: (ctx) => PopScope(
        canPop: !blocked,
        child: AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Text(blocked ? '업데이트가 필요합니다' : '새 판이 있습니다',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                blocked
                    ? '지금 판($current)으로는 사용할 수 없습니다. $target 판으로 업데이트해 주십시오.'
                    : '$target 판이 나왔습니다. (지금 $current)',
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
            if (!blocked)
              TextButton(
                onPressed: () {
                  _snoozed = true;
                  Navigator.pop(ctx);
                },
                child: const Text('나중에'),
              ),
            if (url != null)
              FilledButton(
                onPressed: () async {
                  await launchUrl(Uri.parse(url),
                      mode: LaunchMode.externalApplication);
                  // 막힌 판은 창을 닫지 않는다 — 받아서 깔고 다시 열어야 한다
                  if (!blocked && ctx.mounted) Navigator.pop(ctx);
                },
                child: const Text('지금 받기'),
              ),
          ],
        ),
      ),
    );
  }
}
