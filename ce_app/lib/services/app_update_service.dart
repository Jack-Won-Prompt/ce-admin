// lib/services/app_update_service.dart
// 새 판이 나왔는지 서버에 묻고, 지금 판과 견준다.

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'api_client.dart';

/// 서버가 말하는 판 사정.
class AppUpdateInfo {
  final String? latest;     // 스토어에 올라간 판
  final String? min;        // 이 아래로는 쓸 수 없는 판
  final String? storeUrl;
  final String? notice;

  const AppUpdateInfo({this.latest, this.min, this.storeUrl, this.notice});
}

/// 지금 판을 어떻게 해야 하는가.
enum UpdateVerdict {
  none,      // 그대로 써도 된다
  optional,  // 새 판이 있다 — 넘길 수 있다
  required,  // 이 판으로는 쓸 수 없다 — 넘길 수 없다
}

class AppUpdateService {
  final Dio _dio;
  AppUpdateService(this._dio);

  /// 지금 기기에 깔린 판(pubspec 의 version 앞자리).
  Future<String> currentVersion() async =>
      (await PackageInfo.fromPlatform()).version;

  /// 서버에 묻는다. 못 물어보면 null — 판 확인 때문에 앱을 멈추지는 않는다.
  Future<AppUpdateInfo?> fetch() async {
    try {
      final res = await _dio.get(
        '/app/version',
        options: Options(
          // 로그인 밖의 자리다. 토큰이 없거나 만료돼도 물어볼 수 있어야 한다.
          headers: {'Authorization': null},
          receiveTimeout: const Duration(seconds: 5),
          sendTimeout: const Duration(seconds: 5),
        ),
      );
      final d = res.data;
      if (d is! Map) return null;

      return AppUpdateInfo(
        latest:   d['latest_version']?.toString(),
        min:      d['min_version']?.toString(),
        storeUrl: d['store_url']?.toString(),
        notice:   d['notice']?.toString(),
      );
    } catch (_) {
      return null;
    }
  }

  /// 지금 판이 어디에 서 있는지 가린다. 최소 판을 먼저 본다 — 둘 다 걸리면
  /// 넘길 수 없는 쪽이 이긴다.
  static UpdateVerdict verdictFor(String current, AppUpdateInfo info) {
    if (isOlder(current, info.min))    return UpdateVerdict.required;
    if (isOlder(current, info.latest)) return UpdateVerdict.optional;
    return UpdateVerdict.none;
  }

  /// a 가 b 보다 낮은 판인가. 「1.2.3」처럼 점으로 나뉜 숫자만 본다.
  /// 자릿수가 다르면 없는 자리는 0 으로 친다(1.2 < 1.2.1).
  /// 어느 한쪽이라도 읽을 수 없으면 false — 알 수 없는 값 때문에 사용자를
  /// 스토어로 몰지 않는다.
  static bool isOlder(String? a, String? b) {
    final x = _parse(a);
    final y = _parse(b);
    if (x == null || y == null) return false;

    for (var i = 0; i < 3; i++) {
      final l = i < x.length ? x[i] : 0;
      final r = i < y.length ? y[i] : 0;
      if (l != r) return l < r;
    }
    return false;
  }

  static List<int>? _parse(String? v) {
    if (v == null) return null;
    final t = v.trim();
    if (t.isEmpty) return null;

    // 「1.1.0+2」처럼 빌드 번호가 붙어 와도 앞자리만 본다
    final core = t.split('+').first;
    if (!RegExp(r'^\d+(\.\d+)*$').hasMatch(core)) return null;

    return core.split('.').map(int.parse).toList();
  }
}

final appUpdateServiceProvider = Provider<AppUpdateService>(
  (ref) => AppUpdateService(ref.read(dioProvider)),
);
