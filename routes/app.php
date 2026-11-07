<?php

use App\Http\Controllers\App\AccountController;
use App\Http\Controllers\App\AnnouncementController;
use App\Http\Controllers\App\AppointmentChatController;
use App\Http\Controllers\App\AppointmentController;
use App\Http\Controllers\App\AuditLogController;
use App\Http\Controllers\App\BillingSettingsController;
use App\Http\Controllers\App\BookingLinkController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DesignController;
use App\Http\Controllers\App\DoctorProfileController;
use App\Http\Controllers\App\DocumentShareController;
use App\Http\Controllers\App\DomainController;
use App\Http\Controllers\App\DriveController;
use App\Http\Controllers\App\DuesController;
use App\Http\Controllers\App\FollowUpController;
use App\Http\Controllers\App\IntegrationController;
use App\Http\Controllers\App\InvitationController;
use App\Http\Controllers\App\InvoiceController;
use App\Http\Controllers\App\LocationController;
use App\Http\Controllers\App\MedicineController;
use App\Http\Controllers\App\OrganizationSettingsController;
use App\Http\Controllers\App\PatientController;
use App\Http\Controllers\App\PatientHistoryController;
use App\Http\Controllers\App\PatientMessageController;
use App\Http\Controllers\App\PrescriptionController;
use App\Http\Controllers\App\QueueController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\RolePermissionController;
use App\Http\Controllers\App\StaffController;
use App\Http\Controllers\App\SubscriptionController;
use App\Http\Controllers\App\SupportTicketController;
use App\Http\Controllers\App\SwitcherController;
use App\Http\Controllers\App\VerificationController;
use App\Http\Controllers\App\VisitController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\PushController;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->name('app.')->middleware(['auth:web', 'account.active', 'no-store'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/verify/email', [EmailVerificationController::class, 'send'])->middleware('throttle:otp')->name('verification.send');

    Route::middleware('contact.verified')->group(function () {
        Route::middleware('not-impersonating')->group(function () {
            Route::get('/two-factor', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
            Route::post('/two-factor/totp', [TwoFactorSetupController::class, 'confirmTotp'])->middleware('throttle:otp-verify')->name('two-factor.totp');
            Route::post('/two-factor/otp/send', [TwoFactorSetupController::class, 'sendOtp'])->middleware('throttle:otp')->name('two-factor.otp.send');
            Route::post('/two-factor/otp', [TwoFactorSetupController::class, 'confirmOtp'])->middleware('throttle:otp-verify')->name('two-factor.otp');
        });

        Route::middleware('two-factor')->group(function () {
            Route::get('/organizations', [SwitcherController::class, 'index'])->name('organizations');
            Route::post('/organizations/{uuid}/switch', [SwitcherController::class, 'switchOrganization'])->name('organizations.switch');

            Route::get('/account', [AccountController::class, 'show'])->middleware('tenant:optional')->name('account');
            Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:30,1')->name('push.subscribe');
            Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->middleware('throttle:30,1')->name('push.unsubscribe');
            Route::post('/account/notifications', [PushController::class, 'savePreferences'])->name('notifications.save');
            Route::post('/account/doctor-profile', [DoctorProfileController::class, 'update'])->name('doctor-profile.update');
            Route::post('/account/doctor-profile/certificate', [DoctorProfileController::class, 'certificate'])->name('doctor-profile.certificate');
            Route::post('/account/doctor-photo', [DoctorProfileController::class, 'photo'])->name('doctor-profile.photo');
            Route::middleware('not-impersonating')->group(function () {
                Route::put('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:login')->name('account.password');
                Route::delete('/account/sessions/{token}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
            });

            Route::middleware(['tenant', 'feature'])->group(function () {
                Route::get('/', DashboardController::class)->can('dashboard.view')->name('dashboard');
                Route::post('/location', [SwitcherController::class, 'switchLocation'])->name('location.switch');

                Route::get('/locations', [LocationController::class, 'index'])->can('viewAny', Location::class)->name('locations.index');
                Route::get('/locations/create', [LocationController::class, 'create'])->can('create', Location::class)->name('locations.create');
                Route::post('/locations', [LocationController::class, 'store'])->can('create', Location::class)->name('locations.store');
                Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->can('update', 'location')->name('locations.edit');
                Route::put('/locations/{location}', [LocationController::class, 'update'])->can('update', 'location')->name('locations.update');
                Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->can('delete', 'location')->name('locations.destroy');

                Route::get('/staff', [StaffController::class, 'index'])->can('viewAny', Membership::class)->name('staff.index');
                Route::get('/staff/{membership}/edit', [StaffController::class, 'edit'])->can('update', 'membership')->name('staff.edit');
                Route::put('/staff/{membership}', [StaffController::class, 'update'])->can('update', 'membership')->name('staff.update');
                Route::post('/staff/{membership}/photo', [StaffController::class, 'photo'])->can('update', 'membership')->name('staff.photo');
                Route::post('/staff/{membership}/deactivate', [StaffController::class, 'deactivate'])->can('update', 'membership')->name('staff.deactivate');
                Route::post('/staff/{membership}/activate', [StaffController::class, 'activate'])->can('update', 'membership')->name('staff.activate');

                Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->can('staff.manage')->name('invitations.store');
                Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->can('staff.manage')->name('invitations.destroy');

                Route::get('/roles', [RolePermissionController::class, 'index'])->can('roles.manage')->name('roles.index');
                Route::put('/roles', [RolePermissionController::class, 'update'])->can('roles.manage')->name('roles.update');

                Route::get('/patients', [PatientController::class, 'index'])->can('viewAny', Patient::class)->name('patients.index');
                Route::get('/patients/export', [PatientController::class, 'export'])->can('reports.view')->name('patients.export');
                Route::get('/patients/search', [PatientController::class, 'search'])->can('viewAny', Patient::class)->name('patients.search');
                Route::get('/patients/create', [PatientController::class, 'create'])->middleware('org.approved')->can('create', Patient::class)->name('patients.create');
                Route::post('/patients', [PatientController::class, 'store'])->middleware('org.approved')->can('create', Patient::class)->name('patients.store');
                Route::get('/patients/{patient}', [PatientController::class, 'show'])->can('view', 'patient')->name('patients.show');
                Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->can('update', 'patient')->name('patients.edit');
                Route::put('/patients/{patient}', [PatientController::class, 'update'])->can('update', 'patient')->name('patients.update');

                Route::get('/patients/{patient}/history', [PatientHistoryController::class, 'show'])->can('viewHistory', 'patient')->name('patients.history');
                Route::post('/patients/{patient}/history/request', [PatientHistoryController::class, 'requestConsent'])->middleware('throttle:otp')->can('viewHistory', 'patient')->name('patients.history.request');
                Route::post('/patients/{patient}/history/confirm', [PatientHistoryController::class, 'confirmConsent'])->middleware('throttle:otp-verify')->can('viewHistory', 'patient')->name('patients.history.confirm');

                Route::get('/appointments', [AppointmentController::class, 'index'])->can('viewAny', Appointment::class)->name('appointments.index');
                Route::get('/appointments/create', [AppointmentController::class, 'create'])->can('create', Appointment::class)->name('appointments.create');
                Route::post('/appointments', [AppointmentController::class, 'store'])->can('create', Appointment::class)->name('appointments.store');
                Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->can('update', 'appointment')->name('appointments.edit');
                Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->can('update', 'appointment')->name('appointments.update');
                Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->can('update', 'appointment')->name('appointments.cancel');
                Route::get('/chats', [AppointmentChatController::class, 'index'])->can('consultations.manage')->name('chats.index');
                Route::get('/appointments/{appointment}/chat', [AppointmentChatController::class, 'show'])->can('chat', 'appointment')->name('appointments.chat');
                Route::get('/appointments/{appointment}/chat/messages', [AppointmentChatController::class, 'messages'])->can('chat', 'appointment')->name('appointments.chat.messages');
                Route::post('/appointments/{appointment}/chat', [AppointmentChatController::class, 'store'])->middleware('throttle:30,1')->can('chat', 'appointment')->name('appointments.chat.store');
                Route::post('/appointments/{appointment}/chat/close', [AppointmentChatController::class, 'close'])->can('chat', 'appointment')->name('appointments.chat.close');
                Route::post('/appointments/{appointment}/chat/reopen', [AppointmentChatController::class, 'reopen'])->can('chat', 'appointment')->name('appointments.chat.reopen');
                Route::get('/appointments/{appointment}/chat/files/{message}', [AppointmentChatController::class, 'file'])->can('chat', 'appointment')->name('appointments.chat.file');

                Route::get('/queue', [QueueController::class, 'index'])->can('viewAny', Appointment::class)->name('queue.index');
                Route::post('/queue/{appointment}/move', [QueueController::class, 'move'])->can('update', 'appointment')->name('queue.move');

                Route::get('/follow-ups', [FollowUpController::class, 'index'])->can('appointments.manage')->name('follow-ups.index');
                Route::put('/follow-ups/{followUp}', [FollowUpController::class, 'update'])->can('appointments.manage')->name('follow-ups.update');

                Route::get('/calendar', [CalendarController::class, 'index'])->can('appointments.manage')->name('calendar.index');
                Route::post('/calendar/availability', [CalendarController::class, 'storeAvailability'])->can('appointments.manage')->name('calendar.availability.store');
                Route::post('/calendar/availability/copy', [CalendarController::class, 'copyDay'])->can('appointments.manage')->name('calendar.availability.copy');
                Route::get('/calendar/availability/{availability}/edit', [CalendarController::class, 'editAvailability'])->can('appointments.manage')->name('calendar.availability.edit');
                Route::put('/calendar/availability/{availability}', [CalendarController::class, 'updateAvailability'])->can('appointments.manage')->name('calendar.availability.update');
                Route::delete('/calendar/availability/{availability}', [CalendarController::class, 'destroyAvailability'])->can('appointments.manage')->name('calendar.availability.destroy');
                Route::post('/calendar/blocks', [CalendarController::class, 'storeBlock'])->can('appointments.manage')->name('calendar.blocks.store');
                Route::delete('/calendar/blocks/{block}', [CalendarController::class, 'destroyBlock'])->can('appointments.manage')->name('calendar.blocks.destroy');

                Route::post('/visits/start/{appointment}', [VisitController::class, 'start'])->can('create', Visit::class)->name('visits.start');
                Route::get('/visits/{visit}', [VisitController::class, 'show'])->can('view', 'visit')->name('visits.show');
                Route::put('/visits/{visit}', [VisitController::class, 'update'])->can('update', 'visit')->name('visits.update');
                Route::post('/visits/{visit}/vitals', [VisitController::class, 'storeVitals'])->can('recordVitals', 'visit')->name('visits.vitals');
                Route::post('/visits/{visit}/complete', [VisitController::class, 'complete'])->can('update', 'visit')->name('visits.complete');
                Route::post('/visits/{visit}/attachments', [VisitController::class, 'storeAttachment'])->can('update', 'visit')->name('visits.attachments.store');
                Route::get('/attachments/{attachment}', [VisitController::class, 'downloadAttachment'])->middleware('signed')->can('view', 'attachment')->name('attachments.show');

                Route::get('/visits/{visit}/prescription', [PrescriptionController::class, 'edit'])->can('prescribe', 'visit')->name('prescriptions.edit');
                Route::put('/visits/{visit}/prescription', [PrescriptionController::class, 'update'])->can('prescribe', 'visit')->name('prescriptions.update');
                Route::get('/prescriptions', [PrescriptionController::class, 'index'])->can('viewAny', Prescription::class)->name('prescriptions.index');
                Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->middleware('org.approved')->can('print', 'prescription')->name('prescriptions.show');
                Route::get('/prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf'])->middleware('org.approved')->can('print', 'prescription')->name('prescriptions.pdf');
                Route::post('/prescriptions/{prescription}/email', [PrescriptionController::class, 'email'])->middleware('org.approved')->can('print', 'prescription')->name('prescriptions.email');
                Route::post('/prescriptions/{prescription}/share', [DocumentShareController::class, 'prescription'])->middleware('throttle:30,1')->middleware('org.approved')->can('print', 'prescription')->name('prescriptions.share');

                Route::post('/messages/whatsapp', [PatientMessageController::class, 'whatsapp'])->middleware(['org.approved', 'throttle:60,1'])->can('dashboard.view')->name('messages.whatsapp');

                Route::get('/medicines', [MedicineController::class, 'index'])->can('prescriptions.manage')->name('medicines.index');
                Route::get('/medicines/search', [PrescriptionController::class, 'searchMedicines'])->can('prescriptions.manage')->name('medicines.search');
                Route::post('/medicines/favourites', [MedicineController::class, 'favourite'])->can('prescriptions.manage')->name('medicines.favourite');
                Route::delete('/medicines/favourites/{favourite}', [MedicineController::class, 'unfavourite'])->can('prescriptions.manage')->name('medicines.unfavourite');
                Route::post('/medicines/requests', [MedicineController::class, 'request'])->can('prescriptions.manage')->name('medicines.request');

                Route::get('/invoices', [InvoiceController::class, 'index'])->can('viewAny', Invoice::class)->name('invoices.index');
                Route::get('/invoices/export', [InvoiceController::class, 'export'])->can('viewAny', Invoice::class)->name('invoices.export');
                Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('org.approved')->can('create', Invoice::class)->name('invoices.create');
                Route::get('/invoices/fee', [InvoiceController::class, 'fee'])->middleware('org.approved')->can('create', Invoice::class)->name('invoices.fee');
                Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('org.approved')->can('create', Invoice::class)->name('invoices.store');
                Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->can('view', 'invoice')->name('invoices.show');
                Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->can('view', 'invoice')->name('invoices.pdf');
                Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'email'])->can('view', 'invoice')->name('invoices.email');
                Route::post('/invoices/{invoice}/share', [DocumentShareController::class, 'invoice'])->middleware('throttle:30,1')->middleware('org.approved')->can('view', 'invoice')->name('invoices.share');
                Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('org.approved')->can('update', 'invoice')->name('invoices.pay');
                Route::post('/invoices/{invoice}/payment-link', [InvoiceController::class, 'paymentLink'])->middleware('org.approved')->can('update', 'invoice')->name('invoices.payment-link');
                Route::post('/invoices/{invoice}/upi/{payment}/confirm', [InvoiceController::class, 'confirmUpi'])->can('update', 'invoice')->name('invoices.upi.confirm');
                Route::post('/invoices/{invoice}/upi/{payment}/reject', [InvoiceController::class, 'rejectUpi'])->can('update', 'invoice')->name('invoices.upi.reject');
                Route::post('/invoices/{invoice}/refund', [InvoiceController::class, 'refund'])->can('refund', 'invoice')->name('invoices.refund');
                Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->can('refund', 'invoice')->name('invoices.cancel');
                Route::get('/dues', DuesController::class)->can('viewAny', Invoice::class)->name('dues.index');

                Route::get('/billing-settings', [BillingSettingsController::class, 'index'])->can('settings.manage')->name('billing-settings.index');
                Route::post('/billing-settings/fees', [BillingSettingsController::class, 'saveFees'])->can('settings.manage')->name('billing-settings.fees');
                Route::post('/billing-settings/items', [BillingSettingsController::class, 'storeItem'])->can('settings.manage')->name('billing-settings.items.store');
                Route::delete('/billing-settings/items/{item}', [BillingSettingsController::class, 'destroyItem'])->can('settings.manage')->name('billing-settings.items.destroy');

                Route::get('/integrations', [IntegrationController::class, 'index'])->can('integrations.manage')->name('integrations.index');
                Route::post('/integrations/razorpay', [IntegrationController::class, 'saveRazorpay'])->can('integrations.manage')->name('integrations.razorpay');
                Route::post('/integrations/razorpay/test', [IntegrationController::class, 'testRazorpay'])->can('integrations.manage')->name('integrations.razorpay.test');
                Route::post('/integrations/cashfree', [IntegrationController::class, 'saveCashfree'])->can('integrations.manage')->name('integrations.cashfree');
                Route::post('/integrations/cashfree/test', [IntegrationController::class, 'testCashfree'])->can('integrations.manage')->name('integrations.cashfree.test');
                Route::post('/integrations/upi', [IntegrationController::class, 'saveUpi'])->can('integrations.manage')->name('integrations.upi');
                Route::post('/integrations/smtp', [IntegrationController::class, 'saveSmtp'])->can('integrations.manage')->name('integrations.smtp');
                Route::post('/integrations/smtp/test', [IntegrationController::class, 'testSmtp'])->can('integrations.manage')->name('integrations.smtp.test');
                Route::post('/integrations/whatsapp/test', [IntegrationController::class, 'testWhatsapp'])->can('integrations.manage')->name('integrations.whatsapp.test');
                Route::post('/integrations/whatsapp', [IntegrationController::class, 'saveWhatsapp'])->can('integrations.manage')->name('integrations.whatsapp');
                Route::get('/integrations/google-drive/connect', [DriveController::class, 'connect'])->can('integrations.manage')->name('integrations.drive.connect');
                Route::get('/integrations/google-drive/callback', [DriveController::class, 'callback'])->can('integrations.manage')->name('integrations.drive.callback');
                Route::post('/integrations/google-drive/{account}/disconnect', [DriveController::class, 'disconnect'])->can('integrations.manage')->name('integrations.drive.disconnect');

                Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->can('settings.manage')->name('settings.organization');
                Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->can('settings.manage')->name('settings.organization.update');

                Route::get('/verification', [VerificationController::class, 'index'])->can('settings.manage')->name('verification.index');
                Route::post('/verification/registration', [VerificationController::class, 'saveRegistration'])->can('settings.manage')->name('verification.registration');
                Route::post('/verification/documents', [VerificationController::class, 'storeDocument'])->middleware('throttle:30,1')->can('settings.manage')->name('verification.documents.store');
                Route::get('/verification/documents/{document}', [VerificationController::class, 'showDocument'])->can('settings.manage')->name('verification.documents.show');
                Route::delete('/verification/documents/{document}', [VerificationController::class, 'destroyDocument'])->can('settings.manage')->name('verification.documents.destroy');
                Route::post('/verification/resubmit', [VerificationController::class, 'resubmit'])->can('settings.manage')->name('verification.resubmit');
                Route::post('/verification/sample-data', [VerificationController::class, 'sampleData'])->can('settings.manage')->name('verification.sample-data');

                Route::get('/settings/design/image/{kind}', [DesignController::class, 'image'])->where('kind', 'logo|cover|gallery-[0-5]')->can('settings.manage')->name('settings.design.image');
                Route::get('/settings/design', [DesignController::class, 'edit'])->can('settings.manage')->name('settings.design');
                Route::put('/settings/design', [DesignController::class, 'update'])->can('settings.manage')->name('settings.design.update');

                Route::get('/booking-links', [BookingLinkController::class, 'index'])->can('settings.manage')->name('booking-links.index');
                Route::get('/booking-links/qr', [BookingLinkController::class, 'download'])->middleware('org.approved')->can('settings.manage')->name('booking-links.download');
                Route::get('/booking-links/poster', [BookingLinkController::class, 'poster'])->middleware('org.approved')->can('settings.manage')->name('booking-links.poster');

                Route::get('/domains', [DomainController::class, 'index'])->can('settings.manage')->name('domains.index');
                Route::post('/domains', [DomainController::class, 'store'])->middleware('throttle:20,1')->middleware('org.approved')->can('settings.manage')->name('domains.store');
                Route::post('/domains/{domain}/check', [DomainController::class, 'check'])->middleware('throttle:30,1')->can('settings.manage')->name('domains.check');
                Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->can('settings.manage')->name('domains.destroy');

                Route::get('/subscription', [SubscriptionController::class, 'index'])->middleware('subscriptions.on')->can('subscription.manage')->name('subscription.index');
                Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('subscriptions.on')->can('subscription.manage')->name('subscription.checkout');
                Route::post('/subscription/coupon', [SubscriptionController::class, 'applyCoupon'])->middleware('subscriptions.on')->middleware('throttle:10,1')->can('subscription.manage')->name('subscription.coupon');
                Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('subscriptions.on')->can('subscription.manage')->name('subscription.cancel');
                Route::get('/subscription/invoices/{platformInvoice}', [SubscriptionController::class, 'invoicePdf'])->middleware('subscriptions.on')->can('subscription.manage')->name('subscription.invoice');

                Route::get('/support', [SupportTicketController::class, 'index'])->can('dashboard.view')->name('support.index');
                Route::post('/support', [SupportTicketController::class, 'store'])->middleware('throttle:20,1')->can('dashboard.view')->name('support.store');
                Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->can('dashboard.view')->name('support.show');
                Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware('throttle:20,1')->can('dashboard.view')->name('support.reply');

                Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->can('dashboard.view')->name('announcements.dismiss');

                Route::get('/reports', [ReportController::class, 'index'])->can('reports.view')->name('reports.index');
                Route::get('/reports/monthly.xlsx', [ReportController::class, 'monthlyXlsx'])->can('reports.view')->name('reports.monthly.xlsx');
                Route::get('/reports/monthly.pdf', [ReportController::class, 'monthlyPdf'])->can('reports.view')->name('reports.monthly.pdf');
                Route::get('/reports/reconciliation', [ReportController::class, 'reconciliation'])->can('reports.view')->name('reports.reconciliation');

                Route::get('/audit-log', [AuditLogController::class, 'index'])->can('audit.view')->name('audit.index');
                Route::get('/audit-log/export', [AuditLogController::class, 'export'])->can('audit.view')->name('audit.export');
            });
        });

        // Outside the tenant group: these are where ResolveTenant sends people.
        Route::get('/no-organization', [SwitcherController::class, 'none'])->name('no-organization');
        Route::post('/subscription/renew', [SubscriptionController::class, 'renew'])->middleware('subscriptions.on')->middleware('not-impersonating')->name('subscription.renew');
        Route::get('/status', [SwitcherController::class, 'status'])->name('organization.status');
    });
});
