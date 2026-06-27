# Where2Go Security Scan Report

Date: 2026-06-22

## Scope

- Primary scope: `api/mobile/*.php` routing layer.
- Supporting scope: customer and partner session helpers in `includes/functions.php`.
- Threat model focus: unvalidated request input, SQL injection risk, broken authentication, and user session management.

## Executive Summary

The scan found one critical mobile API issue: customer-specific endpoints trusted `customer_id` values supplied by the app. Before patching, an attacker could change `customer_id` and read or modify another customer's profile, saved places, rewards, bookings, QR check-ins, or profile photo.

Critical ownership checks have been patched by adding mobile bearer tokens and enforcing token-to-customer matching on protected API routes.

## Attack Paths Simulated

| Attack path | Before patch | After patch |
| --- | --- | --- |
| Use customer A token/request to read customer B profile | Customer data could be exposed because `customer_id` was trusted | Blocked with `403` |
| Write saved place data for another customer | Another customer's saved list could be changed | Blocked with `403` |
| Read bookings without authentication | Booking history could be requested by customer ID | Blocked with `401` |
| Submit account actions without a token | Customer-specific mutations could be attempted by ID | Blocked with `401` |
| Legitimate owner request | Should work | Still works with `200` |

## Findings And Remediation

### Critical: Broken Mobile API Authorization

Affected files:

- `api/mobile/profile.php`
- `api/mobile/profile-photo.php`
- `api/mobile/reservations.php`
- `api/mobile/rewards.php`
- `api/mobile/saved.php`
- `api/mobile/scan.php`

Risk:

The mobile API accepted `customer_id` from request bodies and query strings without proving that the caller owns that customer account.

Patch applied:

- Added `api/mobile/security.php`.
- `auth.php` now issues a 30-day random bearer token after login/register.
- Tokens are stored only as SHA-256 hashes in `mobile_api_tokens`.
- Protected endpoints now call `where2go_mobile_security_require_customer(...)`.
- The Expo app stores the token in `AsyncStorage` and sends it in `Authorization: Bearer ...` for customer-specific requests.

### High: Session Fixation Defense In Shared Login Helpers

Affected file:

- `includes/functions.php`

Risk:

`login.php` and `partner-login.php` already regenerated session IDs, but the shared helper functions did not. Future code calling the helpers directly could skip session ID rotation.

Patch applied:

- Added `session_regenerate_id(true)` inside `login_user(...)`.
- Added `session_regenerate_id(true)` inside `login_partner_user(...)`.

### Medium: CORS Is Still Development-Friendly

Affected file:

- `api/mobile/security.php`

Current state:

The mobile API still uses `Access-Control-Allow-Origin: *` to keep Expo Go, tunnel testing, and local development working.

Recommended before launch:

- Restrict allowed origins to trusted production app/web domains.
- Keep `Authorization` allowed in request headers.
- Review tunnel exposure before sharing preview links publicly.

### Medium: Missing Login Rate Limiting

Affected file:

- `api/mobile/auth.php`

Current state:

Login input is validated and passwords are verified with existing password helpers, but repeated login attempts are not rate limited.

Recommended before launch:

- Add per-email and per-IP throttling.
- Add temporary lockout or progressive delay after repeated failures.

## SQL Injection Review

The mobile API uses prepared statements for dynamic customer-specific SQL. The remaining `$conn->query(...)` calls in the reviewed mobile routing layer use static SQL strings or schema creation SQL, not raw request values.

No critical SQL injection issue was found in the patched mobile API scan.

## Verification Evidence

Automated checks run after patching:

```text
PHP lint:
No syntax errors detected in api/mobile/security.php
No syntax errors detected in api/mobile/auth.php
No syntax errors detected in api/mobile/profile.php
No syntax errors detected in api/mobile/profile-photo.php
No syntax errors detected in api/mobile/reservations.php
No syntax errors detected in api/mobile/rewards.php
No syntax errors detected in api/mobile/saved.php
No syntax errors detected in api/mobile/scan.php
No syntax errors detected in api/mobile/places.php
No syntax errors detected in includes/functions.php

TypeScript:
node node_modules/typescript/bin/tsc --noEmit
Passed
```

Final dynamic proof loop:

```json
{
  "ok": true,
  "checks": {
    "own_profile_allowed": 200,
    "cross_profile_blocked": 403,
    "missing_token_saved_blocked": 401,
    "cross_saved_blocked": 403,
    "owner_saved_allowed": 200,
    "missing_token_bookings_blocked": 401,
    "cross_bookings_blocked": 403
  }
}
```

Temporary scan accounts were deleted after verification.

## Recommended Next Steps

1. Add login rate limiting before external beta testing.
2. Restrict CORS origins before production launch.
3. Add token revocation/logout server-side if long-lived sessions become a concern.
4. Add automated API security tests to the project so ownership checks stay protected as routes grow.
