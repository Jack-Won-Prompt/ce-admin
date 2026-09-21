# ce_app

A new Flutter project.

## Development Environment

- Flutter: 3.47.4 (stable)
- Dart: 3.13.3

## 운영판·개발판 (2026-09-21)

소스는 하나다. 운영판과 개발판은 **빌드 설정(flavor)** 으로만 갈린다.

| | 운영판 `prod` | 개발판 `dev` |
|---|---|---|
| 앱 이름 | CE Admin | CE Admin 개발 |
| 꾸러미 이름 | `com.coloplast.ceadmin` | `com.coloplast.ceadmin.dev` |
| SSO 되돌아올 주소 | `ceadmin://sso` | `ceadmin-dev://sso` |
| 배포 | Play 스토어 | APK 직접 설치 |

꾸러미 이름이 달라 **한 폰에 둘을 같이 두고 오갈 수 있다.**

```bash
# 운영판 — 스토어에 올릴 것
flutter build appbundle --release --flavor prod \
  --dart-define=API_BASE_URL=https://www.ceadmin.co.kr/api

# 개발판 — 사내 시험용
flutter build apk --release --flavor dev \
  --dart-define=API_BASE_URL=https://www.ceadmin.co.kr/api \
  --dart-define=SSO_SCHEME=ceadmin-dev
```

`--flavor` 를 빼면 Gradle 이 어느 판인지 몰라 선다. `SSO_SCHEME` 은
`android/app/build.gradle.kts` 의 `ssoScheme` 과 **반드시 같아야 한다** —
다르면 로그인을 마치고도 앱으로 돌아오지 못한다.

서버는 `?cb=` 로 받은 값이 허용 목록(`EntraController::APP_SCHEMES`)에 있을 때만
그 주소로 돌려보낸다. 새 판을 만들면 그 목록에도 더해야 한다.

> **개발판을 처음 찍기 전에**: Firebase 콘솔에서 같은 프로젝트(`ceadmin-2e5a2`)에
> 안드로이드 앱 `com.coloplast.ceadmin.dev` 를 더하고 `google-services.json` 을
> 새로 내려받아 `android/app/` 에 덮어쓴다. 없으면 빌드가
> `No matching client found for package name` 으로 선다.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
