// 판 견주기 — 잘못 세면 멀쩡한 사람을 스토어로 몰거나, 못 쓰는 판을 그냥 둔다.

import 'package:flutter_test/flutter_test.dart';
import 'package:ce_app/services/app_update_service.dart';

void main() {
  group('isOlder', () {
    test('자릿수가 같을 때', () {
      expect(AppUpdateService.isOlder('1.0.0', '1.1.0'), isTrue);
      expect(AppUpdateService.isOlder('1.1.0', '1.1.0'), isFalse);
      expect(AppUpdateService.isOlder('1.2.0', '1.1.9'), isFalse);
      expect(AppUpdateService.isOlder('1.1.9', '1.2.0'), isTrue);
    });

    test('자릿수가 다르면 없는 자리는 0 으로 친다', () {
      expect(AppUpdateService.isOlder('1.2', '1.2.1'), isTrue);
      expect(AppUpdateService.isOlder('1.2.0', '1.2'), isFalse);
      expect(AppUpdateService.isOlder('2', '1.9.9'), isFalse);
    });

    test('빌드 번호가 붙어 와도 앞자리만 본다', () {
      expect(AppUpdateService.isOlder('1.1.0+2', '1.1.0'), isFalse);
      expect(AppUpdateService.isOlder('1.0.0+9', '1.1.0+1'), isTrue);
    });

    test('읽을 수 없으면 낮다고 하지 않는다 — 스토어로 몰지 않기 위해', () {
      expect(AppUpdateService.isOlder('1.0.0', null), isFalse);
      expect(AppUpdateService.isOlder('1.0.0', ''), isFalse);
      expect(AppUpdateService.isOlder('1.0.0', '알 수 없음'), isFalse);
      expect(AppUpdateService.isOlder('나쁜 값', '1.0.0'), isFalse);
    });
  });

  group('verdictFor', () {
    test('최소 판에 걸리면 넘길 수 없다 — 둘 다 걸려도 이쪽이 이긴다', () {
      const info = AppUpdateInfo(latest: '1.2.0', min: '1.1.0');
      expect(AppUpdateService.verdictFor('1.0.0', info), UpdateVerdict.required);
    });

    test('최신 판보다만 낮으면 넘길 수 있다', () {
      const info = AppUpdateInfo(latest: '1.2.0', min: '1.1.0');
      expect(AppUpdateService.verdictFor('1.1.0', info), UpdateVerdict.optional);
    });

    test('최신 판이면 아무것도 묻지 않는다', () {
      const info = AppUpdateInfo(latest: '1.2.0', min: '1.1.0');
      expect(AppUpdateService.verdictFor('1.2.0', info), UpdateVerdict.none);
    });

    test('서버가 비워 두면 아무것도 묻지 않는다', () {
      const info = AppUpdateInfo();
      expect(AppUpdateService.verdictFor('1.0.0', info), UpdateVerdict.none);
    });
  });
}
