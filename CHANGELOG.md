# Changelog

## Unreleased

### Patient files in the practice's own Google Drive

- Practices can connect one or more Google accounts under Integrations. A copy of every visit file and chat file goes into a "CurePlus" folder in their Drive, choosing the account with the most free space. More accounts mean more space.
- CurePlus uses Google's `drive.file` permission, so it only sees the files it puts there. Files still open through CurePlus with the same permissions and audit log.
- Files are always saved on the server first and copied every five minutes. A copy only counts once Google reports identical contents. Sample patients' files are never copied.
- Server copies stay until a platform admin records the practice's choice under the new File storage page: keep them (charged separately) or remove them. Before removal, each file is checked in Drive. A file missing from Drive keeps its server copy and is copied again.
- The admin can bring files back from Drive to the server. A Drive holding the only copy of any file cannot be disconnected until that is done.
- A Google account whose permission was withdrawn is marked "Connect again", and its files wait on the server.

### Appointment chat, and unfinished appointments stay on the queue

- Every appointment has a chat between the patient and their doctor, for questions and reports (PDF, JPG or PNG up to 10 MB). Only the appointment's own doctor can read it at the practice. Messages are encrypted at rest.
- The doctor closes the chat when it is no longer needed, and can reopen it. Chats of finished appointments close by themselves 30 days after the appointment. A closed chat stays readable.
- New messages show on the doctor's dashboard, the queue and a new Patient chats page. Patients get an email and push alert without the message text, at most once per 10 minutes.
- The queue now shows a "Not finished from earlier days" section, so appointments left booked, waiting or in consultation are no longer lost overnight.
- Web push now uses the standard aes128gcm encryption.

### Faster doctor calendar

- Add the same hours to several days at once, with Mon–Fri, Mon–Sat and Every day shortcuts. If any day clashes, nothing is saved and the clash is named.
- Edit hours in place (time, minutes per patient, location). Appointments already booked keep their time.
- Copy a whole day, morning and evening sessions alike, to other days, optionally replacing what those days already have at this practice. Hours at other organizations are never removed.
- Weekly hours are grouped by doctor.

### Audit fixes

- A patient can no longer cancel a paid appointment online, so the money is not left with the clinic unseen. They are asked to contact the clinic, which can cancel and refund. Rescheduling still works.
- Each side can send up to 20 files a day in one chat, so uploads cannot fill the server's disk.
- Removing a doctor's hours or a calendar block is now recorded in the audit log.
- The clinic and patient portals have a "Skip to content" link for keyboard and screen-reader users.

### UPI payments without a gateway, and GST only with a GSTIN

- Practices without Razorpay or Cashfree can take UPI payments directly into their own UPI ID, personal or business. For video calls and clinic visits separately, they choose whether patients who book online may pay or must pay.
- Patients get a QR code and a "Pay with UPI app" button with the amount filled in, then enter the 12-digit transaction ID. A booking that requires payment is held until then.
- Staff confirm each UPI payment against their bank app with Received or Not received, from the bill. The dashboard shows how many are waiting, and bills can be filtered by "UPI to check". A transaction ID can be used once only.
- New messages: `appointment_payment_missed` and `upi_payment_not_found`.
- Items can carry GST only when the practice has saved a GSTIN, and a taxable item needs a rate above 0%. Rates up to 40% are accepted. Bills now show the HSN/SAC code on screen as well as in the PDF.
- Saving a bill that the system refuses now shows a message instead of an error page.

### Send from your own phone

- WhatsApp buttons send through the practice's WhatsApp Business number when connected. Patients no longer fall back to the platform's number.
- Test connection and a template list for WhatsApp Business under Integrations.
- The SMS button also shows on computers, and explains that it opens on a phone.
- WhatsApp and SMS buttons on prescriptions, bills, payment links, appointments, the queue and follow-ups open staff's own apps with the message ready.
- Signed links now open a page showing the prescription or bill, with a PDF download, and last 30 days.

### Signup details and email separation

- Signup now asks for everything approval needs: address, registration details for doctors, and a proof document for clinics. Clinic owners who see patients can say so.
- The platform's email is only for platform business and verification. Patient email is sent from the practice's own address, or not at all.

### Access dates, email fix, Cashfree, prescription rule

- Super admins can set an access end date at approval or later. After it the practice is locked out and its booking closes; changing the date restores access.
- Saving SMTP details now switches sending on, and the page warns when email is only going to a log file.
- Cashfree added alongside Razorpay. Each practice chooses one.
- Only the doctor on a visit can write its prescription. Owners can read but not write them.
- Smoke test opens every page as every role.
- Owners can read consultations but not change them, unless they are that patient's doctor.
- Owners are reminded by email and WhatsApp a week before access ends and again the day before.

### Practice websites, prescription list, doctor approval

- Each practice gets its own website at `/{slug}`: hero, doctors, services, about and photos, branches with directions, questions. Booking moved to `/book/{slug}`.
- New settings: services, questions, clinic photos, year established, doctor photos.
- Prescriptions list with search for owners and doctors.
- Prescriptions now need their own permission. Reception and nurses can no longer open them unless an owner allows it.
- Patients can only book doctors whose registration a super admin has checked.

### Approval workflow, subscriptions off, audit fixes

- Practices start as pending verification. They can sign in, upload documents, and set up branches, staff, fees and sample patients. Booking links, real patients, bills, prescription printing, online payments, SMS and WhatsApp stay closed until approval, enforced in middleware, policies and services.
- Super Admin: Pending approvals with a count, document viewer, approve, and reject with a required reason. Decisions are audited, owners are told by email and WhatsApp, and rejected practices can fix and resubmit.
- Subscriptions switched off with `SUBSCRIPTIONS_ENABLED=false`. When on, trials start at approval.
- Fixed: logos and covers could not be seen by practices awaiting approval, and JPG uploads failed on PHP builds without JPEG support. Pictures are now shrunk in the browser and previewed straight away. Logos appear on prescriptions and bills.
- Fixed: anyone with Manage staff could create or change owners.
- Fixed: a staff member made a doctor got no booking link.
- Hardened: sign-in code emails capped per address, patient export limited to reports access, invitation throttle.
- Easier: dashboard setup checklist, grouped menus, Add a doctor with a guide.

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
