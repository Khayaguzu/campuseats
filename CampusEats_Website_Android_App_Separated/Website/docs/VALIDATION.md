# Validation record

Date: 15 October 2026.

## PHP and database

PHP 8.3 syntax validation passed for all PHP files. JavaScript syntax validation passed with Node. The schema and sample catalogue were imported into an isolated MariaDB 10.11 database, and a test administrator was created using the provided CLI script.

All **30 integration checks passed** against the actual PHP server and database. They cover:

- Administrator login and student/lecturer registration.
- Public administrator requests without immediate elevation.
- Administrator-only API enforcement and CSRF rejection.
- Invalid quantities, insufficient stock and closed vendor rejection.
- Database-backed baskets shared between independent sessions.
- Server-authoritative prices and duplicate-checkout prevention.
- Unverified student checkout without discount.
- Verified student checkout with exactly 10% discount: R90 becomes R81.
- Order cancellation, exact stock restoration and cross-user isolation.
- Legal and illegal order status transitions.
- Payment recording at collection.
- Shared settings, password change and subsequent login.
- Product stock edits and vendor closure.

The tests used temporary accounts and an isolated database. Test accounts, database files and credentials are not included in the delivered package. Run `tests/integration.py` only against your own clean test database, using an administrator you create.

## Browser

Headless Chromium checks passed for menu loading (32 products), search, administrator sign-in, administration, product editor, settings, switching to dark mode, adding food and checkout. No JavaScript runtime errors were observed. The desktop layout was visually inspected at 1440 × 1050, and the phone layout at 390 × 844. No horizontal overflow was detected on the phone viewport.

## Android build

The complete Gradle `assembleDebug` build succeeded using JDK 17, Gradle 8.9, Android Gradle Plugin 8.7.3, Kotlin 2.0.21 and Android SDK Platform 35. Kotlin compilation, resource processing, Java compilation, dexing and debug APK packaging completed successfully.

`apk/CampusEats-debug.apk` is included. It is configured for the standard Android emulator, pointing to `http://10.0.2.2/campuseats/web/`. For a physical phone, change the URL properties and rebuild. It is a development APK, not a production-signed release. Runtime operation on an Android device has not been verified in this environment.

## Remaining environment-dependent checks

- Installation and operation on the user's Windows/WampServer instance.
- Running the Android application on a real phone or emulator connected to that server.
- Live Google sign-in, account linking and the Android browser return flow, after configuring owner-controlled OAuth credentials and a reachable callback domain.
- Actual vendor foods, prices, stock, opening arrangements, ingredients and photographs.

These checks are not implied by successful source validation or a debug APK build. The package implements campus collection and payment on collection, not online card processing.

## Maintenance update, 15 October 2026

Repairs to the separated distribution improve setup feedback, OAuth button gating, documented checkout lead time and Android URL handling. PHP lint and JavaScript syntax checks passed, and the PHP API was smoke-tested without a configured database to confirm that it returns a useful JSON setup error. An earlier validation record above describes tests on the original release and must not be interpreted as re-running the full MySQL integration suite or rebuilding the APK after these maintenance edits.

**Still required on the owner's computer:** import the MySQL schema and seed, configure `server/config.php`, run the supplied database integration tests against an isolated database, rebuild the Android project, test on emulator/phone and configure Google OAuth credentials for live SSO.

## PHP 7.2 to 7.4 compatibility update

The reported `T_ELLIPSIS` error on PHP 7.2/7.3 was caused by PHP 7.4-only array unpacking in `api.php`. This version uses `array_merge()` for the SQL parameter list and removes newer PHP constructs from `bootstrap.php`, including `??=`, `never` and `JSON_THROW_ON_ERROR`. Cookie options retain SameSite=Lax where supported natively and use the PHP 7.2 cookie-path compatibility technique otherwise.

The existing admin, roles, stock, baskets, checkout, student discounts, Google SSO and API endpoints remain in place. Confirm the PHP interpreter version that Apache is using, the database credentials and the API response before retrying registration. Syntax checking on a later PHP version does not establish successful execution with PHP 7.2.

## Payment feature update, 15 October 2026

New orders use payment-first checkout. The new `order_payments` table is additive to the
existing database; existing data must not be re-seeded. Verification uses either explicit
administrator confirmation of an EFT after reviewing the real bank account or the PayFast
signed ITN plus source IP, merchant, amount and server-to-server confirmation checks.
An HTTP return redirect or customer-submitted information never marks an order paid.

Local verification performed on this update: `php -l` on every PHP file,
`node --check` on the shared website script, and `php tests/payment_helpers.php`
for money parsing, signature generation and method gating.

Not validated here: WampServer PHP 7.2 runtime, real database transactions, a live PayFast
ITN with merchant credentials, successful EFT reconciliation, and an Android build/device
run. Before accepting real payments test thoroughly on a non-production database, PayFast
Sandbox and a publicly reachable HTTPS callback, and build the modified Android source.


## Automatic payment correction

The manual `payment_verify` operation is no longer accepted. New orders accept PayFast only with a valid configured HTTPS merchant callback, or an explicitly enabled, loopback-restricted **demo simulation** for local development. No collection number is displayed before a confirmed payment, and only paid orders can progress to food preparation and collection. The `order_payments` migration must be applied on existing databases to avoid a generic Administration 500 error. PayFast live payment and callback integration have not been validated with real merchant credentials in this environment; complete sandbox and end-to-end Android tests before accepting money.
