# CurePlus

Multi-tenant OPD management for clinics and doctors in India: appointments, queue, consultations, prescriptions, billing, a patient portal and reminders, installable on any device as a PWA.

Built on PHP 8.4, Laravel 13, MariaDB 10.5 and Blade with Alpine.js and Tailwind. It runs on Hostinger shared hosting and can move to a VPS without a rewrite.

| Document | Read it for |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | **Start here.** How the system fits together and the rules you must not break |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | First install and updates on Hostinger, outside accounts to set up, and restoring backups |
| [docs/VPS_MIGRATION.md](docs/VPS_MIGRATION.md) | Moving to a VPS: Redis, Supervisor, Caddy on-demand TLS |
| [CHANGELOG.md](CHANGELOG.md) | What changed |

## Run it locally

You need PHP 8.4, Composer and Node.

```bash
composer install
npm install --ignore-scripts && npm run build
cp .env.example .env
```

Change these lines in `.env` for local work:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
SESSION_SECURE_COOKIE=false
SMS_DRIVER=log
MAIL_MAILER=log
# Boost's inline browser logger is blocked by the CSP, so it only adds console noise.
BOOST_BROWSER_LOGS_WATCHER=false
```

Remove the other `DB_*` lines, then:

```bash
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

To process queued work locally, such as messages and backups, run `php artisan schedule:run`.

## Demo accounts

These are created by `DemoSeeder`, which refuses to run in production. The password for all of them is `Demo@12345`.

| Who | Sign in at | Email | Shows |
|---|---|---|---|
| Platform admin | `/admin/login` | `admin@cureplus.test` | Super Admin portal |
| Clinic owner | `/login` | `owner@cityclinic.test` | Clinic with two branches |
| Doctor in two organizations | `/login` | `roy@cureplus.test` | Organization switcher, consultation, prescriptions |
| Receptionist | `/login` | `reception@cityclinic.test` | Restricted menu, one branch, billing |

Owners, doctors and admins are asked to set up 2FA at first sign-in. With `MAIL_MAILER=log`, emails appear in `storage/logs/laravel.log`. Search for `Your code is`. Set `QUEUE_CONNECTION=sync` locally unless you run `php artisan queue:work`, or queued emails such as verification links and invitations will not go out.

Try the practice's website at `/city-clinic`, with booking at `/book/city-clinic`, and sign in at `/patient/login` with any email address. The clinic owner changes how those pages look under Patient page design.

## Checks

```bash
php artisan test                                                     # SQLite in memory
DB_CONNECTION=mariadb DB_DATABASE=cureplus_test php artisan test     # Also runs the concurrency tests
vendor/bin/pint --format agent
vendor/bin/phpstan analyse --memory-limit=1G
composer audit
```

## Build status

| Phase | Scope | Status |
|---|---|---|
| 1 | Foundation: tenancy, sign-in, RBAC, audit, Super Admin basics, PWA | Built |
| 2 | OPD core: patients, shared calendar, queue and TV display, EMR, prescriptions | Built |
| 3 | Billing, optional GST, Razorpay, refunds, reports | Built |
| 4 | Patient portal, booking links, QR codes, consents, DPDP rights, languages | Built |
| 5 | WhatsApp, SMS, email and push reminders, follow-up call list | Built |
| 6 | Plans, subscriptions, feature flags, support tickets, announcements, templates | Built |
| 7 | Custom domains, monitoring, backups, API keys, platform settings, ABDM readiness | Built |

Nothing is deployed yet. See the deployment guide.

## Known limitations

**Needs outside accounts before it works in production**

- **SMS:** a generic JSON gateway exists, but no provider is chosen. Indian SMS needs DLT template registration. It is only used for reminders; every sign-in code goes by email.
- **WhatsApp:** templates must be created and approved in Meta Business Manager with the names and parameters in `config/messages.php`.
- **Razorpay:** tested against simulated responses, never the real API. Run a test-mode payment, refund and subscription before going live.
- **Gmail** sends about 500 messages a day, and Monitoring warns at 80%. Plan a move to SES or ZeptoMail.

**Needs professional review**

- **Legal pages are drafts.** Have counsel write them, then paste them into Super Admin, System configuration.
- **DPDP deletion against medical retention:** a deletion request removes the login and unlinks records, but clinics keep medical and billing records. Whether that and the retention periods are right is a legal question.
- **GST:** consultations are always exempt. Other items, such as instruments, can carry GST only when the practice has saved a GSTIN. Tax is shown as CGST and SGST, so inter-state IGST is not handled. Have a CA confirm the treatment and the invoice format.
- **Direct UPI payments** are checked by hand: a patient's transaction ID counts only after staff find the money in their bank app and press Received.
- **Hindi and Bengali translations** were not reviewed by native speakers or clinicians. This matters most for dosage instructions.
- **ABDM:** readiness only. The ABHA field and FHIR export exist, but nothing has been certified against ABDM's sandbox.

**Known behaviour**

- **PDFs cannot show Hindi or Bengali:** DomPDF cannot shape these scripts. The printed page shows them correctly; the PDF shows English and points to the printed copy.
- **Patients without email:** patient sign-in needs an email. A patient the clinic registered without one cannot use the portal until the clinic adds it, and cross-clinic history cannot be requested for them.
- **A mistyped email:** everyone with the same email is treated as one family in the portal, so a clinic that types the wrong address exposes that record to whoever owns it. Clinics should check the address with the patient.
- **Cross-organization history** matches on email and exact name, so "Ravi" and "Ravi K" do not match. This favours privacy over completeness.
- **Unverified numbers:** registration reserves a mobile before it is verified, so someone could block a number they do not own until support steps in.
- **Lists:** search and filters are on the main lists. CSV export is on patients, bills, organizations and audit logs, but not yet every list.
- **Coupons:** extra trial days apply directly. Percentage discounts need an offer created in Razorpay.

**Not yet verified**

- **Not run on the Hostinger server.** The host's command-line PHP settings, the symlink, and real email and HTTPS still need checking at first deploy.
- **Web push** was not tested with a real browser subscription. Its encryption test skips on Windows PHP without an OpenSSL configuration and runs in CI.
- **Lighthouse** was not run, and the PWA install prompt has not been tried on a real phone or iPhone.
