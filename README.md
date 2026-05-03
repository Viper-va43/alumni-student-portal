# Where2Go

Where2Go is a PHP and MySQL web app for discovering places, registering customers, onboarding partner businesses, booking visits, and issuing QR-based rewards.

## Technology Stack

- Backend: PHP 8.x
- Database: MySQL or MariaDB
- Frontend: HTML, CSS, JavaScript
- Local server: XAMPP Apache and MySQL

## Project Structure

```text
Where2Go/
├── admin/
├── assets/
├── config/
├── database/
├── includes/
├── pages/
├── Home.php
└── index.php
```

## Local Setup

1. Clone or place the project in `C:\xampp\htdocs\Where2Go`.
2. Start Apache and MySQL from XAMPP.
3. Create a database named `where2go`.
4. Copy `config/database.example.php` to `config/database.php` if `config/database.php` is missing.
5. Update the database credentials in `config/database.php` only if your local MySQL user is not the default XAMPP `root` user with no password.
6. Visit `http://localhost/Where2Go/Home.php`.

## Useful Pages

- Customer home: `http://localhost/Where2Go/Home.php`
- Legacy landing page: `http://localhost/Where2Go/index.php`
- Customer login: `http://localhost/Where2Go/login.php`
- Partner login: `http://localhost/Where2Go/partner-login.php`
- Partner registration: `http://localhost/Where2Go/partner-register.php`

## Notes

- `config/database.php` is intentionally ignored by Git because it may contain local credentials.
- The reward tables can be seeded with `database/reward-system-example-data.sql` after replacing the sample IDs with real customer, business, and location IDs.
- Keep `config/maps.local.php` local if you add a Google Maps API key.
