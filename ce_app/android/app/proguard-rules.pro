# Flutter 기본 규칙
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# SLF4J — pusher_channels_flutter 의존성 (R8 경고 억제)
-dontwarn org.slf4j.impl.StaticLoggerBinder

# Pusher
-keep class com.pusher.** { *; }
-dontwarn com.pusher.**

# OkHttp (Pusher 내부 사용)
-dontwarn okhttp3.**
-dontwarn okio.**

# Play Core — 플러터가 「나중에 받는 조각(deferred component)」을 부르는 자리에서만 쓴다.
# 이 앱은 그 기능을 쓰지 않아 라이브러리를 넣지 않았는데, R8 은 부르는 코드만 보고
# 클래스가 없다며 릴리스 빌드를 멈춘다. 쓰지 않는 길이므로 경고만 걷는다.
-dontwarn com.google.android.play.core.**
