# Foody

A restaurant record management system built with **native PHP**, MariaDB, HTML, CSS and JavaScript. The project runs locally through XAMPP without a PHP framework or frontend build step.

## Features

| Area | Functionality |
| --- | --- |
| Login / Logout | Account authentication and session management. |
| Main Page | Select a restaurant and open its service page using the preferred duration. |
| Service | Enter and save RM amounts for 7 days (Weekly) or 4 weeks (Monthly). |
| Report | Save report amounts separately by restaurant and duration. |
| Commission | Add or update commission, penalties and total delivery costs. |
| My Profile | Update your name, email and password. |
| My Restaurant | Update the three existing restaurants, with two restaurants per page. |
| Order List | Add manual orders, filter by restaurant, browse pages and update order status. |
| Settings | Choose a default restaurant, duration, orders per page, date format and appearance. |
| Help Center | Usage instructions and explanations of common errors. |

The sidebar follows page scrolling and stays vertically centered in the viewport. On short screens, the sidebar itself can scroll so every button remains accessible.

## Requirements

- XAMPP with Apache and MariaDB.
- PHP with the `mysqli` / `mysqlnd` and `mbstring` extensions. Development and testing use PHP 8.4.
- Apache `mod_rewrite` and `AllowOverride All` for the `.htaccess` rules.
- Node.js 24 to run JavaScript, HTTP and browser tests; it is not required to use the application.

## Local setup

### 1. Place the project in XAMPP

Place the project folder at:

```text
C:\xampp\htdocs\Foody
```

Start **Apache** and **MySQL** through the XAMPP Control Panel. The MySQL service in this installation uses MariaDB.

### 2. Set up the database

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `foody` with the `utf8mb4` character set.
3. Select that database and import [database/foody.sql](database/foody.sql).

This file contains the complete schema, the appearance settings upgrade and the initial names of the three restaurants. Repeated imports retain existing records and edited restaurant details. An existing installation does not need another import simply because the SQL file has moved.

**The SQL file is not a backup of your current data.** Accounts, orders and financial records from another installation are not included. Use phpMyAdmin's Export feature to back up those records.

### 3. Check the database connection

The connection in `service_functions.php` reads these environment variables:

| Variable | Local default |
| --- | --- |
| `FOODY_DB_HOST` | `localhost` |
| `FOODY_DB_PORT` | `3306` |
| `FOODY_DB_NAME` | `foody` |
| `FOODY_DB_USER` | `root` |
| `FOODY_DB_PASSWORD` | Empty |

For a different configuration, set the variables for the relevant Apache process and CLI terminal. The project does not load `.env` files automatically. These defaults are intended for local XAMPP setup.

### 4. Create the first account

Open PowerShell in the project folder:

```powershell
Set-Location C:\xampp\htdocs\Foody
$env:Path = 'C:\xampp\php;' + $env:Path
.\scripts\create-account.ps1
```

Enter your name, email, password and password confirmation when prompted. New passwords must be **12 to 72 bytes** long.

The script only creates the first account when the database has no accounts. If an account already exists, sign in with that account. The SQL file does not provide a default account or password, and public registration is not available.

### 5. Open the application

```text
http://localhost/Foody/
```

This address opens Main Page when you are signed in, or redirects you to Login. You can also open Login directly at `http://localhost/Foody/login.php`.

## How data is saved

- **Service / Report:** `Calculation` saves all amounts for the selected restaurant and duration, then opens the commission page.
- **Commission:** `Add` creates a record; `Update` changes the existing record for that restaurant, module and duration.
- **My Profile / My Restaurant / Settings:** changes are saved when the corresponding save button submits the form. `Reset to Defaults` in Settings saves the default preferences.
- **Order List:** `Add` creates a Pending order. `Update` changes Pending to Completed or Cancelled; both are final statuses.
- **Delete on Service / Report / Commission forms:** resets the form to the values loaded when the page opened; it does not delete database records.

Money calculations use integer cents. Inputs accept up to two decimal places. NETT is recalculated on the server:

```text
NETT Commission = Total Commission - Penalties - Total Delivery Cost
```

