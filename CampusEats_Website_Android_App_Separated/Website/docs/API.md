# Shared API contract

Base path: `/campuseats/web/api.php?action=ACTION`. Both the browser and the Kotlin WebView use this same API. MySQL credentials exist only on the PHP server. All JSON responses have `application/json` content type. Error responses contain `{"error":"Human-readable message"}`.

## Authentication

Call `GET bootstrap` first and keep its session cookie. Use its `csrf` value as `X-CSRF-Token` on every POST. Send `Content-Type: application/json`. No cross-origin access is configured. An unauthenticated protected endpoint returns 401; insufficient privileges or missing CSRF returns 403; validation failure returns 422.

| Action | Method | Access | Request/response summary |
| --- | --- | --- | --- |
| bootstrap | GET | Public | Returns user, csrf, vendors, sso_enabled |
| catalog | GET | Public | Returns active products, prices in cents, stock and vendor availability |
| register | POST | Public | name, email, password, role, student_number |
| login | POST | Public | email, password; establishes session |
| logout | POST | Signed in | Empty JSON object; clears session |
| settings | POST | Signed in | name, phone, student_number, theme, order_updates |
| password | POST | Signed in | current_password, new_password |
| role_request | POST | Signed in | role, student_number; cannot grant admin privileges |
| cart | GET | Signed in | Returns this user's persisted basket |
| cart_set | POST | Signed in | product_id, quantity; 0 removes; maximum 99 per product |
| checkout | POST | Signed in | request_key, pickup_at (ISO timestamp with timezone), notes |
| orders | GET | Signed in | Most recent 100 of this user's orders, including item snapshots |
| cancel | POST | Signed in | id; only own placed order |
| admin | GET | Admin | Products, users, recent 100 orders, revenue stats and image names |
| product_save | POST | Admin | id (0 for new), vendor_id, name, description, category, price_cents, stock, image, active |
| vendor_save | POST | Admin | id, name, location, is_open |
| user_approve | POST | Admin | id; applies requested role and marks verification |
| order_status | POST | Admin | id, status; legal transition only |

## Checkout

A basket can contain food from several kitchens. Collection locations are snapshotted in each order item. All basket products must be active, available, from open vendors and in stock. The server serializes checkout for the user and locks inventory during its transaction. Order inserts, item snapshots, stock deductions and basket clearing either all commit or all roll back.

The discount is `round(subtotal_cents / 10)` for a verified student, otherwise zero. Client-supplied prices or totals are ignored. `request_key` must contain 16 to 36 ASCII letters/digits/hyphens and is unique per user. Reusing it returns the original order identifier.

The `pickup_at` timestamp is stored in UTC. The interface displays order collection times in Africa/Johannesburg. The collection-time input uses the device's local timezone before converting to an ISO timestamp.

## Single sign-on endpoints

- `GET sso.php`: Begin Google login.
- `GET sso.php?mode=callback`: Provider callback; state and lifetime are validated.
- `GET sso.php?challenge=...`: Begin Android login from the system browser with an SHA-256 verifier challenge.
- `POST sso.php?mode=redeem`: URL-encoded `ticket` and `verifier`; consumes a valid two-minute ticket and creates the application cookie session. This endpoint uses possession proof instead of the ordinary API CSRF token, because it bootstraps a new WebView session.

No Google client secret belongs in the app. Local and Google accounts are explicitly linked only from an authenticated session for the same email address.
