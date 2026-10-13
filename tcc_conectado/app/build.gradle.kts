import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
}

val localSettings = Properties().apply {
    val file = rootProject.file("local.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}
val apiBaseUrl = localSettings.getProperty("API_BASE_URL", "http://10.0.2.2:8088/php_appest/")
require(apiBaseUrl.matches(Regex("https?://[a-zA-Z0-9.:-]+/[a-zA-Z0-9_/-]*"))) { "API_BASE_URL inválida" }

android {
    namespace = "com.github.carloseduardofsousa.tcc"
    compileSdk {
        version = release(36) {
            minorApiLevel = 1
        }
    }

    defaultConfig {
        applicationId = "com.github.carloseduardofsousa.tcc"
        minSdk = 24
        targetSdk = 36
        versionCode = 1
        versionName = "1.0"
        buildConfigField("String", "API_BASE_URL", "\"$apiBaseUrl\"")

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"


    }

    buildFeatures {
        buildConfig = true
    }

    buildTypes {
        debug {
            // Mantém o XAMPP/10.0.2.2 acessível apenas no desenvolvimento local.
            manifestPlaceholders["cleartextTraffic"] = "true"
        }
        release {
            // A URL de produção deve ser HTTPS; o APK de release não aceita HTTP.
            manifestPlaceholders["cleartextTraffic"] = "false"
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.material)
    implementation(libs.androidx.activity)
    implementation(libs.androidx.constraintlayout)
    implementation(libs.androidx.recyclerview)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.kotlinx.coroutines.android)
    implementation(libs.mpandroidchart)
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
}

val iniciarApiLocal = System.getProperty("os.name").startsWith("Windows") &&
    apiBaseUrl == "http://10.0.2.2:8088/php_appest/" &&
    providers.gradleProperty("startLocalApi").orNull == "true"
val scriptApiLocal = rootProject.file("../iniciar-local.ps1").absolutePath

tasks.register<Exec>("prepararAmbienteLocal") {
    group = "development"
    description = "Inicia a API e o MySQL locais somente com -PstartLocalApi=true. Pode executar o instalador."
    enabled = iniciarApiLocal
    commandLine("powershell.exe", "-NoProfile", "-ExecutionPolicy", "Bypass", "-File",
        scriptApiLocal, "-Rapido")
}

if (iniciarApiLocal) {
    tasks.matching { it.name == "preDebugBuild" }.configureEach {
        dependsOn("prepararAmbienteLocal")
    }
}
