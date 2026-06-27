# Where2Go Developer Commands

Run commands from `D:\xampp\htdocs\Where2Go` unless noted otherwise.

## Local XAMPP Notes

- Start Apache and MySQL from the XAMPP Control Panel.
- Local app URL: `http://localhost/Where2Go/`
- Database name: `where2go`
- Default local database user: `root`
- Default local database password: empty

Useful paths:

- PHP: `D:\xampp\php\php.exe`
- MySQL client: `D:\xampp\mysql\bin\mysql.exe`
- MySQL dump: `D:\xampp\mysql\bin\mysqldump.exe`

## Git

```powershell
git status --short
git diff -- path\to\file.php
git diff --name-only
git add path\to\file.php
git commit -m "Describe the change"
git branch
git switch -c codex/my-change
git log --oneline -5
```

## PHP Checks

```powershell
D:\xampp\php\php.exe -l login.php
D:\xampp\php\php.exe -l register.php
D:\xampp\php\php.exe -l includes\functions.php
```

Check all PHP files outside common generated folders:

```powershell
Get-ChildItem -Recurse -File -Include *.php |
  Where-Object { $_.FullName -notmatch '\\node_modules\\|\\.git\\' } |
  ForEach-Object { & D:\xampp\php\php.exe -l $_.FullName }
```

## JavaScript Checks

```powershell
node --check assets\js\home.js
node --check assets\js\place-detail.js
node --check assets\js\partner-portal.js
```

## Database Backup And Restore

Back up the local database:

```powershell
D:\xampp\mysql\bin\mysqldump.exe -u root where2go > database\where2go-backup.sql
```

Restore or import a SQL file:

```powershell
Get-Content database\where2go-backup.sql | D:\xampp\mysql\bin\mysql.exe -u root where2go
```

Run a one-off SQL query:

```powershell
D:\xampp\mysql\bin\mysql.exe -u root where2go -e "SHOW TABLES;"
```

## Debugging Checklist

- Confirm Apache and MySQL are running in XAMPP.
- Open the browser developer console and check network errors.
- Check PHP syntax with `php -l` after editing PHP files.
- Confirm form `name` attributes match the PHP `$_POST` keys.
- Confirm IDs from forms or URLs are checked against the logged-in user or partner before update/delete actions.
- Check Apache/PHP error logs if a page is blank.
- Confirm uploaded files are saved under the expected `assets/images/uploads/` path.
- Test desktop and mobile widths after changing page layout.

## Database Safety Checklist

- Use prepared statements for user-controlled values.
- Back up the database before manual schema changes.
- Prefer idempotent schema helpers or migration scripts.
- Keep foreign key ownership clear: customers own customer records, partners own businesses, and businesses own locations/photos/menus/offers.

## Deployment Checklist

- Move database credentials out of committed files or configure them per environment.
- Set secure session cookie settings and HTTPS on hosting.
- Confirm file upload limits and upload directory permissions.
- Disable display errors in production and log errors server-side.
- Import required SQL schema/data before first launch.
- Test login, register, logout, profile edit, partner dashboard, admin pages, bookings, reviews, saved places, check-ins, and mobile API auth.
- Confirm all admin-only pages reject non-admin users.