The `Delivery per Services` field represents the **total delivery cost in RM**, rather than a delivery count. NETT can be negative when deductions exceed commission.

## Current scope

- All signed-in users share access to business records; role-based access has not been implemented.
- The three existing restaurants can be updated. Adding and deleting restaurants are not available.
- `Services Completed` and `Total Reports` are still sample figures.
- Weekly and Monthly each store one set of values per restaurant and module; dated weekly or monthly history is not available.
- Total Commission is entered manually. Service / Report totals are not automatically transferred as commission.
- Orders do not automatically change the sample Service / Report counts or commission amounts.
- Settings are saved per account. Light supports Solid / Transparent panels; Dark uses solid panels. Login remembers the last appearance used in that browser.

## Code structure

```text
database/foody.sql        Consolidated schema and settings upgrade
scripts/                 CLI scripts for initial account creation
tests/                   PHP, JavaScript, HTTP and browser tests
*_functions.php          Validation, calculations and database access
*_controller.php         Request handling for the corresponding modules
sidebar.php / sidebar.js Shared sidebar and positioning behavior
appearance_functions.php Appearance preferences and appearance cookie handling
theme.css / account.css  Theme and account page styles
.htaccess                Application entry point and internal file access restrictions
```

Database queries use prepared statements. Forms that change data have CSRF protection, and relevant updates reject stale records or forms. Passwords are stored as hashes; changing an email or password requires the current password and revokes older sessions.

## Running tests

Run these commands from the project folder after setting up the database. Add XAMPP PHP to `PATH` as shown in the account creation instructions above.

```powershell
php tests/auth_test.php
php tests/service_test.php --database
php tests/report_test.php --database
php tests/commission_test.php --database
php tests/restaurant_test.php
php tests/order_test.php
php tests/settings_test.php
php tests/appearance_test.php
node tests/service_js_test.mjs
node tests/commission_js_test.mjs
```

The database tests above use temporary tables to isolate test records from the real tables.

For HTTP tests, Apache must be running at `http://localhost/Foody/`:

```powershell
php tests/run_authenticated_tests.php
```

The HTTP runner creates temporary accounts and orders, tests renaming the Jamilah restaurant, then cleans up fixtures and restores the restaurant details. Run it on a development installation without concurrent editing. HTTP tests are restricted to a local database named `foody`.

Browser tests for appearance and sidebar behavior require a separate headless Edge instance with debugging port `9237`. PowerShell example:

```powershell
$foodyBrowserProfile = Join-Path $env:TEMP ('foody-browser-' + [guid]::NewGuid().ToString('N'))
$foodyBrowserArgs = @(
    '--headless=new'
    '--remote-debugging-port=9237'
    ('--user-data-dir="' + $foodyBrowserProfile + '"')
    '--no-first-run'
    '--no-default-browser-check'
    'about:blank'
)
$foodyBrowser = Start-Process -FilePath 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' -ArgumentList $foodyBrowserArgs -WindowStyle Hidden -PassThru
try {
    php tests/run_authenticated_tests.php --appearance-browser
} finally {
    Stop-Process -Id $foodyBrowser.Id -ErrorAction SilentlyContinue
}
```

Adjust the Edge executable path if your installation differs. Test screenshots are saved in the `foody-theme-screenshots` folder within the Windows temporary directory.

## Troubleshooting

- **Database or login unavailable:** ensure MariaDB is running, the schema has been imported, and Apache / CLI configurations point to the same database. Check the PHP / Apache logs for technical details.
- **Form expired / record changed:** reload the page, review the latest values and reapply your intended changes.
- **Too many attempts:** wait up to 15 minutes before trying again.
- **Redirected to Login:** sessions expire after 30 minutes of inactivity or 12 hours, or are revoked after an email / password change.
- **HTTP 500 after Apache setup:** check that `mod_rewrite` and `AllowOverride All` are enabled for the project directory.
- **SQL files / scripts / tests return 404 in the browser:** access is intentionally restricted. Import SQL through phpMyAdmin and run scripts and tests through the terminal.
