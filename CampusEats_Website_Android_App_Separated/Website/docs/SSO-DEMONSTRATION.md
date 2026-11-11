# CampusEats Google SSO: setup and assessment evidence

## Implementation and rubric

The website and Kotlin Android WebView app use the same PHP server, users table and Google identity. Google authentication occurs in the system browser. PHP exchanges the authorization code on the server and fetches the verified identity from Google's HTTPS UserInfo endpoint. It identifies returning users by Google's subject identifier, not a submitted email address.

The Android app generates a random verifier and sends only its SHA-256 challenge to the server. After Google sign-in, the user taps **Return to CampusEats app**. The app redeems a single-use ticket together with its verifier. The server checks both, consumes the ticket in a database transaction and creates the app's session. Tickets expire after two minutes. Google access tokens and the client secret are never sent to the app.

| Rubric requirement | Implementation | Evidence to record |
| --- | --- | --- |
| Functional third-party login on website | Continue with Google, server-side authorization-code exchange | Website Google account selection, followed by My account |
| Functional third-party login on Android | External browser authentication, app return and proof-bound ticket redemption | App login, browser Google account selection, Return to CampusEats app, signed-in My account |
| Integration across both platforms | Same Google subject maps to the same database user | Same email and profile on both; change profile on one and refresh the other |
| Correct session handling | HttpOnly/SameSite cookies, rotated session IDs, idle/absolute expiry, one-use tickets, POST logout | Refresh while signed in; logout and protected-page checks; automated regression results |

A completed implementation does not guarantee a mark. The assessor must see successful real Google authentication on both platforms. Automated tests below do not prove that production Google settings or the deployed APK work.

## One-time hosted configuration

1. Confirm the hosted website and database work. Keep an existing backup of server/config.php. The update must not overwrite database credentials or payment settings.
2. In Google Cloud, configure the OAuth consent screen for CampusEats and create an OAuth client of type **Web application**. Add your demonstration accounts as test users if the project is in Testing mode.
3. Register this exact authorized redirect URI for the current root-hosted installation:

   `https://khayaguzu.helioho.st/sso.php?mode=callback`

4. In the private server/config.php set base_url to `https://khayaguzu.helioho.st`, secure_cookies to true, and fill google_client_id and google_client_secret with the real client values. Keep the secret out of GitHub, screenshots, Android code and chat. Enable PHP cURL and PDO MySQL. The server needs outbound HTTPS access to Google.
5. Both Android_App/gradle.properties values, CAMPUS_BASE_URL and CAMPUS_SSO_URL, must be `https://khayaguzu.helioho.st/`. Rebuild and install the new APK. The APK already stored in the source repository predates this update.
6. Deploy updated server and web files, including the new server/session.php. The existing users.google_sub and mobile_tickets schema already supports this update; no destructive database import is required.
7. Existing sessions created before this update will require sign-in again. The default idle timeout is 30 minutes and the absolute lifetime is eight hours. The idle timer measures server requests, including background activity.

Both platforms use the PHP web OAuth client. Do not put a Google client secret in the Android app or register campuseats://auth as Google's redirect URI. That URI is only the PHP server's handoff back to the app.

Reference: https://developers.google.com/identity/protocols/oauth2/web-server

## Existing password accounts

Account linking deliberately requires proof of ownership. Open the CampusEats website in the system browser, log in with the existing CampusEats password, open My account, and select **Link Google identity** using the same email. Then use **Continue with Google** on either platform. An existing linked Google identity cannot be silently replaced. For a Google account without an existing CampusEats account, the first sign-in creates a normal account without administrator privileges or verified-student status.

The Android WebView and browser have separate cookies. Logging into the WebView with a password does not also log the system browser into that account. Complete initial linking in the browser as described above.

## Demonstration recording

1. On the website, choose Continue with Google and complete real Google authentication. Show My account with the signed-in email and connected Google status.
2. Refresh the website and open a protected feature such as My orders. Confirm the session remains valid.
3. On Android, choose Continue with Google. Show the system browser, Google authentication/account selection, and the successful return page. Tap Return to CampusEats app within two minutes.
4. Show the same email in Android My account. Change a harmless profile field, save it, then refresh the website to show shared account data.
5. Sign out in Android. Confirm My orders requires authentication. Relaunch or refresh to confirm the app has not recovered the old signed-in session.
6. Sign out separately on the website and confirm protected access is blocked there as well.
7. Demonstrate cancellation without a new signed-in session. Record automated results for expired/replayed tickets and session expiry.

CampusEats logout ends the current client session. It does not log the person out of their Google account or another CampusEats browser/app session. That is intentional local logout, not global Google logout.

## Verification and limitations

The Test CampusEats SSO workflow runs PHP syntax checks, JavaScript syntax validation, session lifetime/fixation tests and real PHP/MySQL endpoint tests for state rejection, cancellation, malformed tickets, wrong verifiers, expiry, replay, shared identity and logout. It uses dummy OAuth credentials and never contacts Google. The Android workflow builds the changed Kotlin application.

Real website and Android Google authentication, provider configuration, device browser handoff and hosted deployment must additionally be verified manually. Do not label unrecorded tests as passed. Capture the demonstration described above only after the real credentials and hosting are configured.

## Troubleshooting

- No Google button: the hosted server has no Google client ID/secret configured.
- redirect_uri_mismatch: compare the Google redirect URI with base_url plus `/sso.php?mode=callback`, including its path and query.
- Existing account message: complete the browser-based linking procedure above.
- App configuration message: both Android URLs must match and use HTTPS; rebuild after changing them.
- Expired return link: start sign-in again from the app. Do not reuse an old return page.
- Website unavailable: restore hosting first; neither app login nor website login can work without the shared server.
