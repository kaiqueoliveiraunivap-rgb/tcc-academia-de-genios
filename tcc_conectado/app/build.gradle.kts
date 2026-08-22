import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
}

// A chave da Gemini nunca fica no código-fonte: vem do local.properties (que o
// .gitignore não versiona) ou da variável de ambiente GEMINI_API_KEY, usada no CI.
val geminiApiKey: String = run {
    val arquivoLocal = rootProject.file("local.properties")
    val doArquivo = if (arquivoLocal.exists()) {
        Properties().apply { arquivoLocal.inputStream().use { load(it) } }
            .getProperty("GEMINI_API_KEY")
    } else {
        null
    }
    doArquivo ?: System.getenv("GEMINI_API_KEY") ?: ""
}

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

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        buildConfigField("String", "GEMINI_API_KEY", "\"$geminiApiKey\"")
    }

    buildFeatures {
        buildConfig = true
    }

    buildTypes {
        release {
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