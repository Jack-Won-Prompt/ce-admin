import java.util.Properties
import java.io.FileInputStream

/* 서명 열쇠는 저장소에 두지 않는다 — android/key.properties 와 .jks 는 .gitignore 가
   막는다. 잃어버리면 스토어에 올린 앱을 다시 고칠 수 없으므로 따로 보관해야 한다.
   파일이 없으면(내려받기만 한 PC) 디버그 열쇠로 물러난다 — 그래야 그 자리에서도
   `flutter run --release` 가 돈다. */
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.ceadmin.ce_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = "28.2.13676358"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.coloplast.ceadmin"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    /* 운영판과 개발판을 따로 찍는다 (2026-09-21 지시).

       소스는 하나다. 갈리는 것은 여기 설정뿐이다 — 개발판은 applicationId 뒤에
       .dev 가 붙어 다른 앱으로 깔리므로 한 폰에 둘을 같이 두고 오갈 수 있다.
       이름도 「CE Admin 개발」로 나와 눈으로 구분된다.

       SSO 가 되돌아오는 주소(scheme)도 갈라야 한다. 둘 다 ceadmin:// 을 받으면
       안드로이드가 누가 받을지 정하지 못해, 엉뚱한 판이 로그인을 가로챈다.

       찍는 법:
         flutter build appbundle --flavor prod --dart-define=API_BASE_URL=https://{운영}/api
         flutter build apk       --flavor dev  --dart-define=API_BASE_URL=https://www.ceadmin.co.kr/api \
                                               --dart-define=SSO_SCHEME=ceadmin-dev

       개발판을 처음 찍기 전에 Firebase 콘솔에서 안드로이드 앱
       com.coloplast.ceadmin.dev 를 같은 프로젝트에 더하고 google-services.json 을
       새로 내려받아야 한다. 없으면 빌드가 「No matching client found」로 선다. */
    flavorDimensions += "server"

    productFlavors {
        create("prod") {
            dimension = "server"
            resValue("string", "app_name", "CE Admin")
            manifestPlaceholders["ssoScheme"] = "ceadmin"
        }
        create("dev") {
            dimension = "server"
            applicationIdSuffix = ".dev"
            versionNameSuffix   = "-dev"
            resValue("string", "app_name", "CE Admin 개발")
            manifestPlaceholders["ssoScheme"] = "ceadmin-dev"
        }
    }

    signingConfigs {
        create("release") {
            keyAlias      = keystoreProperties["keyAlias"] as String?
            keyPassword   = keystoreProperties["keyPassword"] as String?
            storePassword = keystoreProperties["storePassword"] as String?
            storeFile     = (keystoreProperties["storeFile"] as String?)?.let { file(it) }
        }
    }

    buildTypes {
        release {
            signingConfig = if (keystorePropertiesFile.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}

flutter {
    source = "../.."
}
