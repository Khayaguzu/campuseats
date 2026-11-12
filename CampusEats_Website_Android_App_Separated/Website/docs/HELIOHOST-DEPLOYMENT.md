# CampusEats deployment to the existing HelioHost website

This guide upgrades an existing CampusEats installation to **general customer accounts**. It does not create a new database or configure an online payment merchant.

Public website: `https://khayaguzu.helioho.st/`

## Before changing the live site

1. Sign in to your HelioHost hosting control panel and locate the existing CampusEats files and MySQL/MariaDB database. Confirm which PHP directory is actually served for the public site. Do not assume a folder name without checking.
2. **Back up the entire existing database** in phpMyAdmin using Export (SQL format). Also download the existing private `server/config.php`, existing application files and any other custom resources to a private location.
3. Record the current counts of accounts and orders, using the existing application's database in phpMyAdmin:

   ```sql
   SELECT role, COUNT(*) AS accounts FROM users GROUP BY role;
   SELECT COUNT(*) AS orders_before FROM orders;
   SHOW COLUMNS FROM users LIKE 'role';
   ```

4. Check that the existing database has the tables and columns expected by the application, including `users.role`, `users.google_sub`, `orders`, and `order_payments`. If `order_payments` is missing, use the existing separate `database/payment_migration.sql` procedure after taking a backup. **Do not import `schema.sql` or `seed.sql` into an existing database.**
5. Plan a maintenance window. Keep the site unavailable to customers during the database and PHP update so the old PHP code does not run against the new customer-only role values.

## Upgrade the existing database

In phpMyAdmin, select the **actual database used by** `server/config.php` (HelioHost may assign a prefixed database name). Import or execute:

`Website/database/general_customers_migration.sql`

The script changes student and lecturer roles into `customer`, retains `admin`, and does not delete accounts or existing orders. It is designed to be repeatable.

Run these read-only checks afterwards:

```sql
SELECT role, COUNT(*) AS accounts FROM users GROUP BY role;
SELECT COUNT(*) AS orders_after FROM orders;
SHOW COLUMNS FROM users LIKE 'role';
```

Confirm that the order count matches the pre-migration count and that the role definition now permits `customer` and `admin` only. Existing discounts in historical orders are preserved.

**Important:** Retained legacy database columns do not mean academic account categories remain enabled. The new code no longer uses those columns. Do not delete the columns without a separate, planned data-retention decision.

## Upload the changed website and server files

1. Obtain the current code from the `codex/google-sso-web-android` branch of `Khayaguzu/campuseats` (or `main` after it has been merged).
2. Upload the **contents** of `Website/web/` to the public website directory already configured on HelioHost. Preserve the existing public URL and path.
3. Upload the new `Website/server/` PHP code, including `session.php`, to the existing **private server directory**, outside the publicly served document root. Do not overwrite `server/config.php` with `config.example.php`.
4. In hosting File Manager, explicitly delete the old public asset `web/assets/college-logo.png`. Uploading files does not automatically remove this obsolete asset.
5. Confirm that `server/config.php` continues to use the correct hosted database details, `base_url = 'https://khayaguzu.helioho.st'` and `secure_cookies = true`. The private config must not be published in GitHub or screenshots.
6. Clear any server or browser caching, then restore site access. Existing sessions might need users to sign in again.

## Check the live website

1. Visit the homepage in a private browser window and verify that the site says **Good food. For everyone.**
2. Confirm the old college logo and academic account-selection fields are absent.
3. Create a new customer account with only name, email and password. Verify that public registration does not grant administrator privileges.
4. Sign in as a pre-existing account, verify previous orders remain, then update a harmless profile field.
5. Add food to the basket, check that there is no student-only discount, and complete a **test order** only through a deliberately configured demonstration checkout or validated payment provider.
6. Sign in as a separately provisioned administrator and confirm that products, vendors, and orders can be managed. Verify that a normal customer cannot open admin-only functions.
7. Check for PHP errors in hosting logs without sharing secrets or customer information.

## Android build and Google sign-in

The Android app loads the same hosted interface. Install the **newly built** debug APK from the latest successful GitHub Actions run for this branch, not the old `Android_App/apk/CampusEats-debug.apk` checked into the repository. A debug build is for testing, not production distribution.

The Android Gradle properties already target `https://khayaguzu.helioho.st/`. After website deployment, test registration, menu, basket, profile, orders and collection on an actual Android device.

**Google sign-in remains unavailable until the site owner creates and configures the real Google OAuth web client.** Follow [SSO-DEMONSTRATION.md](SSO-DEMONSTRATION.md). Register the exact redirect URI `https://khayaguzu.helioho.st/sso.php?mode=callback`. Never paste the OAuth client secret into chat, GitHub or the Android source.

## Recovery if the upgrade fails

Stop customer traffic. Restore **both** the backed-up database and the matching prior PHP/web application files before reopening the site. Merely reverting PHP while keeping the new role ENUM can break the old application's registration and role logic. After restoring, confirm login and orders using the pre-upgrade checks.

## Deployment status

The pull request and automated tests validate the **source code and isolated test environment**. They cannot confirm a production database migration, file upload, working Google credentials, payment provider or real-device operation. Complete and record the checks above before marking live deployment as verified.
