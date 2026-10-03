# SI HRIS - Production Installation Guide

This package installs SI HRIS on a live Linux server **with no demo or sample data**. The admin e-mail and password are entered during setup. Allow about 30 minutes.

## What you need

| Item | Requirement |
|---|---|
| Server | Linux (Ubuntu 22.04/24.04 or Debian 12 tested layout), SSH access with `sudo` |
| PHP | **8.3 or newer**, with extensions: `bcmath ctype curl dom fileinfo gd intl mbstring openssl pdo_mysql tokenizer xml zip` |
| Database | MySQL 8 or MariaDB 10.6+ (an empty database) |
| Web server | Apache 2.4 (with `mod_rewrite`, `mod_headers`, `mod_ssl`) or nginx + PHP-FPM |
| HTTPS | **Mandatory.** A domain name and a TLS certificate (e.g. Let's Encrypt). The app forces https links in production. |
| Mail | An SMTP account (for notifications, e-mail 2FA codes, scheduled reports) |
| Not needed on the server | Composer, Node.js, npm - everything is pre-built in the package. |

Ubuntu quick install of the prerequisites (Apache + mod_php variant):

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php8.3 php8.3-{bcmath,curl,gd,intl,mbstring,mysql,xml,zip} mariadb-server unzip
sudo a2enmod rewrite headers ssl
```

## Step 1 - Upload and verify the package

```bash
scp hris-production-*.zip hris-production-*.zip.sha256 you@your-server:/tmp/
ssh you@your-server
cd /tmp && sha256sum -c hris-production-*.zip.sha256     # must print: OK
```

## Step 2 - Extract to the application folder

```bash
sudo mkdir -p /var/www/hris
sudo unzip -q /tmp/hris-production-*.zip -d /tmp/hris-unpack
sudo cp -a /tmp/hris-unpack/hris-production-*/. /var/www/hris/
sudo rm -rf /tmp/hris-unpack
```

The web server's document root will be `/var/www/hris/public` - never the folder above it.

## Step 3 - Create the database

```bash
sudo mysql
```
```sql
CREATE DATABASE hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hris'@'localhost' IDENTIFIED BY 'CHOOSE-A-STRONG-DB-PASSWORD';
GRANT ALL PRIVILEGES ON hris.* TO 'hris'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Step 4 - Configure the environment file

```bash
cd /var/www/hris
sudo cp .env.production.example .env
sudo nano .env
```

Set at least these values (everything else has safe production defaults):

| Key | Value |
|---|---|
| `APP_URL` | `https://your-domain` (no trailing slash) |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | the values from step 3 |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | your SMTP account. **Mail is sent using these `.env` values, not the SMTP fields on the Settings page.** |

Leave `APP_KEY`, `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` empty - the installer generates them.

## Step 5 - Set file ownership and permissions

The web-server user (`www-data` on Ubuntu/Debian) must own the files it writes to. Replace `www-data` if yours differs.

```bash
cd /var/www/hris
sudo chown -R www-data:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
sudo chmod +x artisan
sudo chmod 640 .env
```

## Step 6 - Configure the web server

**Apache:** copy and edit the sample, then enable it.

```bash
sudo cp deploy/apache-hris-production.conf /etc/apache2/sites-available/hris.conf
sudo nano /etc/apache2/sites-available/hris.conf     # set your domain and certificate paths
sudo a2ensite hris.conf && sudo systemctl reload apache2
```

**nginx:** use `deploy/nginx-hris.conf` the same way (PHP-FPM socket path may differ).

Get a certificate if you do not have one yet, e.g. `sudo apt install certbot python3-certbot-apache && sudo certbot --apache -d your-domain`.

**Upload limits:** photos and documents up to 10 MB are supported. With Apache + mod_php this is already set in `public/.htaccess`. With PHP-FPM it is set in `public/.user.ini` (allow up to 5 minutes for PHP to pick it up) - or set `upload_max_filesize = 12M` and `post_max_size = 50M` in your FPM `php.ini` and restart PHP-FPM. nginx also needs `client_max_body_size 50M` (already in the sample).

## Step 7 - Run the installer (creates the admin account)

Run it as the web-server user so the files it creates are writable by the web server:

```bash
cd /var/www/hris
sudo -u www-data php artisan hris:install
```

The installer will:

1. Check PHP version/extensions, folder permissions, `.env` (production, debug off, https URL) and the database connection - and stop with a clear message if anything is wrong.
2. Generate the application key and web-push keys.
3. Create all database tables.
4. Create the **system data the application cannot run without** - roles and the permission matrix, approval workflows, country/nationality lists, renewal reminder types and the confidential Grievance/Whistleblower helpdesk category. **No employees, leave types, expense types, departments, locations or any other sample data is created.**
5. **Ask you for** the company name, admin first and last name, **admin e-mail (the login)** and **admin password** (typed twice, hidden). The password must satisfy the default policy (8+ characters, upper-case, lower-case, number, and not easily guessable); weak passwords are re-prompted.
6. Create the Admin account (with its own employee record) and cache the configuration for speed.

It refuses to run again once an account exists, so it cannot overwrite a live system.

**Immediately afterwards, back up `/var/www/hris/.env` somewhere safe.** If `APP_KEY` is lost, encrypted data (2FA secrets, government ID numbers, stored passwords) cannot be recovered.

For automated provisioning, the installer can run without prompts:
`HRIS_ADMIN_PASSWORD='...' sudo -E -u www-data php artisan hris:install --no-interaction --company="Acme" --admin-first-name=Ada --admin-last-name=Lovelace --admin-email=ada@acme.com`

## Step 8 - Schedule the background jobs (required)

The application runs daily jobs (renewal reminders, leave carry-over, scheduled reports, pulse surveys, log retention). Install the cron entry:

```bash
sudo cp /var/www/hris/deploy/hris-cron /etc/cron.d/hris
sudo chmod 644 /etc/cron.d/hris
```

(Edit the path/user inside if you installed elsewhere or your web user is not `www-data`.) No queue worker is required.

## Step 9 - Verify

1. Open `https://your-domain/up` - it should show a healthy page.
2. Open `https://your-domain`, sign in with the admin e-mail and password you chose in step 7.
3. Test outgoing mail from the server:
   ```bash
   cd /var/www/hris
   sudo -u www-data php artisan tinker --execute="Mail::raw('HRIS mail test', fn(\$m) => \$m->to('you@yourdomain.com')->subject('HRIS test'));"
   ```
   If nothing arrives, re-check the `MAIL_*` values, then run `sudo -u www-data php artisan config:cache`.
4. Log files: `/var/www/hris/storage/logs/`. If you see a blank "500" page, look there first.

## Step 10 - First-login configuration (as Admin)

The system starts empty. In this order:

1. **Settings** - company details, password policy, employee ID format, idle-session timeout.
2. **HR Admin > Organization & Master Data** - sub-units/departments, job titles, locations, job categories, employment statuses.
3. **HR Admin > Leave Configuration** - leave types, entitlements, holidays. **Claims Management** - expense types and claim events.
4. **Employees** - add staff (employee IDs generate automatically) and link supervisors.
5. **Roles / user accounts** - give HR Admins and other users their logins and roles.
6. Turn on **two-factor authentication** (Settings) only after step 9's mail test succeeds, because e-mail codes depend on working mail.
7. **Content-Security-Policy:** it ships in `report-only` mode (nothing is blocked). After a few weeks with no unexpected entries in the log, you may set `CSP_MODE=enforce` in `.env` and run `sudo -u www-data php artisan config:cache`.

## Backups (set up before go-live)

Back up daily, and keep copies off the server:

```bash
mysqldump --single-transaction -u hris -p hris | gzip > hris-$(date +%F).sql.gz
tar czf hris-files-$(date +%F).tgz -C /var/www/hris .env storage/app
```

Test a restore at least once.

## Upgrading to a newer package

1. Back up (database + `.env` + `storage/app`).
2. `sudo -u www-data php artisan down`
3. Extract the new package over `/var/www/hris`, **keeping** your `.env` and `storage/` (the package contains neither), then re-run the ownership commands from step 5.
4. `sudo -u www-data php artisan migrate --force && sudo -u www-data php artisan optimize`
5. `sudo -u www-data php artisan up`

Do **not** run `hris:install` again on an existing system.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Installer: "Writable: storage ... FAIL" | Re-run step 5 (ownership/permissions) |
| Installer: "APP_URL uses https:// ... FAIL" | Set a full `https://` URL in `.env` |
| Installer: database connection fails | Check `DB_*` in `.env`; confirm the user/database from step 3 |
| 404 on every page except the home page | Apache `AllowOverride All` / `mod_rewrite` missing, or nginx `try_files` line missing |
| 500 error after changing `.env` | `sudo -u www-data php artisan config:cache` (or `config:clear`), then check `storage/logs` |
| "failed to upload" on photos/documents | PHP upload limits not applied - see step 6 "Upload limits" |
| Redirect loop / mixed content behind a load balancer or Cloudflare | Make sure the proxy forwards `X-Forwarded-Proto: https` |
| Forgot admin password | Use "Forgot password" on the sign-in page (needs working mail), or `sudo -u www-data php artisan tinker` and set a new one via the User model |

## What is in the package

Application code, pre-built front-end assets, production-only PHP libraries (no dev tools), the database migrations, the system-data seeders, and the `deploy/` folder (Apache, nginx and cron samples). The package contains no `.env`, no database, no logs and no demo accounts.
