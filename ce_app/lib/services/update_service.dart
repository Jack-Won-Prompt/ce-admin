// lib/services/update_service.dart

import 'package:flutter/foundation.dart';
import 'package:in_app_update/in_app_update.dart';

/// Google Play 인앱 업데이트 (강제 + 상시).
/// 스토어에서 설치된 앱에서만 동작하며, 디버그/스토어 외 설치/iOS 에서는 조용히 무시.
///
/// - 새 버전이 있으면 **즉시(전체화면) 업데이트를 강제**한다(구버전 사용 차단).
/// - 앱 시작뿐 아니라 **포그라운드 복귀(resume)마다** 재확인한다.
class UpdateService {
  static bool _running = false;

  /// 새 버전 확인 → 있으면 즉시 업데이트 강제. 재호출 가능(중복 실행만 방지).
  static Future<void> checkAndUpdate() async {
    // Play In-App Update 는 Android 전용
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) return;
    if (_running) return;
    _running = true;

    try {
      final info = await InAppUpdate.checkForUpdate();

      // 이미 즉시 업데이트가 진행 중이던 상태로 재진입 → 그대로 재개
      if (info.updateAvailability ==
          UpdateAvailability.developerTriggeredUpdateInProgress) {
        await InAppUpdate.performImmediateUpdate();
        return;
      }

      if (info.updateAvailability != UpdateAvailability.updateAvailable) return;

      // 강제: 즉시 업데이트 우선. Play 가 즉시 업데이트를 허용하면 전체화면 강제.
      if (info.immediateUpdateAllowed) {
        await InAppUpdate.performImmediateUpdate();
      } else if (info.flexibleUpdateAllowed) {
        // 즉시가 불가한 환경에서는 유연 업데이트로 폴백(다운로드 후 즉시 적용)
        await InAppUpdate.startFlexibleUpdate();
        await InAppUpdate.completeFlexibleUpdate();
      }
    } catch (_) {
      // 스토어 외 설치·네트워크 오류·미지원 환경·사용자 취소 등은 무시.
      // (다음 resume 때 다시 시도된다)
    } finally {
      _running = false;
    }
  }
}
