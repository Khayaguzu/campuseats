# CampusEats, website and Android app

The existing campus food ordering software is separated into two development folders. Both use the same PHP API and MySQL database.

- **Website/**: PHP website, API, database schema and seed data, images, admin bootstrap and integration tests. Copy the **contents** of this folder to `C:\wamp64\www\campuseats`. Import the SQL files and create `server/config.php` from the example.
- **Android_App/**: Android Studio Kotlin application, its Gradle project and the prebuilt original emulator APK. Open this folder in Android Studio, configure the server address in `gradle.properties`, and rebuild to include source fixes.

The website entry point is `http://localhost/campuseats/web/`. The Android emulator should use `http://10.0.2.2/campuseats/web/` to reach the same Windows computer. Physical devices need your computer's reachable LAN IP or an HTTPS host.

**Read [Website/README.md](Website/README.md) for complete WampServer, Android, database and Google sign-in instructions.** The app intentionally displays the same interface as the website using Android WebView.

No credentials are committed. Google sign-in requires owner-supplied Google OAuth configuration. The provided APK was built before these source fixes and should be rebuilt for an updated installation.

## Legacy WampServer local PHP compatibility

The PHP source has been adapted for PHP **7.2 to 7.4** syntax and runtime functions, so a local upgrade is not required if one of those versions is installed and selected in Apache. PHP 5.6 is not supported. For public hosting, upgrade to a currently supported PHP version because PHP 7.x has reached end of security support. See `Website/README.md` for the nested-folder URL used when cloning this repository directly inside WampServer.

## Pay-before-collection update

The original website and Kotlin app remain in separate folders. After backing up an existing `campuseats` database, import `Website/database/payment_migration.sql` using phpMyAdmin and add the `payments` block from `Website/server/config.example.php` to your private `server/config.php`. Detailed steps and gateway requirements are in `Website/README.md`. No merchant credentials are included.


## Payment correction for existing Windows WampServer installations

Manual EFT approval has been removed from new checkout. Genuine online payments are verified automatically by PayFast. A clearly-labelled **local simulated payment** option is available for localhost testing only, disabled by default and requiring no payment credentials. The paid collection number appears in My orders after verification. For the Administration error, back up the existing MySQL database and import `Website/database/payment_migration.sql` on the same MySQL instance (3308 in this installation). Do not replace private `server/config.php`; add only the `payments` configuration. Read `Website/README.md` for full setup steps and safeguards.


## Checkout account verification and payment interface

The payment dialog uses the same design and account session as the original site. Customers must provide their CampusEats email and password again to confirm a local simulated transaction, or before receiving a PayFast redirect. The backend verifies both. Local simulations are prominently disclosed as **not charging actual money** on both the payment screen and receipt. No card or bank-login credentials are collected.
