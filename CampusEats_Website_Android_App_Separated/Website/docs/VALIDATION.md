# CampusEats validation record

## Current general-customer update, 11 November 2026

Source branch: `codex/google-sso-web-android`, pull request [#3](https://github.com/Khayaguzu/campuseats/pull/3).

The update removes college branding and the old academic account choices from the active website and shared Android interface. New public accounts receive the `customer` role, with `admin` provisioned separately. New orders use listed menu prices for every customer, while existing order records retain their original amounts.

### Automated evidence

| Workflow | GitHub Actions run | Reported result |
| --- | --- | --- |
| Website and Android Google SSO / session regression tests | [Test CampusEats SSO](https://github.com/Khayaguzu/campuseats/actions/runs/43027505958) | Passed on the original PR head |
| Checkout PHP and account / migration regression tests | [Test CampusEats Checkout PHP](https://github.com/Khayaguzu/campuseats/actions/runs/43027506127) | Passed on the original PR head |
| Kotlin debug APK build | [Build CampusEats Android APK](https://github.com/Khayaguzu/campuseats/actions/runs/43027505987) | Passed on the original PR head |

These original runs were against commit `46154dbb4b36fa1055a72935c4c402d5ecc5edce`. When a later commit is added, check the latest run before treating these checks as current. The original APK artifact is a build output, not evidence of successful installation or external Google authentication.

The database tests use an isolated test database. They do not migrate or verify the privately hosted database.

### Manual checks still required

- Back up the existing hosted database and `server/config.php`; run the general-customer migration on the actual production database.
- Upload the latest server and web files, remove the obsolete logo asset and verify the homepage and registration screen.
- Confirm existing customer logins, historical orders and administrator access.
- Install the current APK and verify real website / Android sharing.
- Configure actual Google OAuth credentials and test browser-to-app return on a device.
- Validate payment-provider settings separately before accepting money.

See [HELIOHOST-DEPLOYMENT.md](HELIOHOST-DEPLOYMENT.md) and [SSO-DEMONSTRATION.md](SSO-DEMONSTRATION.md). Do not present simulated payment as real payment.

## Historical testing note

Earlier validation notes referred to a legacy academic-role release. Those tests are not evidence that the current general-customer version works on production hosting. The up-to-date record above supersedes the earlier project-specific claims.
