# Shared API contract

Base path: `/campuseats/web/api.php?action=ACTION`. Both the browser and the Kotlin WebView use this same API. MySQL credentials exist only on the PHP server. All JSON responses have `application/json` content type. Error responses contain `{"error":"Human-readable message"}`.

## Authentication

Call `GET bootstrap` first and keep its session cookie. Use its `csrf` value as `X-CSRF-Token` on every POST. Send `Content-Type: application/json`. No cross-origin access is configured. An unauthenticated protected endpoint returns 401; insufficient privileges or missing CSRF returns 403; validation failure returns 422.

| Action | Method | Access | Request/response summary |
| --- | --- | --- | --- |
| bootstrap | GET | Public | Returns user, csrf, vendors, sso_enabled, payment_options |
| catalog | GET | Public | Returns active products, prices in cents, stock and vendor availability |
| register | POST | Public | name, email, password, role, student_number |
| login | POST | Public | email, password; establishes session |
| logout | POST | Signed in | Empty JSON object; clears session |
| settings | POST | Signed in | name, phone, student_number, theme, order_updates |
| password | POST | Signed in | current_password, new_password |
| role_request | POST | Signed in | role, student_number; cannot grant admin privileges |
| cart | GET | Signed in | Returns this user's persisted basket |
| cart_set | POST | Signed in | product_id, quantity; 0 removes; maximum 99 per product |
| checkout | POST | Signed in | request_key, pickup_at (ISO timestamp with timezone), notes, payment_method (payfast or demo on localhost only) |
| orders | GET | Signed in | Most recent 100 orders, item snapshots, method, paid/unpaid state and payment method |
| payment_start | POST | Order owner | id; creates a short-lived PayFast payment URL for an unpaid PayFast order |
| cancel | POST | Signed in | id; only own placed order |
| admin | GET | Admin | Products, users, recent 100 orders, revenue stats and image names |
| product_save | POST | Admin | id (0 for new), vendor_id, name, description, category, price_cents, stock, image, active |
| vendor_save | POST | Admin | id, name, location, is_open |
| user_approve | POST | Admin | id; applies requested role and marks verification |
| order_status | POST | Admin | id, status; paid orders only for preparing/ready/collected |
| payment_demo_complete | POST | Order owner, localhost only | id; confirms simulated test payment (no real money), enabled explicitly in local configuration |

## Checkout

A basket can contain food from several kitchens. Collection locations are snapshotted in each order item. All basket products must be active, available, from open vendors and in stock. The server serializes checkout for the user and locks inventory during its transaction. Order inserts, item snapshots, stock deductions and basket clearing either all commit or all roll back.

The discount is `round(subtotal_cents / 10)` for a verified student, otherwise zero. Client-supplied prices or totals are ignored. `request_key` must contain 16 to 36 ASCII letters/digits/hyphens and is unique per user. Reusing it returns the original order identifier.

Orders are stored unpaid, and the collection order number is only displayed as valid after confirmed PayFast payment or clearly labelled local simulation. Pending orders reserve stock; unpaid orders can be cancelled with stock restoration. Paid cancellations require a separate bank/provider refund process. The payment status cannot be updated by the browser or a customer-supplied reference.

The `pickup_at` timestamp is stored in UTC. The interface displays order collection times in Africa/Johannesburg. The collection-time input uses the device's local timezone before converting to an ISO timestamp.

## Single sign-on endpoints

- `GET sso.php`: Begin Google login.
- `GET sso.php?mode=callback`: Provider callback; state and lifetime are validated.
- `GET sso.php?challenge=...`: Begin Android login from the system browser with an SHA-256 verifier challenge.
- `POST sso.php?mode=redeem`: URL-encoded `ticket` and `verifier`; consumes a valid two-minute ticket and creates the application cookie session. This endpoint uses possession proof instead of the ordinary API CSRF token, because it bootstraps a new WebView session.

No Google client secret belongs in the app. Local and Google accounts are explicitly linked only from an authenticated session for the same email address.

## Payment gateway endpoints

- `GET pay.php?token=...`: Short-lived PayFast form hand-off with server-calculated amount, merchant data and signature. The Android Kotlin WebView sends the hand-off URL to the system browser. Browser return does not authenticate payment.
- `POST payfast-itn.php`: Server-to-server PayFast ITN. The callback validates the merchant ID, MD5 signature and secret passphrase, the remote IP against PayFast hosts, the PayFast verification endpoint over TLS, the order ID, the exact amount, and completed status. Only then does it mark the order paid. A duplicate callback for the same provider payment ID is idempotent.

The `order_payments` table is installed via `database/payment_migration.sql` on existing databases. Its fields record payment method, any legacy EFT reference, PayFast transaction ID, and confirmation timestamp. Credentials and bank configuration remain private in `server/config.php`; no payment credentials are embedded in JavaScript or Kotlin. See `README.md` for local simulated testing and PayFast HTTPS prerequisites.


**Current payment contract:** `checkout` requires `payment_method="payfast"` for genuine money or `"demo"` for opt-in localhost simulations. `payment_start` initiates PayFast, `payment_demo_complete` simulates a test success ONLY from a localhost browser on the local WampServer host. The public payment callback alone confirms live payment. Any old `payment_verify` endpoint is removed. An order number is a collection credential only after payment_status is `paid`. The simulated method must not be counted as real revenue.
