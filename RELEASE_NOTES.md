# CampusEats Release Notes

**Latest documented version:** Android **1.1.0** (`versionCode 2`)  
**Documentation date:** 12 November 2026  
**Corresponding application source:** [`codex/google-sso-web-android`](https://github.com/Khayaguzu/campuseats/tree/codex/google-sso-web-android)  
**Live demonstration:** https://khayaguzu.helioho.st/

> The current application is a **no-charge demonstration project**. No funds are deducted or received. Google OAuth and real PayFast services are not enabled until independently configured and verified. These notes describe source changes and observed test results, not a published Google Play release.

## Version timeline

| Version / stage | Status | Summary |
| --- | --- | --- |
| **Prototype / early CampusEats** | Historical | Student/Lecturer academic role workflow, shared PHP/MySQL ordering foundation, menu, basket, collection orders and administrator tools |
| **Improved shared-hosting prototype** | Historical development | Local-versus-hosted URL troubleshooting, Android API configuration, checkout validation, order history and food-photo improvements |
| **v1.1.0, general customers** | Current feature-branch source and hosted demonstration, November 2026 | Open customer registration, independent branding, responsive Android layout, controlled role migration, no-charge checkout, CI build/test coverage and updated documentation |

The table summarises meaningful user-visible and technical changes. Minor edits are available through Git commit history; release notes do not imply an exhaustive line-by-line commit inventory.

## v1.1.0, updated CampusEats

### 1. General-customer accounts instead of academic-specific onboarding

**Before:** The prototype centred on Student and Lecturer roles, student-number verification and an eligibility-based discount.

**Now:**
- Public registration collects **name, email and password** without requiring an institution or academic role.
- Every public account is a **Customer**; administrator status is never granted by changing a submitted form field.
- An existing Administrator can still maintain the store.
- New orders use the same catalogue price for customers, with no academic-category discount.
- The migration retains historical accounts, order records and previously calculated order totals.
- Deprecated academic fields may remain physically in an upgraded database to preserve data, but the updated app does not rely on them.

**Innovation for the project:** Non-destructive business-rule migration, combined with stricter server-side privilege separation.

**Evidence:** [General customer migration and verification](https://github.com/Khayaguzu/campuseats/blob/codex/google-sso-web-android/CampusEats_Website_Android_App_Separated/Website/docs/GENERAL-CUSTOMERS.md).

### 2. Updated brand identity and interface

**Before:** The prototype contained institution-related imagery and an academic audience emphasis.

**Now:**
- The official green-and-orange CampusEats logo is shown on the website and in the app.
- The main message is **“Good food. For everyone.”**
- Updated food photography, menu cards, search, categories and kitchen filters communicate the product purpose.
- Mobile navigation, spacing, images and dialog styling have been adjusted for phone screens.
- Desktop browsers retain a larger sidebar-oriented view; Android uses bottom navigation.

**Innovation for the project:** One brand and responsive interface instead of maintaining separate inconsistent designs.

**Evidence:** [Website and Android screenshots](README.md#3-screenshots).

### 3. Shared website and Android data

**Before:** Local WampServer and emulator networking could cause a working website to fail in Android when a local `10.0.2.2` URL was used.

**Now:**
- The Kotlin Android WebView uses the hosted site at `https://khayaguzu.helioho.st/`.
- Website and Android call the same PHP API and use the same hosted database.
- Customers see centrally saved profiles, baskets, food items and orders on either platform after signing in.
- Browser and WebView sessions remain isolated, so each client signs in separately.
- A fresh v1.1.0 APK can be compiled in Android Studio or built by GitHub Actions.

**Innovation for the project:** Cross-platform consistency through one server-authoritative application.

**Evidence:** [Android README and connection guide](https://github.com/Khayaguzu/campuseats/blob/codex/google-sso-web-android/CampusEats_Website_Android_App_Separated/Android_App/README.md).

### 4. No-charge Card and Instant EFT practice checkout

**Before:** Checkout was designed around academic ordering and its earlier payment/test configuration.

**Now:**
- The checkout interface allows users to select **Card** or **Instant EFT** as **practice options**.
- Only fictional sample details should be entered.
- A successful confirmation generates a unique collection number.
- Orders appear in **My orders**, where progress can be tracked.
- No real banking transaction occurs and the confirmation explicitly states **no charge**.
- Real payment integration remains disabled until merchant configuration and independent callback validation.

**Innovation for the project:** A realistic demonstration of payment choice and order confirmation without handling real money.

**Evidence:** [Android basket and order-confirmation screenshots](README.md#3-screenshots).

### 5. Improved order handling and administrator capabilities

- Maintain an order lifecycle from **Placed** to **Preparing**, **Ready** and **Collected**.
- View collection locations and saved order-line information.
- Update food prices, descriptions, images, categories and stock in Administration.
- Maintain vendor locations and whether a kitchen is accepting orders.
- Keep the Administration interface and server-side mutations restricted to approved accounts.
- Provide Customer account preferences, light/dark appearance and order-update settings.

**Innovation for the project:** Unified fulfilment management across the public customer interface and restricted operations interface.

### 6. Security, configuration and deployment improvements

- Keep private database credentials in `server/config.php` outside the public document root.
- Use HTTPS and secure cookies on the hosted installation.
- Preserve historical accounts and orders during schema updates.
- Treat the user-facing checkout state separately from verified receipt of money.
- Reject attempts to create administrator accounts through public registration.
- Record migration and rollback instructions, including the requirement to restore matching code **and** database on recovery.
- Avoid committing passwords, OAuth secrets and full database exports to public GitHub.

**Innovation for the project:** More reliable deployment of an evolving system while retaining its historical information.

### 7. GitHub Actions and quality assurance

**Three automated workflows** are defined for the updated source:

1. **Android APK build:** Set up Java 17 and Android SDK 35, build `app-debug.apk`, verify the hosted URL and upload the artifact (retained 30 days).
2. **PHP checkout tests:** Syntax-check checkout/PHP files and execute payment helper tests.
3. **SSO and customer integration tests:** Create an isolated MySQL service and exercise PHP/JavaScript syntax, session helpers, migration, user accounts, ordering, access control and SSO handoff.

**Verified successful runs on 12 November 2026** (source commit `abaade98cbc3a5276629fd1a0885e1bbcf35298a`):

| Pipeline | Result | Evidence |
| --- | --- | --- |
| Android debug APK | ✅ Passed | [Run 43159988806](https://github.com/Khayaguzu/campuseats/actions/runs/43159988806) |
| Checkout PHP | ✅ Passed | [Run 43159988817](https://github.com/Khayaguzu/campuseats/actions/runs/43159988817) |
| SSO/customer integration | ✅ Passed | [Run 43159988788](https://github.com/Khayaguzu/campuseats/actions/runs/43159988788) |

**Innovation for the project:** Automated, repeatable build and regression verification tied to GitHub source-control events.

### 8. Manual website and phone testing

The following activities were demonstrated during final testing:

- Loading the hosted responsive website and Android app.
- Logging in as a Customer, loading the menu and inspecting account settings.
- Adding products to a basket and selecting collection time.
- Completing a no-charge Card workflow and receiving collection references.
- Viewing orders and progress on Android and the website.
- Exercising Administrator screens with restricted navigation.
- Correcting and rechecking Android styling after a stylesheet deployment and cache update.

**Test boundary:** These observations do not establish production payment security, complete device compatibility or Google OAuth readiness.

## Known issues and planned work

| Item | Current status / required next step |
| --- | --- |
| **Google OAuth** | Code support exists, but live credentials and authorised redirect setup are still needed |
| **Real-money checkout** | Not enabled; PayFast credentials, sandbox verification, notification validation and operational controls are required |
| **Google Play distribution** | Current artifact is a debug APK; build and sign a release Android App Bundle and complete Play Store checks |
| **Main branch parity** | Updated version is still on a feature branch; review and merge before treating `main` code as v1.1.0 |
| **Broader test coverage** | Run further accessibility, security, load, device and usability testing |
| **Notifications** | Order-update settings exist, but always-on background notifications would require additional infrastructure |

## Upgrading an existing installation

**Do not import the fresh schema or seed SQL over a live database.**

1. Back up the current database, site files and private configuration.
2. Read [GENERAL-CUSTOMERS.md](https://github.com/Khayaguzu/campuseats/blob/codex/google-sso-web-android/CampusEats_Website_Android_App_Separated/Website/docs/GENERAL-CUSTOMERS.md) and [HELIOHOST-DEPLOYMENT.md](https://github.com/Khayaguzu/campuseats/blob/codex/google-sso-web-android/CampusEats_Website_Android_App_Separated/Website/docs/HELIOHOST-DEPLOYMENT.md).
3. Apply the documented, repeatable customer migration to the **existing** database.
4. Deploy the corresponding updated PHP server/web source together while keeping private credentials safe.
5. Verify role permissions and account/order preservation.
6. Install a newly built Android APK targeting the hosted HTTPS URL.

## Definition of "innovative" for this release

The features above are presented as **innovations relative to the CampusEats prototype**, not as claims that food ordering, WebView, role-based access or CI/CD are novel inventions. The innovation is the integration and improvement of these techniques for this project.

Return to the [full project README](README.md).
