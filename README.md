# Library Management System

A local library management system for a single library administrator or librarian.

## Description

This project is designed for a local library environment where only the administrator/librarian can access the system. Members are managed from the admin panel and do not have login accounts or self-registration.

## Technology Stack

- Laravel
- PHP
- MySQL
- Blade
- Bootstrap 5

## Authentication Model

- Only one authenticated user role is used: administrator/librarian
- Members are not system users
- Members are managed manually from the admin panel
- No public member login or member registration is available

## Current Phase

This project is currently in Phase 1 architecture setup with admin-only authentication and placeholder management screens.

## Project Setup

1. Copy `.env.example` to `.env`
2. Configure MySQL settings in `.env`
3. Run `composer install`
4. Run `php artisan key:generate`
5. Run `php artisan serve`

## Admin Access

- Public home page: `/`
- Admin login page: `/login`
- Admin dashboard after login: `/admin/dashboard`

## Notes

- Members are not authenticated users.
- Member self-registration is not implemented.
- The admin manages member records directly through the admin panel.
- Business logic and database implementation will be added in later phases.

## Dashboard and circulation dates

The application timezone is `Asia/Colombo`. Dashboard clocks are initialized from the server timestamp and update every second in the browser. Issue and return timestamps are recorded by the server; the existing configured issue period determines due dates, and membership expiry falls back to one year from membership start or registration when an older record has no stored expiry date.

Before deploying circulation timestamp changes, apply pending migrations with `php artisan migrate`. Do not use `migrate:fresh` on a database containing library records.

## Email Alerts

The system sends welcome and book-issue messages after successful database commits. Daily commands send once-per-period due-date and membership-expiry reminders. Configure SMTP and reminder windows in `.env` as described in [docs/email-alerts.md](docs/email-alerts.md). The scheduler is registered in `app/Console/Kernel.php`; migrations must be applied before running the reminder commands.
