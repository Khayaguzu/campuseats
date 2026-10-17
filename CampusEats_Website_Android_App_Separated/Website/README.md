# CampusEats

A PHP/MySQL campus food ordering website and an Android Studio Kotlin application, adapted from the supplied Rosebank HTML prototype.

## What is included

- A responsive interface shared by the website and Android app. The two folders are separated for development and deployment.
- 32 sample foods with selling prices in ZAR, stock quantities, allergen descriptions and locally bundled illustrative photographs.
- The four original vendors: The Grill Spot, Pizza Piazza, Fresh & Green and Coffee Corner.
- Student, lecturer and administrator registration. Administrator registration creates an approval request, not immediate administrative privileges.
- A 10% discount on food for verified students. An existing administrator verifies the student number before the discount starts. Lecturer accounts pay normal prices.
- Database-backed baskets that follow the same account between devices.
- Checkout with collection time, order notes and pay-before-collection via EFT verification or PayFast, with order history.
- Stock reservation, cancellation with stock restoration and order status tracking.
- Administrator screens for food creation/editing/hiding, prices, quantities, vendor details/open status, role approval and order fulfilment.
- Shared profile, phone, theme and in-app notification preferences, plus password changes.
- Google single sign-on integration, including a system-browser flow for Android.
- PHP JSON API, MySQL schema/seed, integration checks and Kotlin project with Gradle wrapper.

**Architecture:** The Android application is a Kotlin WebView application, not a separately implemented native Compose interface. It renders the same responsive PHP-backed web interface and uses the same JSON API. This deliberately keeps all ordering, settings and administration features identical. Both clients require access to the server. Neither the Android app nor JavaScript connects directly to MySQL.

**Catalogue:** Prices, quantities, collection locations and food descriptions are editable sample data, not confirmed information about actual campus vendors. Food photos illustrate categories and may not match a specific item. Replace these and confirm ingredients with the real vendors before accepting real orders. The college logo is retained from the supplied project; no official affiliation is asserted.

## 1. Start the website in WampServer

Requirements: WampServer with Apache, PHP **7.2 or newer** (including the existing WampServer PHP 7.2, 7.3 and 7.4 installations), and a compatible MySQL or MariaDB database. Enable PHP extensions `pdo_mysql`, `curl` and `openssl` through the WampServer PHP extensions menu. No Composer or Node installation is needed for the website. PHP 5.6 is not supported.

**Local development note:** PHP 7.2, 7.3 and 7.4 are end-of-life and must not be used for a publicly accessible production deployment. The compatibility changes below allow local WampServer testing without requiring a PHP upgrade; use a supported PHP release for public hosting. In WampServer, select an existing PHP 7.4 installation if it is available, otherwise PHP 7.3 or PHP 7.2.

1. Extract the archive. Copy the **contents of the `Website` folder**, not the enclosing project folder, into `C:\wamp64\www\campuseats`. The entry point must be `C:\wamp64\www\campuseats\web\index.php`. Keep the `Android_App` folder outside WampServer, for example in `Documents` or your Android Studio projects directory.
2. Start WampServer and wait for its icon to turn green.
3. Open `http://localhost/phpmyadmin`. Import `database/schema.sql` first, then `database/seed.sql`. Import each once into a clean database. The scripts create and select `campuseats`.
4. Copy `server/config.example.php` to `server/config.php`.
5. Edit `config.php` with your MySQL host, port, username and password. WampServer may run MySQL and MariaDB on different ports, so use the port shown for the database you imported into. The empty root password in the example is only a common local WampServer default; use your actual credentials.
6. Keep `base_url` as `http://localhost/campuseats/web` for initial browser testing.
7. Create the first administrator from a Windows terminal. Replace the PHP version folder below with the folder actually installed on your computer. Choose your own strong password.

```bat
cd C:\wamp64\www\campuseats
C:\wamp64\bin\php\php7.4.0\php.exe server\create-admin.php admin@example.com "Campus Administrator" "ChooseYourOwnLongPassword!"
```

