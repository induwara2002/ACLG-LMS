# Email Alerts

## SMTP configuration

Set these values in the deployment's `.env`; do not commit that file or place credentials in source code:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=library@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

Use the SMTP host, username, and password issued by your provider. Port 587 with TLS is common; some providers require port 465 with SSL (`MAIL_ENCRYPTION=ssl`). Follow the provider's current SMTP instructions. For local development, the supplied `.env.example` uses Mailpit (`mailpit:1025`) and can be paired with a local Mailpit instance.

Email alerts and reminder windows are configurable:

```dotenv
LIBRARY_EMAIL_ALERTS_ENABLED=true
LIBRARY_DUE_DATE_REMINDER_DAYS=2
LIBRARY_MEMBERSHIP_EXPIRY_REMINDER_DAYS=7
```

Both reminder values mean the number of days before the due/expiry date. The commands run for the exact date `today + window`. Set email alerts to `false` to disable delivery. Changes to `.env` require `php artisan config:clear` when configuration is cached.

## Safe testing

Automated tests use `Mail::fake()` and never deliver real email:

```powershell
php artisan test --filter=EmailAlertsTest
```

For a manual end-to-end check, use a local Mailpit server or a dedicated test inbox and test credentials. Do not use member addresses or production SMTP credentials in development. To preview rendered messages without SMTP, set `MAIL_MAILER=log` locally and inspect the application's log using synthetic member data; logs may contain message content.

## Migrations and commands

Apply the persistent deduplication table migration:

```powershell
php artisan migrate
```

Run reminder commands manually:

```powershell
php artisan library:remind-loans
php artisan library:remind-membership-expiry
```

The `email_alert_deliveries` table has a unique key for alert type, entity, and due/expiry period. Re-running a command does not send an already claimed reminder again. Mail transport errors are logged; a failed attempt releases its claim so a later run can retry. A process termination while a claim is pending can leave it in place; this favors avoiding duplicate email over automatic retry when delivery is ambiguous.

## Scheduler

Both reminders are scheduled daily at 08:00 using the application's scheduler timezone and `withoutOverlapping()`.

- **Local Windows:** keep `php artisan schedule:work` running in a terminal during development, or create a Windows Task Scheduler task that runs every minute. Set the working directory to the project root, program to the PHP executable, and arguments to `artisan schedule:run`.
- **Production Linux/Unix:** configure the hosting cron service to run `cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1` every minute. Ensure the PHP binary and project path match the deployed environment.
- Verify configured schedules with `php artisan schedule:list`.

The application does not introduce a queue, so no queue worker is required. SMTP access and the Laravel scheduler (local process, Task Scheduler, or cron) are required for automatic reminders.
