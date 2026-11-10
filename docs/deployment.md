# Free hosting deployment

## Recommended host

For a no-card, non-trial deployment, the closest fit verified for this Laravel
application is [alwaysdata's Free offer](https://www.alwaysdata.com/en/offers/).
It includes managed PHP hosting and MariaDB, and its documentation confirms
PHP 8.1+, Composer, SSH, Apache with `.htaccess`, and MariaDB 10.11 or newer.
The Free offer is advertised for personal needs; confirm that your intended
use is permitted before creating an account.

This is a constrained shared-hosting tier, not a production-grade service
commitment: it provides 1 GB disk, 256 MB RAM, one quarter CPU, and three days
of backups. Do not put real member or payment data there unless the responsible
organization approves the provider, data handling, and backup risk. Keep an
independent, encrypted backup of any database that contains real records.

References:

- [alwaysdata offers and limits](https://www.alwaysdata.com/en/offers/)
- [PHP versions and configuration](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/configuration/)
- [SSH access](https://help.alwaysdata.com/en/docs/web-hosting/remote-access/ssh/)
- [MariaDB versions and connection details](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/)
- [Alwaysdata backups](https://help.alwaysdata.com/en/docs/web-hosting/backups/)

## Hosting configuration

1. Create an alwaysdata account using the Free offer and confirm its terms fit
   the intended use. Create a PHP site and select PHP 8.1 or later. The account
   default PHP version also controls the `php` command used over SSH.
2. Deploy this repository into a directory outside the web document root. Set
   the website's document root to the repository's `public` directory only.
   Never make the repository root, `.env`, `vendor`, `storage`, or `bootstrap`
   directly web-accessible.
3. Use SSH/SFTP to access the account. Install the Composer dependencies for
   production and create the Vite production assets with `npm ci` and
   `npm run build`. `public/build` is intentionally ignored by Git, so it must
   be built as part of deployment or transferred as a deployment artifact.
4. Create a MariaDB database and a dedicated database user in the hosting
   control panel. Use the exact host, database name, and username shown there;
   the host is account-specific and is not `127.0.0.1`.
5. Configure the production environment in a private `.env` file outside the
   document root (or in the host's protected environment settings):

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-hostname
   APP_KEY=base64:...

   DB_CONNECTION=mysql
   DB_HOST=mysql-your-account.alwaysdata.net
   DB_PORT=3306
   DB_DATABASE=your_account_database
   DB_USERNAME=your_account_user
   DB_PASSWORD=your-private-password

   CACHE_DRIVER=file
   SESSION_DRIVER=file
   QUEUE_CONNECTION=sync
   FILESYSTEM_DISK=local
   LIBRARY_EMAIL_ALERTS_ENABLED=false
   MAIL_MAILER=log
   ```

   Generate an application key only for a new application installation, then
   keep it private and stable. If this application already has users or
   encrypted data, do not rotate its existing key. Do not commit `.env` or
   credentials; the repository ignores `.env` files other than `.env.example`.
   Keep email alerts disabled until the mail transport is configured and
   verified.
6. Make `storage` and `bootstrap/cache` writable by the PHP account. Run
   `php artisan storage:link` after deployment if the public-storage symlink
   does not already exist. User-uploaded files and the private `.env` must
   remain on persistent storage between releases.
7. Enable the site's HTTPS certificate and redirect HTTP to HTTPS in the
   hosting control panel.
8. Configure a hosting cron entry for `php artisan schedule:run` once per
   minute if the loan and membership reminder commands should run. Review mail
   settings and recipients before enabling these commands for real users.

Laravel already reads production settings from environment variables, uses
file-backed cache/session defaults, and has MySQL/MariaDB connection settings.
No Dockerfile or container startup command is applicable to this PHP/Apache
host.

## Database safety and release checks

- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or a seeder against
  an existing database.
- This guide and the deployment preparation do not run migrations or connect
  to a database. Before any schema change, identify the target database,
  create and verify an independent backup, review pending migrations, and get
  explicit approval from the database owner.
- After configuration, verify `https://your-hostname/health` returns HTTP 200
  and `{"status":"ok"}`. This is a liveness check; it does not certify database
  availability or validate application login flows.
- Run the project tests against a dedicated disposable test database only.

## GitHub deployment

The manual workflow at `.github/workflows/deploy.yml` builds production PHP
dependencies and Vite assets, then transfers them over SSH. It intentionally
does not run migrations, seeders, or remote file deletion. It excludes `.env`
and `storage/` so server configuration, logs, sessions, and uploaded files are
not transferred from GitHub.

Before using it:

1. Add a GitHub `production` environment, restrict deployments to the
   deployment branch, and require a reviewer if the repository's plan supports
   environment approvals.
2. Add these environment secrets to that GitHub environment:
   `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY`, and
   `DEPLOY_KNOWN_HOSTS`. Verify the SSH host key's fingerprint against the
   hosting control panel before storing the corresponding `known_hosts` line.
   Do not trust an unverified `ssh-keyscan` result.
3. Configure the host's PHP CLI version and web site to use the same release
   path, with the web document root set to `<DEPLOY_PATH>/public`. Create the
   private production `.env` on the host before triggering deployment. Make
   sure `DEPLOY_PATH` is a directory the SSH user and PHP process can write to.
4. Trigger **Deploy to alwaysdata** manually from the intended branch after
   reviewing the exact commit. The workflow refreshes Laravel config and view
   caches but does not touch the database schema.

No hosting account, SSH key, environment values, or production database was
provided, so this workflow has not been run and no deployment has been made.
Never put host credentials or production environment values in the repository
or paste them into chat.