8. Open **http://localhost/campuseats/web/** and sign in with that administrator account.
9. Register a separate student account with its student number. Sign in as the administrator, open **Administration → Users**, verify the number against your campus records, then approve it.
10. The student now receives the 10% discount. For example, two R45 burgers cost R90 before discount and **R81 after discount**.

There are no bundled public administrator credentials. The first administrator can approve later administrator requests. The administrator creation script only runs through PHP CLI, not through a browser.

### Windows setup assistance

If `php.exe` cannot be found, open `C:\wamp64\bin\php`, note the installed version directory, and substitute it into the command. If phpMyAdmin uses MariaDB port 3307, set `db_port` to `3307`. If WampServer Apache is on port 8080, use `http://localhost:8080/campuseats/web/` and update both app URLs accordingly.

Only `web/` should be publicly served in a production virtual host. Directory-level `.htaccess` files deny access to source/configuration folders for local WampServer installs. Do not disable those protections. Never put the SQL scripts or `config.php` inside `web/`.

## 2. Open and run the Android app

A successfully built debug APK is included at **`Android_App/apk/CampusEats-debug.apk`**. It targets the standard Android emulator's host address (`10.0.2.2`) with WampServer at `/campuseats/web/`. To use a real phone or a different server address, change the properties below and rebuild. The APK has been built successfully but has not been run on an Android device here.


1. Install Android Studio with the Android SDK and use its JDK 17-compatible Gradle runtime.
2. Choose **Open**, then select the `Android_App` directory in this package. Do not open the `Website` directory as an Android Studio project.
3. Let Gradle sync. The project includes a Gradle 8.9 wrapper, Android Gradle Plugin 8.7.3 and Kotlin 2.0.21. Install Android SDK Platform 35 and Build Tools when prompted.
4. Open `Android_App/gradle.properties`.
5. For the current nested Git checkout in WampServer, use the following Android emulator configuration. If you copied `Website` directly to `C:\wamp64\www\campuseats`, remove the nested path and use `/campuseats/web/` instead:

```properties
CAMPUS_BASE_URL=http://10.0.2.2/campuseats/CampusEats_Website_Android_App_Separated/Website/web/
CAMPUS_SSO_URL=http://10.0.2.2/campuseats/CampusEats_Website_Android_App_Separated/Website/web/
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

The APK output is `Android_App/app/build/outputs/apk/debug/app-debug.apk`. The prebuilt APK under `Android_App/apk/` reflects the original build, and must be rebuilt to include subsequent Kotlin fixes. For distribution, configure your own signing key using Android Studio's signed APK flow. An unsigned project is not a published Play Store application.

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

Browse or search the menu, choose a vendor/category, add items and open the basket. Select a collection time between 5 minutes and 24 hours ahead, add optional notes and place the order. Order collection times are shown in South African time in the order history. Pay first, wait until payment is verified, then collect using the activated order number.

An unpaid placed order can be cancelled before preparation starts. Cancellation restores its stock. Paid orders require a separate refund process. Changes in stock, prices or vendor availability are checked again at checkout. If checkout fails after a connection interruption, retry without refreshing; the request identifier prevents a duplicate order.

### Administrators

- **Orders:** Move orders through Placed → Preparing → Ready → Collected. Only paid orders can be prepared or handed over. The final action confirms that the customer collected the order. You can cancel placed/preparing orders and return their stock.
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
| `../Android_App/` | Android Studio Kotlin project, outside the WampServer website directory |
| `tests/integration.py` | Real-server integration checks |
| `docs/API.md` | API contract |
| `docs/VALIDATION.md` | Checks performed and remaining validation |

## 6. Security and operational scope

Passwords use PHP password hashing. SQL uses prepared statements. Mutations require a session-bound CSRF token. Cookies are HttpOnly and SameSite=Lax; enable Secure in HTTPS deployments. Authorisation is enforced on the server, and administrator signup requires approval. Checkout uses transactions and row locks, prices are recalculated on the server, and amounts use integer cents. Administrative changes are audited.

Before real use, replace the sample catalogue and photos, use a restricted database account, enable HTTPS, configure backups, remove development/test files from the public document root and validate the complete workflow on your own WampServer and Android devices. Automatic time-based vendor opening hours, built-in card storage, delivery, password recovery email and background push notifications are not included. The implemented workflow is campus collection with pay before collection.

## Technical references used

- Android WebView: https://developer.android.com/develop/ui/views/layout/webapps/webview
- Android Gradle Plugin 8.7 compatibility: https://developer.android.com/build/releases/agp-8-7-0-release-notes
- Google server-side OAuth: https://developers.google.com/identity/protocols/oauth2/web-server
- Google userinfo endpoint: https://developers.google.com/identity/openid-connect/reference

See `docs/IMAGE-CREDITS.md` for the bundled illustrative photo sources.

## 7. Troubleshooting the separated project

- If the website displays **Server setup required**, confirm `C:\wamp64\www\campuseats\server\config.php` exists, then import `Website/database/schema.sql` and `Website/database/seed.sql` into the same MySQL or MariaDB server configured in that file. These source paths refer to the extracted archive.
- If phpMyAdmin uses MariaDB on port `3307`, change `db_port` in `server/config.php` to match. Check that `pdo_mysql` is enabled in the PHP version Apache is using.
- If you see an HTTP 404 at `/campuseats/web/`, check that the entry point is directly under `C:\wamp64\www\campuseats\web\index.php`, not under an extra `Website` directory.
- If the Android app says it cannot connect, keep Apache running and open `http://10.0.2.2/campuseats/web/` in the **Android emulator browser**. For a phone, follow the physical-device section and use the computer's LAN IP, not `localhost` or `10.0.2.2`.
- If the Google sign-in option is unavailable, configure **both** `google_client_id` and `google_client_secret`, together with the exact OAuth callback. Email/password registration still works without Google setup.
- Run the website and Android app against the same PHP API and MySQL database. Never configure Android to connect directly to the database.

