# CampusEats

A PHP/MySQL campus food ordering website and an Android Studio Kotlin application, adapted from the supplied Rosebank HTML prototype.

## What is included

- A responsive interface shared by the website and Android app.
- 32 sample foods with selling prices in ZAR, stock quantities, allergen descriptions and locally bundled illustrative photographs.
- The four original vendors: The Grill Spot, Pizza Piazza, Fresh & Green and Coffee Corner.
- Student, lecturer and administrator registration. Administrator registration creates an approval request, not immediate administrative privileges.
- A 10% discount on food for verified students. An existing administrator verifies the student number before the discount starts. Lecturer accounts pay normal prices.
- Database-backed baskets that follow the same account between devices.
- Checkout with collection time, order notes, payment on collection and a saved receipt/order history.
- Stock reservation, cancellation with stock restoration and order status tracking.
- Administrator screens for food creation/editing/hiding, prices, quantities, vendor details/open status, role approval and order fulfilment.
- Shared profile, phone, theme and in-app notification preferences, plus password changes.
- Google single sign-on integration, including a system-browser flow for Android.
- PHP JSON API, MySQL schema/seed, integration checks and Kotlin project with Gradle wrapper.

**Architecture:** The Android application is a Kotlin WebView application, not a separately implemented native Compose interface. It renders the same responsive PHP-backed web interface and uses the same JSON API. This deliberately keeps all ordering, settings and administration features identical. Both clients require access to the server. Neither the Android app nor JavaScript connects directly to MySQL.

**Catalogue:** Prices, quantities, collection locations and food descriptions are editable sample data, not confirmed information about actual campus vendors. Food photos illustrate categories and may not match a specific item. Replace these and confirm ingredients with the real vendors before accepting real orders. The college logo is retained from the supplied project; no official affiliation is asserted.

## 1. Start the website in WampServer

Requirements: WampServer with Apache, PHP **8.2 or newer**, and MySQL 8 or MariaDB 10.6 or newer. Enable PHP extensions `pdo_mysql`, `curl` and `openssl` through the WampServer PHP extensions menu. No Composer or Node installation is needed for the website.

1. Extract this archive. Copy the contents of `CampusEats` to `C:\wamp64\www\campuseats`. The entry point should be `C:\wamp64\www\campuseats\web\index.php`.
2. Start WampServer and wait for its icon to turn green.
3. Open `http://localhost/phpmyadmin`. Import `database/schema.sql` first, then `database/seed.sql`. Import each once into a clean database. The scripts create and select `campuseats`.
4. Copy `server/config.example.php` to `server/config.php`.
5. Edit `config.php` with your MySQL host, port, username and password. WampServer may run MySQL and MariaDB on different ports, so use the port shown for the database you imported into. The empty root password in the example is only a common local WampServer default; use your actual credentials.
6. Keep `base_url` as `http://localhost/campuseats/web` for initial browser testing.
7. Create the first administrator from a Windows terminal. Replace the PHP version folder below with the folder actually installed on your computer. Choose your own strong password.

```bat
cd C:\wamp64\www\campuseats
C:\wamp64\bin\php\php8.3.0\php.exe server\create-admin.php admin@example.com "Campus Administrator" "ChooseYourOwnLongPassword!"
```

8. Open **http://localhost/campuseats/web/** and sign in with that administrator account.
9. Register a separate student account with its student number. Sign in as the administrator, open **Administration → Users**, verify the number against your campus records, then approve it.
10. The student now receives the 10% discount. For example, two R45 burgers cost R90 before discount and **R81 after discount**.

There are no bundled public administrator credentials. The first administrator can approve later administrator requests. The administrator creation script only runs through PHP CLI, not through a browser.

### Windows setup assistance

If `php.exe` cannot be found, open `C:\wamp64\bin\php`, note the installed version directory, and substitute it into the command. If phpMyAdmin uses MariaDB port 3307, set `db_port` to `3307`. If WampServer Apache is on port 8080, use `http://localhost:8080/campuseats/web/` and update both app URLs accordingly.

