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