### Older WampServer PHP compatibility

The API no longer uses PHP 7.4 array unpacking, PHP 7.4 null-coalescing assignment, or PHP 8.1 `never` return types. It also avoids requiring the PHP 7.3 `JSON_THROW_ON_ERROR` constant and supports PHP 7.2 session cookie configuration while retaining HttpOnly and SameSite=Lax semantics.

If you previously saw `Parse error: unexpected ... (T_ELLIPSIS)` in `web/api.php` line 253, update the PHP source to this version and hard refresh the website. If the Apache-served PHP version is PHP 5.6, use WampServer's PHP menu to select one of your **already installed** PHP 7.2, 7.3 or 7.4 versions. No PHP installer is needed.

For the existing nested GitHub layout in `C:\wamp64\www\campuseats\CampusEats_Website_Android_App_Separated\Website`, keep `server/config.php` there and use:

```text
http://localhost/campuseats/CampusEats_Website_Android_App_Separated/Website/web/
```

In that layout, the `base_url` in `server/config.php` should point to the same URL without its trailing slash. Do not reimport an existing database or share `config.php` in GitHub.

## 8. Pay before collection (added)

**Important: existing installations must be migrated once.** Back up your database first using phpMyAdmin Export. Select your existing `campuseats` database and import `database/payment_migration.sql` exactly once. The SQL uses `CREATE TABLE IF NOT EXISTS` and does not delete existing users, orders, products or stock. New installations already include this table in `database/schema.sql`.

Copy the `payments` configuration section from `server/config.example.php` into your existing private `server/config.php` if it does not already contain it. Keep your existing `db_port` (for example 3308), database credentials and `base_url`; do not overwrite your working local config.

**Checkout no longer needs staff to approve payment:** The previous manual EFT payment option is removed from new orders. PayFast verifies live payments automatically; localhost testing can use an explicitly enabled simulation without real funds. Administrator access is still protected separately, and student-number verification still controls the 10% discount, not eligibility to place or pay for orders.

