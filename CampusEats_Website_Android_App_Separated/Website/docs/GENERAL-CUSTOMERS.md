# CampusEats: food ordering for everyone

The website and Android app now welcome all customers. CampusEats remains the product name. Rosebank College branding and its logo have been removed from the active interface and the logo asset has been deleted.

Registration asks for name, email and password. Google sign-in is also supported after provider setup. Every public registration creates a customer; submitted role fields cannot create an administrator. Account settings no longer ask for institution numbers or role requests. Customer accounts can browse, order and collect food without academic verification. Store administrators still manage products, vendors and orders.

New orders use the same listed prices for everyone, with no student discount. Historical order totals and discounts are preserved. The website and Android app share this interface and the same server rules.

## Deploy this update

1. Back up the current database and private server/config.php.
2. Put the site in maintenance mode while updating the schema and PHP files together.
3. Select the existing CampusEats database in phpMyAdmin and run database/general_customers_migration.sql. This converts existing student/lecturer accounts to customer and preserves administrator accounts. Pending administrator requests do not grant access. It preserves user IDs, passwords, Google links, orders, baskets and historical prices. The script can safely be rerun.
4. Upload the updated server and web directories, including server/session.php. Preserve the existing private config.php. Remove web/assets/college-logo.png from the host as well; copying new files alone does not delete old assets. Do not import schema.sql or seed.sql over existing data.
5. Restart the site and refresh the browser/app. Install the newly built Android 1.1.0 APK when using the improved SSO handoff. Android loads the shared hosted interface, so branding updates are served by the same website.
6. Create a customer with name, email and password, check profile editing, place a test order, and verify the same account in Android. Check that admin-only screens remain protected.

Legacy academic database columns may remain on upgraded databases solely to preserve stored data. The new application never reads or writes them. Fresh installations omit those fields entirely. Removing them permanently is not necessary for this update.

## Payments and Google setup

The general customer update does not configure or activate a payment provider. Existing explicit demonstration mode still represents no-charge orders. Real purchases require the existing PayFast flow to be configured and validated separately. Do not treat a demonstration confirmation as money received.

Google OAuth credentials are still required to activate real Google sign-in. Follow SSO-DEMONSTRATION.md for configuration and cross-platform assessment evidence.

## Verification

CI verifies general customer registration without role or academic fields, attempted role escalation, shared baskets/profiles, full-price checkout, payment confirmation safeguards, inventory changes, admin access, migration data preservation and repeated migration. It also runs the SSO/session regressions and builds the Android app. Live hosting and real Google/payment provider authentication require separate deployment testing.
