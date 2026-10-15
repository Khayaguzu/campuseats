# CampusEats Android app, same website and REST API

**Open this `Android_App` directory directly in Android Studio**, using **File > Open**. Do not open `campuseats` or `CampusEats_Website_Android_App_Separated` as the Android Gradle project root. This Kotlin application is a secure WebView container for the existing responsive CampusEats website, **not a separately implemented Android shopping interface**. Both website and app use the exact same JavaScript and `Website/web/api.php` endpoint, and the PHP server uses one MySQL database.

## Connection on the standard Android emulator

1. Start WampServer on Windows. Ensure `Website/server/config.php` on that computer points to the correct MySQL instance, for example port 3308, and uses the same website URL configured in PHP. Do not place credentials in the Android project.
2. On the Windows browser, check the website at `http://localhost/campuseats/CampusEats_Website_Android_App_Separated/Website/web/`.
3. **Inside the Android emulator's Chrome browser**, open `http://10.0.2.2/campuseats/CampusEats_Website_Android_App_Separated/Website/web/`. If this fails, the Android app cannot connect either. Check Apache port 80, Windows firewall, and Apache access permissions. HTTP 403 indicates access restrictions, 404 means the URL path is wrong, and 500/503 means the PHP backend needs attention.
4. In `gradle.properties`, both `CAMPUS_BASE_URL` and `CAMPUS_SSO_URL` must point to the **same exact** `http://10.0.2.2/.../Website/web/` path. Sync Gradle, **rebuild/reinstall the app** after edits, then run it.
5. The native error screen shows the configured server URL and the HTTP or network error, and has a button that opens the website in the emulator's browser. There is no fallback database or duplicated menu in the app.

## REST API and database parity

Both website and Android app render `Website/web/index.php` and load `Website/web/assets/app.js`. The browser's requests use `fetch('api.php?action=...')` on that server. Authentication cookies, student/lecturer/admin roles, discounts, basket, account settings, food catalogue, order number and payment confirmation all run on the PHP backend. The server is authoritative and uses MySQL via PDO. The Android application never reads MySQL directly or stores a separate copy of account data.

The account and basket are shared between clients when the same account signs in to both. Browser and WebView maintain **separate login cookies**, so logging in on the website does not automatically sign the user into the app. Saved account settings and orders appear in both once authenticated. This behavior is normal and protects session isolation.

The local-only simulated payment is restricted by PHP server-side checks to a safe loopback environment. If your Android emulator's requests do not satisfy that check, use website localhost testing or configure a public HTTPS PayFast sandbox for remote/device tests. Do **not** weaken the security check for network clients or present simulated payments as real transactions.

## Build

The project includes Gradle wrapper 8.9, Android Gradle Plugin 8.7.3, Kotlin 2.0.21, JDK 17-compatible bytecode and Android SDK 35. Open `Android_App` in Android Studio, allow Gradle Sync, select an emulator and press Run. A normal debug build permits local HTTP. Release builds require HTTPS.

To check the shared API contract without running Android:

```powershell
cd C:\wamp64\www\campuseats\CampusEats_Website_Android_App_Separated\Website
python tests\shared_client_contract.py
```

To exercise the actual API and shared database, run the existing `Website/tests/integration.py` on an **isolated test database**, following its instructions. It writes records and must never be run against your real campus database. The prebuilt APK in the `apk` folder does not contain later source changes; rebuild from Android Studio.
