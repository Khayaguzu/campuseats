# CampusEats, website and Android app

The existing campus food ordering software is separated into two development folders. Both use the same PHP API and MySQL database.

- **Website/**: PHP website, API, database schema and seed data, images, admin bootstrap and integration tests. Copy the **contents** of this folder to `C:\wamp64\www\campuseats`. Import the SQL files and create `server/config.php` from the example.
- **Android_App/**: Android Studio Kotlin application, its Gradle project and the prebuilt original emulator APK. Open this folder in Android Studio, configure the server address in `gradle.properties`, and rebuild to include source fixes.

The website entry point is `http://localhost/campuseats/web/`. The Android emulator should use `http://10.0.2.2/campuseats/web/` to reach the same Windows computer. Physical devices need your computer's reachable LAN IP or an HTTPS host.

**Read [Website/README.md](Website/README.md) for complete WampServer, Android, database and Google sign-in instructions.** The app intentionally displays the same interface as the website using Android WebView.

No credentials are committed. Google sign-in requires owner-supplied Google OAuth configuration. The provided APK was built before these source fixes and should be rebuilt for an updated installation.