**PayFast online:** Set `payments.payfast.enabled` true, set `mode` to `sandbox`, and enter your Sandbox merchant ID, key and security passphrase. Use an accessible **HTTPS** `base_url` with a public callback (`/web/payfast-itn.php`). PayFast cannot send ITNs to `localhost`, `10.0.2.2`, or a private 192.168.x.x address. The order begins as **unpaid**, and the customer uses **Pay online**. An account holder does not need an administrator to confirm payment. The short-lived hand-off automatically constructs the signed form on the server. Only a verified gateway ITN (signature, source IP, server validation, matching amount and merchant) marks the order as paid. A return redirect is **not** payment proof. When using the Kotlin app, PayFast opens in the external browser rather than the embedded WebView. Once live credentials and HTTPS hosting are configured, change `mode` to `live` only after real Sandbox testing. Merchant secrets stay in `server/config.php` and must never be committed to GitHub or placed in the Kotlin app.

**Payment safety:** The original stock reservation occurs when checkout creates a *pending* order. Kitchen staff cannot advance to preparing/ready/collected until payment is verified. Unpaid orders can be cancelled and the reserved stock is restored. Paid orders cannot be cancelled through the old cancellation action because refunds must be handled separately with the payment provider or bank. Administrators must monitor abandoned pending orders and reconcile delayed gateway notifications, including arranging refunds where needed. Automatic refunds, timed reservation expiry, background payment reminders and accounting settlement are not part of this change.

**Security and operations:** PayFast must be configured for a public HTTPS domain and a directly verifiable notification source; proxies that mask the originating PayFast IP need a carefully designed trusted-proxy configuration before going live. Test against a dedicated database and Sandbox merchant account. PHP 7.2-7.4 compatibility remains for local development; for any publicly accessible production service, use a supported, security-maintained PHP version.


## Coursework checkout on a hosted website (no payment provider required)

CampusEats keeps its existing two-stage checkout, food stock reservations,
account password re-check, verified-student 10% discount, collection number
and shared browser/Android database. For a coursework project on the configured
HTTPS website, the internal `demo` checkout method may be opened to every
signed-in user. No card or bank details are requested, and no money is moved.

