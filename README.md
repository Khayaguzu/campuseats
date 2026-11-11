# CampusEats

Food ordering for everyone, with a PHP/MySQL website and a Kotlin Android app using the same hosted interface and account database.

## Current update

- General customer registration with name, email and password.
- Google sign-in for website and Android after OAuth configuration.
- Menu browsing, shared basket, order-ahead collection and account settings.
- Customer and administrator access; public registration never grants administrator privileges.
- Equal menu prices for all new orders, with historical order amounts preserved.
- Store administration for products, vendors and order preparation.

The current update removes institution branding and academic account categories. Read the [deployment and migration guide](CampusEats_Website_Android_App_Separated/Website/docs/GENERAL-CUSTOMERS.md) before upgrading an existing installation.

## Project structure

- `CampusEats_Website_Android_App_Separated/Website`: PHP server, shared web interface, SQL and tests.
- `CampusEats_Website_Android_App_Separated/Android_App`: Kotlin WebView client and Gradle project.

See [website installation](CampusEats_Website_Android_App_Separated/Website/README.md), [Android instructions](CampusEats_Website_Android_App_Separated/Android_App/README.md), [API](CampusEats_Website_Android_App_Separated/Website/docs/API.md) and [Google SSO setup](CampusEats_Website_Android_App_Separated/Website/docs/SSO-DEMONSTRATION.md).

Google credentials and payment settings remain private server configuration. No-charge demonstration checkout does not process real payments. Configure and validate PayFast before accepting actual money.
