# CampusEats website

A general food-ordering website shared by browser users and the Kotlin Android WebView app. All public accounts are customers. Only separately provisioned administrators can manage the store.

## Existing installations

Read [GENERAL-CUSTOMERS.md](docs/GENERAL-CUSTOMERS.md). Back up the database and config.php, run database/general_customers_migration.sql on the existing database, then deploy the updated PHP and web files together. Do not reimport schema.sql or seed.sql into an existing installation. Preserve the private server/config.php and remove the obsolete college-logo.png asset from the host.

## New installations

1. Use PHP 7.2 or newer with PDO MySQL, cURL and a MySQL/MariaDB database. Use a maintained PHP release for public hosting.
2. Import database/schema.sql and, for a sample menu, database/seed.sql.
3. Copy server/config.example.php to server/config.php. Set database connection details and the public base_url. Keep server/ outside the public document root; serve web/ publicly.
4. Set secure_cookies to true for HTTPS hosting. Use the same HTTPS base URL for both Android Gradle URL properties.
5. Provision an administrator from a terminal:

   `php server/create-admin.php admin@example.com "Store Administrator" "ChooseYourOwnLongPassword!"`

6. Open the website and create an ordinary account using name, email and password. No account category or institution number is required.
7. Configure Google OAuth using [SSO-DEMONSTRATION.md](docs/SSO-DEMONSTRATION.md).

## Ordering and payments

Customers browse the menu, add food to a basket, choose collection time and complete checkout. Prices and stock are enforced by PHP. There is no role-based discount on new orders. Existing historical discounts remain on their orders.

Configure PayFast in private server/config.php for genuine payments, including its public HTTPS callback. Provider confirmation is required before the order is marked paid. Existing explicit demonstration modes remain no-charge simulations and must not be mistaken for real purchases. This update does not activate payments or change private payment credentials.

## Validation

GitHub Actions checks PHP/JavaScript, customer ordering and account permissions, non-destructive migration, SSO sessions and ticket redemption, and Android APK compilation. The APK in older source packages is not automatically replaced; use the artifact from the latest successful Android build.

See [API.md](docs/API.md) for endpoint details and [GENERAL-CUSTOMERS.md](docs/GENERAL-CUSTOMERS.md) for deployment verification.
