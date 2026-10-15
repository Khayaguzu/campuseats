plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}
val campusBase = providers.gradleProperty("CAMPUS_BASE_URL").get()
val campusSso = providers.gradleProperty("CAMPUS_SSO_URL").get()
android(
{
    namespace = "za.ac.campuseats"
    compileSdk = 35
    defaultConfig(
    {
        applicationId = "za.ac.campuseats"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
        buildConfigField("String", "BASE_URL", "\"${campusBase}\"")
        buildConfigField("String", "SSO_URL", "\"${campusSso}\"")
    })
    buildFeatures(
    {
        buildConfig = true
    })
    compileOptions(
    {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    })
    kotlinOptions(
    {
        jvmTarget = "17"
    })
    buildTypes(
    {
        release(
        {
            isMinifyEnabled = false
        })
    })
})
