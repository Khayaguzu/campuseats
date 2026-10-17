# CampusEats Android app, same website and REST API

> **Current course-project setup:** Use the HTTPS hosted URL in both Android `gradle.properties` settings, rebuild and sign in to the same server-backed account. The separately configured HTTPS **`school_checkout`** mode can confirm **no-charge coursework orders** for newly registered students and lecturers when explicitly enabled in the private server configuration. It is not a financial transaction. Only the older local `payments.demo` mode is loopback-restricted. For role rules, hosted installation, detailed customer instructions and GitHub Actions APK download, read the [main README](../../README.md).


**Open this `Android_App` directory directly in Android Studio**, using **File > Open**. Do not open `campuseats` or `CampusEats_Website_Android_App_Separated` as the Android Gradle project root. This Kotlin application is a secure WebView container for the existing responsive CampusEats website, **not a separately implemented Android shopping interface**. Both website and app use the exact same JavaScript and `Website/web/api.php` endpoint, and the PHP server uses one MySQL database.

## Connection to the hosted CampusEats website

The shared production website and REST API are hosted at **https://khayaguzu.helioho.st/**. The hosted PHP backend connects to the MariaDB database created in HelioHost Plesk; the Android app never receives database credentials or connects directly to MariaDB.

1. Verify `https://khayaguzu.helioho.st/` loads the menu and `https://khayaguzu.helioho.st/api.php?action=bootstrap` responds with JSON. Never publish a session CSRF token, private login data or database credentials from the response.
2. In `gradle.properties`, verify `CAMPUS_BASE_URL` and `CAMPUS_SSO_URL` are both set to `https://khayaguzu.helioho.st/`. They must remain on the same trusted HTTPS host.
3. Open **this Android_App folder** in Android Studio. Use a JDK compatible with the project's Gradle 8.9 wrapper (JDK 17), sync Gradle, then **rebuild and reinstall** the Android app.
4. Run the installed CampusEats app on the emulator or a physical Android device with internet access. It should display the same menu and API-backed account as the browser. Login cookies are separate, so sign in separately inside the app. Changes to the saved account, basket, orders and menu persist centrally on the server.
5. If the app cannot connect, first open the same HTTPS website in **Chrome inside the emulator**. Check for HTTP or TLS errors before changing code. The debug error panel can open the configured website in the browser.

**Optional local testing with WampServer:** For emulator-only development, temporarily set both URLs in `gradle.properties` to `http://10.0.2.2/campuseats/CampusEats_Website_Android_App_Separated/Website/web/` and rebuild. The special `10.0.2.2` address points to the Windows host only from the emulator; it is not the live hosting address and will not work on a physical phone. Restore HTTPS before preparing a release build.

**Payment and SSO:** Genuine PayFast payment is disabled unless properly configured and tested. The hosted school-project checkout is a separate, explicitly opted-in **no-charge** flow controlled by private `payments.school_checkout` settings. Do not enable unrestricted course checkout for real sales. The localhost-only `payments.demo` setting is distinct and must not be opened to remote clients. Google SSO remains unavailable until the server owner configures it.

## REST API and database parity

Both website and Android app render `Website/web/index.php` and load `Website/web/assets/app.js`. The browser's requests use `fetch('api.php?action=...')` on that server. Authentication cookies, student/lecturer/admin roles, discounts, basket, account settings, food catalogue, order number and payment confirmation all run on the PHP backend. The server is authoritative and uses MySQL via PDO. The Android application never reads MySQL directly or stores a separate copy of account data.

The account and basket are shared between clients when the same account signs in to both. Browser and WebView maintain **separate login cookies**, so logging in on the website does not automatically sign the user into the app. Saved account settings and orders appear in both once authenticated. This behavior is normal and protects session isolation.

The **localhost developer simulation** (`payments.demo`) is restricted to loopback requests by PHP server-side checks. For coursework on the HTTPS host, the different `payments.school_checkout` setting permits **explicitly authorised no-charge** checkout for signed-in users without collecting funds. For real-money testing, configure and validate a PayFast sandbox instead. Do **not** weaken the localhost security check or present simulated confirmation as a real payment.

## Build

The project includes Gradle wrapper 8.9, Android Gradle Plugin 8.7.3, Kotlin 2.0.21, JDK 17-compatible bytecode and Android SDK 35. Open `Android_App` in Android Studio, allow Gradle Sync, select an emulator and press Run. A normal debug build permits local HTTP. Release builds require HTTPS.

To check the shared API contract without running Android:

```powershell
cd C:\wamp64\www\campuseats\CampusEats_Website_Android_App_Separated\Website
python tests\shared_client_contract.py
```

To exercise the actual API and shared database, run the existing `Website/tests/integration.py` on an **isolated test database**, following its instructions. It writes records and must never be run against your real campus database. The prebuilt APK in the `apk` folder does not contain later source changes; rebuild from Android Studio.