Only `web/` should be publicly served in a production virtual host. Directory-level `.htaccess` files deny access to source/configuration folders for local WampServer installs. Do not disable those protections. Never put the SQL scripts or `config.php` inside `web/`.

## 2. Open and run the Android app

A successfully built debug APK is included at **`apk/CampusEats-debug.apk`**. It targets the standard Android emulator's host address (`10.0.2.2`) with WampServer at `/campuseats/web/`. To use a real phone or a different server address, change the properties below and rebuild. The APK has been built successfully but has not been run on an Android device here.


1. Install Android Studio with the Android SDK and use its JDK 17-compatible Gradle runtime.
2. Choose **Open**, then select the `android` directory in this package, not the whole CampusEats directory.
3. Let Gradle sync. The project includes a Gradle 8.9 wrapper, Android Gradle Plugin 8.7.3 and Kotlin 2.0.21. Install Android SDK Platform 35 and Build Tools when prompted.
4. Open `android/gradle.properties`.
5. For the standard Android emulator running on the same computer as WampServer, retain:

```properties
CAMPUS_BASE_URL=http://10.0.2.2/campuseats/web/
CAMPUS_SSO_URL=http://10.0.2.2/campuseats/web/
```

6. Keep WampServer running. Start an Android emulator with API 26 or newer and press **Run**. Update Android System WebView if the emulator uses an old version.
7. Sign in with the same credentials as the website. Your basket, orders and settings come from the same database.

`10.0.2.2` is the emulator's route to the host computer. `localhost` inside the emulator refers to the emulator itself, not your computer.

### Physical Android phone

Put the phone and computer on the same trusted Wi-Fi. Use `ipconfig` on Windows to find the computer's LAN IPv4 address, for example `192.168.1.20`. Set both Gradle URL properties to `http://192.168.1.20/campuseats/web/` and rebuild. Permit Apache through Windows Firewall on your private network, and configure Apache to allow your specific LAN subnet to access `web/`. Do not expose phpMyAdmin or the MySQL port to the phone. Test the URL in the phone browser first.

Local HTTP is enabled **only in the debug variant**. A release build blocks cleartext traffic and needs an HTTPS server URL. Build a debug APK using Android Studio's APK build action or:

```bat
gradlew.bat assembleDebug
```

The APK output is `android/app/build/outputs/apk/debug/app-debug.apk`. For distribution, configure your own signing key using Android Studio's signed APK flow. An unsigned project is not a published Play Store application.

## 3. Configure Google single sign-on

Email/password accounts work without Google setup. The Google button stays hidden until `google_client_id` is configured.

1. Create a Google Cloud project and configure its OAuth consent screen. While it is in testing mode, add your intended test accounts.
2. Create an OAuth client of type **Web application**. This is a server-side OAuth flow, including for the Android client.
3. For browser-only localhost development, register this exact authorised redirect URI:

```text
http://localhost/campuseats/web/sso.php?mode=callback
```

4. Put the client ID and secret in `server/config.php`. Set `base_url` to the exact corresponding server address without a trailing slash. The secret stays on the PHP server, never in the Android project.
5. For Android SSO, use a trusted HTTPS domain that both the system browser and the application can reach. Configure its exact callback URI in Google Cloud, update `base_url`, set `secure_cookies` to `true`, and set both Android URL properties to that same HTTPS server (with a trailing slash in the Android properties). Rebuild the app. Google OAuth redirect restrictions make a raw LAN IP or the emulator address unsuitable as the registered public callback.
6. If Windows PHP reports a certificate error, configure `curl.cainfo` in PHP's `php.ini` to a current trusted CA bundle and restart Apache. Do not disable TLS verification.

**Identity and roles:** Google verifies the identity/email, not whether the person is a student or administrator. New SSO accounts start without elevated access. Open **My account → Your campus role** to request Student, Lecturer or Administrator status. Students must provide a student number and receive administrator verification for the discount.

