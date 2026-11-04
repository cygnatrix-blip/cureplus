# Deploying CurePlus on Hostinger

For whoever installs or updates the production site. The target is Hostinger Business shared hosting with LiteSpeed and MariaDB, on `cureplus.cygnatrix.com`.

> **Do not use `medimind.cygnatrix.com`.** That site runs the live MediMind pharmacy product. CurePlus has its own website, folder and database.

## What the host allows

Checked on 3 November 2026 for the web PHP on this account. Check the CLI PHP yourself in step 1, because it can differ.

| Fact | What it means here |
|---|---|
| `proc_open`, `exec`, `shell_exec`, `system`, `popen` are disabled | Scheduled tasks run in-process and backups are PHP-only. Do not use `Schedule::command()` |
| `symlink` is disabled in PHP | `php artisan storage:link` fails. Files are served through signed routes, so it is not needed |
| No Node | `public/build` is committed to Git. Build locally before committing frontend changes |
| No Redis, no Supervisor, no daemons | Database queue, cache and sessions. One cron entry does everything |
| PHP 8.4 is selected, 8.5 is offered | Stay on 8.4 until the dependencies are confirmed on 8.5 |

## First install

### 1. Find the CLI PHP

Sign in over SSH. The default `php` command may be a different version from the website's:

```bash
php -v
/opt/alt/php84/usr/bin/php -v
/opt/alt/php84/usr/bin/php -r 'echo ini_get("disable_functions"), PHP_EOL;'
/opt/alt/php84/usr/bin/php -r 'var_dump(gd_info()["JPEG Support"]);'
```

Use whichever path reports 8.4 for `PHP_BIN` below and in cron. The last line must print `true`, or clinics cannot upload JPG logos and cover photos; enable JPEG for GD in hPanel if it does not.

### 2. Create the database

In hPanel, go to Databases and create a database and user. Connect from the app with host `127.0.0.1`. The `srvNNNN.hstgr.io` host is for outside connections only.

### 3. Get the code

Keep the application outside `public_html`, so `.env` and `vendor` can never be served:

```bash
cd ~/domains/cureplus.cygnatrix.com
git clone <your-repository-url> app
cd app
PHP_BIN=/opt/alt/php84/usr/bin/php
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
nano .env
$PHP_BIN artisan key:generate
chmod 600 .env
```

**Back up `APP_KEY` somewhere safe and separate.** Without it, encrypted columns and backups can never be read, even from a good database copy.

### 4. Point the website at `public/`

Laravel's `public/` folder must be the web root. Run this from the shell, where `ln` works even though PHP's `symlink` is disabled. Look inside `public_html` first, and keep anything you need:

```bash
cd ~/domains/cureplus.cygnatrix.com
ls -la public_html
mv public_html public_html.empty
ln -s app/public public_html
```

If symlinks are refused, clone the project into `public_html` instead. The root `.htaccess` then sends every request to `public/` and blocks the rest. This is safe but second best, because the code sits inside the web root.

Then confirm that `.env` is not reachable. This must not return its contents:

```bash
curl -I https://cureplus.cygnatrix.com/.env
```

### 5. Migrate and create the first admin

```bash
cd ~/domains/cureplus.cygnatrix.com/app
$PHP_BIN artisan migrate --force
$PHP_BIN artisan cureplus:sync-rbac
$PHP_BIN artisan cureplus:seed-defaults
$PHP_BIN artisan cureplus:create-admin you@example.com "Your Name"
```

Save the printed password, sign in at `/admin/login`, and set up 2FA. Create a second super admin too, because only another admin can reset a locked-out admin. Never run the demo seeder in production; it refuses, but do not try.

### 6. Add the cron job

In hPanel, go to Advanced, then Cron Jobs, and run this every minute:

```
/opt/alt/php84/usr/bin/php /home/YOUR_HOSTINGER_USER/domains/cureplus.cygnatrix.com/app/artisan schedule:run >> /dev/null 2>&1
```

After two minutes, the admin dashboard's "Cron last ran" should say a minute ago or less, and `/health` should return 200.

### 7. Plans are switched off

Subscriptions are off (`SUBSCRIPTIONS_ENABLED=false`), and charges are agreed with each practice directly. To switch them on later, set it to `true`, run `artisan config:cache`, then set real prices in Super Admin, Plans and subscriptions. Trials start on the approval date.

### 8. Approving practices

New practices appear under Super Admin, Pending approvals, and every super admin gets an email. Other admin roles cannot approve. Doctors invited to an approved practice appear there too, once they have sent their registration details. Patients cannot book them until they are verified.

## Outside accounts

Each of these can go in `.env` or in Super Admin, Integrations. Values saved in Integrations win.

### Email (Gmail)

Turn on 2-Step Verification on the Google account and create an App Password. Use `smtp.gmail.com`, port 587, and set a From address, because emails fail without one. **This is required, not optional: every sign-in code goes by email, so without working email nobody can sign in with a code, including patients.** Gmail sends about 500 messages a day, and Monitoring warns at 80%. Every sign-in uses one, so move to SES or ZeptoMail before you have many patients.

### SMS

Optional. SMS only carries reminders and notices; sign-in codes go by email. Set the driver to `http` with the gateway's URL and token, or add a provider class (see Architecture §13). The gateway must accept a JSON body with `to`, `sender` and `message`.

Sending to Indian numbers needs DLT registration. Register the text of each event in `config/messages.php`, word for word. Allow time for this: carriers drop unregistered messages.

### WhatsApp

Create templates in Meta Business Manager with these exact names and body parameters, in order:

