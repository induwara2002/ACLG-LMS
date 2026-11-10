# Library Management System

A Laravel 10 application for managing a network of Pradeshiya Sabha libraries. It uses Blade views, MySQL, and Vite for frontend asset builds.

## Requirements

- PHP 8.1 or later supported by the installed Laravel 10 dependencies, with the required Laravel PHP extensions and PDO MySQL
- Composer
- MySQL
- Node.js and npm

## Application roles

- **Main/Super Admin:** administers Sabha records and system-wide configuration, with cross-Sabha reporting and read-only catalog access.
- **Pradeshiya Sabha Admin:** manages members, catalog, circulation, payments, and settings for their assigned Sabha.
- **Librarian:** handles day-to-day member services, catalog lookups, issuing, returns, renewals, fines, and reports within their Sabha.
- **Member:** signs in to the member portal to browse their Sabha's catalog, review their membership and circulation records, receive alerts, renew eligible loans, and reserve unavailable books.

Public member applications are created in a pending state and require Sabha staff approval before circulation services are available. Staff access to records and physical inventory is scoped to the user's Sabha; Main Admin access to operational records is controlled separately from their read-only catalog access.

## Current features

- Main Admin and Sabha administration, Sabha settings, assignments, and audit logs
- Separate staff and member dashboards, localized interfaces, and role-based authentication
- Book catalog, authors, publishers, categories, physical copies, and Sabha-scoped inventory
- Member applications, approvals, membership types, renewals, and account management
- Book issue, return, loan renewal, unavailable-book reservations, fines, payments, and lost/damaged copy records
- Reports, Google Books ISBN lookup, email alerts, and scheduled due-date and membership-expiry reminders

The application timezone is `Asia/Colombo`. Email and reminder configuration is documented in [docs/email-alerts.md](docs/email-alerts.md). Backup and member-data protection guidance is in [docs/backup-and-data-protection.md](docs/backup-and-data-protection.md).

Free hosting guidance and production deployment checks are documented in
[docs/deployment.md](docs/deployment.md).

## Local setup

1. Copy `.env.example` to `.env` and configure a **dedicated local development database** and application URL.
2. Install PHP dependencies:

   ```powershell
   composer install
   ```

3. Generate an application key for a new local installation:

   ```powershell
   php artisan key:generate
   ```

4. Install the exact frontend dependency versions from the npm lockfile and build production assets:

   ```powershell
   npm ci
   npm run build
   ```

5. Apply migrations only to a new or explicitly approved local database:

   ```powershell
   php artisan migrate
   ```

   Do not point setup or test commands at a database containing library records unless the migration plan has been reviewed and the data backed up. Never use `migrate:fresh` on a database with records.

6. Make sure `storage` and `bootstrap/cache` are writable by the PHP process, then start the local server:

   ```powershell
   php artisan serve
   ```

The public home page is `/`, staff sign in at `/login`, and authenticated users are sent to the dashboard for their role. Configure real mail transport and the Laravel scheduler before enabling automated email reminders.

## Tests

Run the PHP test suite with:

```powershell
php artisan test
```

`phpunit.xml` targets the MySQL database `library_management_test`, and feature tests use Laravel's `RefreshDatabase` trait. Create and configure this as a **dedicated disposable test database**: the test runner rebuilds its schema. Never run the suite against a live or data-bearing database. Frontend assets can be checked separately with:

```powershell
npm run build
```

The JavaScript ISBN lookup tests are in `tests/js`; the project currently has no npm test script.