**Existing email/password accounts:** They are deliberately not linked to Google merely because an email matches. Sign into the existing account in a normal browser, select **My account → Link Google identity**, and authenticate with the same email. For Android, complete this initial linking in the system browser, then return to the app and use Google sign-in. Thereafter the Google identity maps to the same account on both platforms.

**Android return:** A `campuseats://auth` deep link carries a two-minute, single-use ticket. The app proves possession of the verifier created when sign-in started. The server consumes the ticket and establishes the WebView session. Provider tokens and client secrets are not stored in the app.

Google credentials, provider consent and the callback domain are owner-controlled configuration, so a live provider sign-in must be tested after setup.

## 4. Everyday use

### Students and lecturers

Browse or search the menu, choose a vendor/category, add items and open the basket. Select a collection time between 5 minutes and 24 hours ahead, add optional notes and place the order. Order collection times are shown in South African time in the order history. Collect from the vendor locations listed with each item and pay at collection.

A placed order can be cancelled before preparation starts. Cancellation restores its stock. Changes in stock, prices or vendor availability are checked again at checkout. If checkout fails after a connection interruption, retry without refreshing; the request identifier prevents a duplicate order.

### Administrators

- **Orders:** Move orders through Placed → Preparing → Ready → Collected. The final action confirms that payment was received. You can cancel placed/preparing orders and return their stock.
- **Products:** Add items, change selling prices, restock quantities, choose images or hide an item. Hiding preserves historical order records.
- **Users:** Verify student numbers and approve valid account-role requests. An administrator request grants powerful permissions, so confirm the person's authority before approval.
- **Vendors:** Edit names and collection locations or stop accepting orders from a kitchen.

Add additional photographs as `.jpg` files under `web/assets/food/`, then select the filename in the product editor. This version supports selecting server-managed images; it does not expose a public image upload endpoint.

Profile and appearance settings are stored centrally. Saved changes appear in another open client after refresh. Order notifications are in-app messages polled every 15 seconds while the application is visible, not SMS, email or background push notifications.

## 5. Files and architecture

| Path | Purpose |
| --- | --- |
| `web/index.php` | Shared responsive interface |
| `web/api.php` | JSON endpoints and role checks |
| `web/sso.php` | Google login, callback and Android ticket redemption |
| `web/assets/` | Styles, JavaScript, logo and locally bundled food photographs |
| `server/bootstrap.php` | PDO, sessions, CSRF, validation and rate limits |
| `server/config.example.php` | Configuration template |
| `server/create-admin.php` | CLI-only first administrator creation |
| `database/schema.sql` | Tables, indexes and foreign keys |
| `database/seed.sql` | Four vendors and 32 sample food items |
| `android/` | Android Studio Kotlin project |
| `tests/integration.py` | Real-server integration checks |
| `docs/API.md` | API contract |
| `docs/VALIDATION.md` | Checks performed and remaining validation |

## 6. Security and operational scope

Passwords use PHP password hashing. SQL uses prepared statements. Mutations require a session-bound CSRF token. Cookies are HttpOnly and SameSite=Lax; enable Secure in HTTPS deployments. Authorisation is enforced on the server, and administrator signup requires approval. Checkout uses transactions and row locks, prices are recalculated on the server, and amounts use integer cents. Administrative changes are audited.

Before real use, replace the sample catalogue and photos, use a restricted database account, enable HTTPS, configure backups, remove development/test files from the public document root and validate the complete workflow on your own WampServer and Android devices. Automatic time-based vendor opening hours, card payments, delivery, password recovery email and background push notifications are not included. The implemented workflow is campus collection with payment on collection.

## Technical references used

- Android WebView: https://developer.android.com/develop/ui/views/layout/webapps/webview
- Android Gradle Plugin 8.7 compatibility: https://developer.android.com/build/releases/agp-8-7-0-release-notes
- Google server-side OAuth: https://developers.google.com/identity/protocols/oauth2/web-server
- Google userinfo endpoint: https://developers.google.com/identity/openid-connect/reference

See `docs/IMAGE-CREDITS.md` for the bundled illustrative photo sources.
