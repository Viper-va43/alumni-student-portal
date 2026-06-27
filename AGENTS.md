# Where2Go Project Memory

Future AI and coding agents must read this file before editing the project.

Do not remove existing working features without explicit permission. Keep the current orange, white, and black visual direction unless a change is required for a bug fix, accessibility, responsiveness, or security.

## Tech Stack

- PHP web app running on XAMPP Apache.
- MariaDB/MySQL database named `where2go`.
- Mixed database access: shared mysqli helpers in `includes/functions.php`, plus PDO in `config/database.php` for public auth pages.
- Frontend is server-rendered PHP with vanilla HTML, CSS, and JavaScript.
- Mobile app and mobile API support live under `mobile/` and `api/mobile/`.

## Important Paths

- `includes/functions.php`: shared session, auth, business, booking, reward, CSRF, rate-limit, and database helper logic.
- `includes/place_data.php`: catalog place and catalog review data helpers.
- `includes/rewards.php`: reward/check-in related helpers.
- `config/database.php`: PDO database connection used by login/register style pages.
- `login.php`, `register.php`, `logout.php`, `profile.php`: customer account flow.
- `partner-login.php`, `partner-register.php`, `partner-dashboard.php`, `partner-business-form.php`: partner account and business management flow.
- `admin/`: admin dashboards and moderation/config pages.
- `api/mobile/`: token-based mobile endpoints.
- `assets/css/`: site CSS.
- `assets/js/`: browser JavaScript.
- `database/`: SQL helper scripts and migration-style notes.

## Active Database Tables

The app currently uses these runtime tables, based on the PHP queries and SQL files:

- `customers`
- `partners`
- `businesses`
- `business_locations`
- `business_hours`
- `business_photos`
- `business_menus`
- `business_offers`
- `bookings`
- `customer_saved_places`
- `customer_place_visits`
- `business_reviews`
- `catalog_places`
- `catalog_place_reviews`
- `daily_top_picks`
- `customer_checkins`
- `customer_rewards`
- `reward_boxes`
- `user_rewards`
- `login_attempts`

## User Roles

- Customer: can manage their own profile, bookings, saved places, visits, check-ins, and reviews.
- Partner: can manage only their own businesses, locations, hours, photos, menus, offers, and related bookings.
- Admin: can access admin pages, approve/reject businesses, configure rewards, and manage top picks.

Admin access is controlled by helper logic and the configured admin email list. Never add admin access by checking only a URL parameter or client-side field.

## Current Features

- Public discovery pages for places and businesses.
- Customer registration, login, logout, profile, saved places, bookings, reviews, visits, and rewards/check-ins.
- Partner registration, login, dashboard, booking status management, and business submission/editing.
- Admin business approvals, reward configuration, and top-pick management.
- Mobile API endpoints for auth, places, profile, reservations, rewards, saved places, and scanning.
- Business photo ordering where the first ordered photo is treated as the public primary photo.

## Security Rules

- Use prepared statements for all SQL that includes user-controlled values.
- Use `password_hash()` for new passwords and `password_verify()` for login checks.
- Show generic login errors so attackers cannot learn whether an email exists.
- Regenerate the session ID after successful login.
- Use CSRF tokens on browser-submitted POST forms and browser fetch POSTs.
- Rate limit repeated failed login attempts.
- Do not trust `user_id`, `customer_id`, `partner_id`, `business_id`, `location_id`, `booking_id`, or review IDs from URLs or forms without a backend ownership check.
- Customer actions must filter by the logged-in customer ID.
- Partner actions must filter by the logged-in partner ID and owned business records.
- Admin pages must require admin access server-side.
- Mobile API endpoints should use the mobile token security helpers.
- Log suspicious repeated failed login attempts without exposing sensitive details to the user.

## Coding Rules

- Inspect the existing file and helper patterns before editing.
- Keep edits scoped to the requested behavior.
- Prefer shared helpers in `includes/functions.php` for repeated security and validation logic.
- Avoid broad rewrites unless they are necessary to fix the requested issue.
- Preserve existing page design and navigation.
- Add comments only when the logic is not self-explanatory.
- Keep names consistent between PHP variables, form field names, and database columns.
- After changing PHP files, run `php -l` on the edited files.
- After changing JavaScript files, run a syntax check where Node.js is available.