| Template | Parameters |
|---|---|
| `appointment_booked` | name, doctor, clinic, time, token |
| `appointment_reminder` | name, doctor, clinic, time |
| `token_called` | name, token, doctor |
| `follow_up_due` | name, doctor, clinic, date |
| `payment_received` | name, amount, clinic |
| `payment_link` | name, amount, clinic, link |
| `document_ready` | name, document, clinic, link |
| `organization_approved` | name, clinic |
| `organization_rejected` | name, clinic, reason |
| `organization_restored` | name, clinic |
| `organization_access_changed` | name, clinic, date |
| `organization_access_ending` | name, clinic, date |
| `doctor_verified` | name |
| `doctor_rejected` | name, reason |

The last three go to practice owners and need the platform's own sender (`WHATSAPP_TOKEN` and `WHATSAPP_PHONE_NUMBER_ID`). Without it, owners get those by email only. Until a sender is configured, patient messages go by SMS if a gateway is set up. With neither, patients get email and push only.

### Web push

In Super Admin, Integrations, press "Generate web-push keys" and set a contact address. Do this once: new keys make every device subscribe again.

### Razorpay

There are two separate kinds of account:

- **The platform's own**, for what organizations pay you. Save the keys in Integrations, and add the webhook URL shown there with the events `subscription.activated`, `subscription.charged`, `subscription.resumed`, `subscription.pending`, `subscription.halted`, `subscription.cancelled` and `subscription.completed`. Create a Razorpay plan for each paid plan and paste its ID into the plan. Only needed once subscriptions are switched on.
- **Each organization's own**, for what patients pay them. Owners set this up in their portal, under Integrations.

Run one payment, one refund and one subscription in test mode first. These flows are tested against simulated responses only.

### Cashfree

Each organization can use its own Cashfree account instead of Razorpay. Owners set this up in their portal, under Integrations. One gateway takes new payments at a time.

Before going live, make one payment and one refund in Cashfree's sandbox, on an `https` address. **This has only been tested against simulated responses.** Check in particular that a webhook arrives and is accepted. Cashfree's documentation is not fully consistent about the webhook's format and which secret signs it. If the signature check rejects it, the payment still completes when the patient returns to the site, but not if they close the page first.

### CAPTCHA (recommended)

Create a Cloudflare Turnstile widget, then set `CAPTCHA_DRIVER=turnstile` and its two keys. Public forms then have a CAPTCHA.

### Custom domains

Add a CNAME record for `connect.cureplus.cygnatrix.com` pointing to `cureplus.cygnatrix.com`, and check that `PLATFORM_IPV4` and `PLATFORM_IPV6` match the server. For each domain that reaches "awaiting parking" in Super Admin:

1. In hPanel, add it as a parked domain of `cureplus.cygnatrix.com`. Hostinger allows 100.
2. Press "Mark as parked". It goes live once HTTPS answers. If the certificate is not ready, press "Retry HTTPS" later.

## Updating

```bash
cd ~/domains/cureplus.cygnatrix.com/app
PHP_BIN=/opt/alt/php84/usr/bin/php bash deploy.sh
```

It stops if the PHP version is too old, if `.env` is missing, or if `APP_DEBUG` is not false. It always leaves maintenance mode, even when a step fails.

Before pushing frontend changes, run `npm run build` locally and commit `public/build`.

## After every deploy

- `/health` returns 200.
- Sign in to one account on each portal.
- Monitoring shows no new failed jobs.

## Backups

A backup runs nightly at 01:30 and is kept for 30 days. Files are encrypted, in `storage/app/private/backups`. Set `BACKUP_OFFSITE_DISK` to keep a copy away from this server, because a copy on the same server does not survive losing the server. Hostinger's daily backups are a second layer.

Each month, an automatic check confirms the newest backup can be decrypted and read. That does not prove a restore works, so **also do this once a month**:

1. Create a spare empty database in hPanel.
2. Copy the app to a temporary folder, or run with `DB_DATABASE` set to the spare database.
3. Run the restore below against the spare database.
4. Sign in and look at some records.
5. Record the result in Super Admin, Backups. Delete the spare database.

### Restoring

This replaces the whole database:

```bash
cd ~/domains/cureplus.cygnatrix.com/app
$PHP_BIN artisan down
$PHP_BIN artisan cureplus:backup-restore <backup id>   # The id is on the Backups page
$PHP_BIN artisan migrate --force                       # In case the code is newer than the backup
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan up
```

The list of backups lives in the database. If the database itself is lost, restore Hostinger's database backup first. Then, if you need a newer copy, use its list to restore from the encrypted file.

The `sessions` and `cache` tables are not backed up, so everyone signs in again after a restore.

## Error tracking and uptime

- Monitoring counts errors per day. Details are in `storage/logs`, kept for 14 days.
- For alerts, point an uptime monitor at `/health`. It returns 503 if the database, cache or cron stops working.
- Sentry is not installed. To add it, install `sentry/sentry-laravel` and set its DSN. Scrub patient data before turning it on.

## Troubleshooting

| Symptom | Check |
|---|---|
| 500 on every page | `storage/logs`, folder permissions on `storage` and `bootstrap/cache`, then `artisan optimize:clear` |
| Sign-in codes never arrive | They do not use cron. Check the mail settings and the spam folder, then `storage/logs` |
| Other emails or messages never arrive | Is cron running? Then Monitoring for failed jobs, then the provider's settings |
| Cron shows "Never" | The PHP path in the cron job; run the command by hand over SSH |
| Everyone shares one IP in the audit log | A CDN is in front. Set `TRUSTED_PROXIES` |
| "Page expired" on every form | `APP_URL` must be `https://`, and `SESSION_SECURE_COOKIE=true` needs HTTPS |
| A domain shows "Page not found" | Its status in Super Admin, Custom domains must be active, and the organization must be active |
| Web push will not send | PHP needs OpenSSL with EC keys. Check `gmp` is enabled, which speeds it up |
