# Changelog

## Unreleased

### Email sign-in and patient design

- Every sign-in and verification code now goes by email: patient login, staff code login, 2FA, booking, and cross-clinic consent. Mobile numbers remain for contact, password login and reminders. Invitations are by email only.
- Payment links go through the notifier, so patients get them by email, and by WhatsApp or SMS when configured.
- Redesigned booking pages and patient dashboard: branded hero, step indicator, doctor cards, date strip, slots by time of day, appointment summary, confirmation ticket, bottom tabs on phones.
- Patient page design for clinics and doctors: colours with readable-contrast protection, corners, logo, cover photo, tagline, about text, highlights, contact details, optional fees, live preview.

### Phase 7: Advanced

- Custom domains: DNS verification, parking queue, HTTPS check before going live, daily re-check, neutral 404 for unknown hosts. Custom domains serve only the booking site.
- Backups: encrypted PHP-only dump, 30-day retention, off-site option, verify, monthly automatic check, command-line restore.
- Monitoring: queue, failed jobs, cron, errors, email volume, webhooks, disk.
- Versioned API with hashed, scoped keys. Medicine catalogue and FHIR prescriptions for the pharmacy link.
- Platform integrations and system configuration, with secrets encrypted. Maintenance mode and editable legal pages.
- Security center: IP blocking, account search, 2FA reset, optional 2FA for all staff. Admin password reset. Breach incident workflow.

### Phase 6: Platform business

- Plans, limits, trials, coupons. Razorpay subscriptions on the platform's own account, grace period and auto-suspend.
- Feature flags per plan and per organization. Support tickets, announcements, templates, platform reports.

### Phase 5: Engagement

- WhatsApp Cloud API, SMS, email and web push behind one notifier, with de-duplicated sending.
- Automatic booking, reminder, token, follow-up and payment messages, with quiet hours and per-event opt-out.
- Missed follow-up call list. Signed links for sending prescriptions and bills.

### Phase 4: Patient portal

- OTP login, records from every organization, reschedule and cancel, online payment.
- Path-based booking links, QR codes, posters, tenant-branded installable PWA.
- Consents, consent-gated cross-organization history logged at both ends, data download, correction and deletion requests.
- English, Hindi and Bengali.

### Phase 3: Billing and payments

- One-screen billing, optional GST, invoice series, part payment and dues, refunds, cancellations.
- Per-organization Razorpay through hosted payment links, signed idempotent webhooks, reconciliation.
- Daily cash, monthly income in Excel and PDF, top diagnoses, doctor-wise revenue. Optional per-organization SMTP.

### Phase 2: OPD core

- Quick patient registration, duplicate detection, family on one mobile, ABHA field.
- One calendar per doctor across all organizations. Double-booking stopped by a row lock and a unique index.
- Tokens, live queue, TV display. EMR with templates, vitals and attachments.
- Prescriptions with catalogue search, favourites, copy last, letterhead, print and PDF. Global medicine catalogue with a request queue. Doctor directory and verification.

### Phase 1: Foundation

- Multi-tenancy: single database, fail-closed tenant scope, tenant-aware jobs.
- Accounts: users, platform admins, organizations, locations, memberships, combinable roles, per-organization permission toggles, invitations.
- Sign-in: password, mobile OTP for staff, TOTP or OTP 2FA, recovery codes, lockout, email and mobile verification, password reset, device sessions.
- Audit log and security events.
- PWA shell for all four portals. Shared-hosting deployment.