1. Deploy the current `Website/server/payments.php` **outside** `httpdocs`
   (the site's private `server` folder). Other PHP, JavaScript and Android
   files are unchanged by the open-registration checkout fix.
2. In the **existing** `payments` array in private `server/config.php`, set:

   ```php
   'school_checkout' => [
       'enabled' => true,
       'allow_registered_users' => true,
       'allowed_user_ids' => [1, 2] // Only used if allow_registered_users is false.
   ],
   ```

   Never publish `config.php`. Do not create a duplicate `payments` array.
   Leave `demo.enabled` and `payfast.enabled` set to false.
3. Ensure `base_url` matches the HTTPS hosting domain and `secure_cookies`
   is true. Checkout stays unavailable to anonymous visitors. Students and
   lecturers may order right after registration; the student discount still
   requires verification and new admin roles still require approval.
4. Sign in to the hosted website or Android app, refresh the page/app and place
   a no-charge order. The user must re-enter the CampusEats account password
   before receiving a collection number. Confirm orders in My orders and in
   the administrator dashboard.

If `allow_registered_users` is false, only accounts in
`allowed_user_ids` may check out (legacy restricted mode). For a real food
business or actual payments, **disable school_checkout** and integrate a
validated real payment provider first. A no-charge order is not proof of payment.

## Automatic payment, without manual payment approval

New orders accept **PayFast online payment** only when you have configured a merchant account and a publicly reachable HTTPS notification URL. The server activates collection number `CE000123` only after a validated PayFast `COMPLETE` notification, with the correct merchant and order amount. Customers can refresh **My orders** to see the collection number. The administrator cannot manually approve payment; they only prepare and hand over paid food orders. Existing EFT orders remain unpaid until handled separately, and can be cancelled and placed again.

### Fix the Administration error and update an existing database

1. In phpMyAdmin, **Export** your existing `campuseats` database as a backup.
2. Select the `campuseats` database on your **MySQL port 3308** server. Import `database/payment_migration.sql`. It adds (or updates) `order_payments` while retaining existing users, vendors, products and orders. Run it once; it is repeatable for installations using the original payment table.
3. Keep your existing `server/config.php` and `db_port => 3308`. Add the `payments` options from `server/config.example.php` without replacing credentials.
4. Refresh `web/api.php?action=bootstrap`, then refresh Administration. If the migration is missing, the API now returns a specific setup error.

### Safe local payment demonstration (no money transferred)

For a local presentation on the same computer running WampServer, add to the `payments` block in **private** `server/config.php`:

```php
'demo' => ['enabled' => true],
```

Keep `base_url` on localhost as already configured. The server also checks that the HTTP request and Apache server are both loopback addresses. **This is a simulated transaction, not a bank payment, and must never be used for real customers or on a public server.** After checkout, use **My orders > Complete test payment** to simulate confirmation and receive a clearly marked test collection number. Switch `demo.enabled` back to `false` before any public deployment.

### Real money payments

To accept actual card/bank payments without admin approval, register a PayFast merchant account, obtain your merchant ID, merchant key and passphrase, and configure a real public HTTPS `base_url`. Set `payments.payfast.enabled` to `true` with those details and arrange delivery to `web/payfast-itn.php`. Test the PayFast sandbox first. **PayFast cannot notify `http://localhost`**; a public HTTPS host (or secured temporary development tunnel) is required. Never put the passphrase or merchant key into the Android application.

Student and lecturer accounts can pay without account-role approval. Student-number verification is only required to qualify for the existing 10% discount; approval remains mandatory before someone gains administrative privileges. The Android app shares the same responsive UI and API, so its payment flow follows the website automatically after you rebuild with the correct server URL.

The Android emulator address `10.0.2.2` points to the Windows host. For a local simulated payment, the backend additionally requires that Apache and the emulator connection resolve to loopback IP addresses; real phones cannot use simulated payments.


## Interactive local payment demonstration

The existing basket and order routes are unchanged. A new modal in `web/index.php`
lets a student or lecturer rehearse the complete payment journey without
handling real financial information. Both **Demo bank card** and **Demo instant
EFT** are interface-only simulation choices; neither communicates with a bank.
The existing `payment_demo_complete` API verifies the current user owns the
order and allows the test payment only for a configured localhost request.

1. **Back up the existing database** in phpMyAdmin (MySQL port **3308** in the
   development setup shown in this conversation).
2. If the `order_payments` table is absent, import **only**
   `database/payment_migration.sql`. It is safe to use with existing records,
   but always back up first. Do not reimport `schema.sql` or `seed.sql`.
3. In the existing untracked `server/config.php` retain the working database
   connection, port and nested `base_url`. Add this section to the returned
   configuration array if it does not already exist:

   ```php
   'payments' => [
       'demo' => ['enabled' => true],
       'payfast' => ['enabled' => false]
   ],
   ```

   If a `payments` section already exists, set only
   `$config['payments']['demo']['enabled']` to `true` inside it. Never
   duplicate the `payments` array key. Do not share or commit `config.php`.
4. Refresh the site on **localhost**. Sign in as a student or lecturer,
   add a food item and select **Local test payment** in the basket.
5. Press **Continue to payment**. The modal displays a real, database-backed
   order amount, the student's verified discount (if applicable), and simulated
   card / instant EFT choices. Press **Simulate successful payment**.
6. View the **test receipt** showing a `CE######` collection number. The order
   becomes test-paid in My orders and can move through normal kitchen statuses.

**Security:** Demo checkout is *never* a real payment and must not be enabled
on public hosting. The server rejects non-loopback requests, and simulated
payments are excluded from actual reported revenue. Existing PayFast hand-off
is unchanged. A physical Android phone cannot use the localhost simulation;
for an emulator, server loopback routing may require further local setup. The
original Android APK remains prebuilt, so rebuild before testing changed web
asset behavior after updating your backend.


### Checkout account verification

Before completing a local simulated payment or requesting a PayFast payment link, the customer re-enters their **CampusEats email and account password**. The server verifies those details against the currently signed-in user and applies a rate limit. No bank account, bank-login or card information is requested by CampusEats. Google-only accounts must first set a CampusEats password under My account, or use the configured live provider flow after setting one.

The checkout is visually consistent with the rest of CampusEats; local transactions remain clearly disclosed as **simulations with no money charged**, including on the order receipt and order history. Simulated payments are excluded from real revenue. This protective notice must not be removed when the app is deployed.
