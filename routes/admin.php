<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BreachController;
use App\Http\Controllers\Admin\CustomDomainController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataRequestController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\MedicineController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\OrganizationApprovalController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformAdminController;
use App\Http\Controllers\Admin\PlatformIntegrationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SecurityCenterController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

// Reached from the clinic portal's banner, where the platform guard is not the default.
Route::post('/admin/impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth:platform')
    ->name('admin.impersonation.stop');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    });

    Route::middleware(['auth:platform', 'account.active', 'no-store'])->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/two-factor', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
        Route::post('/two-factor/totp', [TwoFactorSetupController::class, 'confirmTotp'])->middleware('throttle:otp-verify')->name('two-factor.totp');
        Route::post('/two-factor/otp/send', [TwoFactorSetupController::class, 'sendOtp'])->middleware('throttle:otp')->name('two-factor.otp.send');
        Route::post('/two-factor/otp', [TwoFactorSetupController::class, 'confirmOtp'])->middleware('throttle:otp-verify')->name('two-factor.otp');

        Route::middleware('two-factor')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');

            Route::get('/approvals', [OrganizationApprovalController::class, 'index'])->can('platform.approvals.manage')->name('approvals.index');
            Route::get('/approvals/{organization}', [OrganizationApprovalController::class, 'show'])->can('platform.approvals.manage')->name('approvals.show');
            Route::get('/approvals/{organization}/documents/{document}', [OrganizationApprovalController::class, 'document'])->whereNumber('document')->can('platform.approvals.manage')->name('approvals.document');
            Route::post('/approvals/{organization}/reject', [OrganizationApprovalController::class, 'reject'])->can('platform.approvals.manage')->name('approvals.reject');

            Route::get('/organizations', [OrganizationController::class, 'index'])->can('platform.organizations.view')->name('organizations.index');
            Route::get('/organizations/export', [OrganizationController::class, 'export'])->can('platform.organizations.view')->name('organizations.export');
            Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->can('platform.organizations.view')->name('organizations.show');
            Route::post('/organizations/{organization}/approve', [OrganizationApprovalController::class, 'approve'])->can('platform.approvals.manage')->name('organizations.approve');
            Route::post('/organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->can('platform.organizations.manage')->name('organizations.suspend');
            Route::post('/organizations/{organization}/impersonate', [ImpersonationController::class, 'start'])->can('platform.impersonate')->name('organizations.impersonate');

            Route::get('/medicines', [MedicineController::class, 'index'])->can('platform.catalogue.manage')->name('medicines.index');
            Route::post('/medicines', [MedicineController::class, 'store'])->can('platform.catalogue.manage')->name('medicines.store');
            Route::post('/medicines/import', [MedicineController::class, 'import'])->can('platform.catalogue.manage')->name('medicines.import');
            Route::put('/medicines/{medicine}', [MedicineController::class, 'update'])->can('platform.catalogue.manage')->name('medicines.update');
            Route::get('/catalogue-requests', [MedicineController::class, 'requests'])->can('platform.catalogue.manage')->name('medicines.requests');
            Route::post('/catalogue-requests/{request}/approve', [MedicineController::class, 'approve'])->can('platform.catalogue.manage')->name('medicines.requests.approve');
            Route::post('/catalogue-requests/{request}/reject', [MedicineController::class, 'reject'])->can('platform.catalogue.manage')->name('medicines.requests.reject');

            Route::get('/domains', [CustomDomainController::class, 'index'])->can('platform.domains.manage')->name('domains.index');
            Route::post('/domains/{domain}/check', [CustomDomainController::class, 'check'])->can('platform.domains.manage')->name('domains.check');
            Route::post('/domains/{domain}/park', [CustomDomainController::class, 'park'])->can('platform.domains.manage')->name('domains.park');
            Route::post('/domains/{domain}/retry', [CustomDomainController::class, 'retry'])->can('platform.domains.manage')->name('domains.retry');

            Route::get('/monitoring', MonitoringController::class)->can('platform.monitoring.view')->name('monitoring.index');

            Route::get('/backups', [BackupController::class, 'index'])->can('platform.backups.manage')->name('backups.index');
            Route::post('/backups', [BackupController::class, 'store'])->can('platform.backups.manage')->name('backups.store');
            Route::post('/backups/restore-tests', [BackupController::class, 'logRestoreTest'])->can('platform.backups.manage')->name('backups.restore-tests');
            Route::post('/backups/{run}/verify', [BackupController::class, 'verify'])->can('platform.backups.manage')->name('backups.verify');
            Route::get('/backups/{run}/download', [BackupController::class, 'download'])->can('platform.backups.manage')->name('backups.download');

            Route::get('/api-keys', [ApiKeyController::class, 'index'])->can('platform.api-keys.manage')->name('api-keys.index');
            Route::post('/api-keys', [ApiKeyController::class, 'store'])->can('platform.api-keys.manage')->name('api-keys.store');
            Route::post('/api-keys/{key}/revoke', [ApiKeyController::class, 'revoke'])->can('platform.api-keys.manage')->name('api-keys.revoke');

            Route::get('/integrations', [PlatformIntegrationController::class, 'index'])->can('platform.integrations.manage')->name('integrations.index');
            Route::post('/integrations', [PlatformIntegrationController::class, 'save'])->can('platform.integrations.manage')->name('integrations.save');
            Route::post('/integrations/test-email', [PlatformIntegrationController::class, 'testEmail'])->middleware('throttle:10,1')->can('platform.integrations.manage')->name('integrations.test-email');
            Route::post('/integrations/vapid', [PlatformIntegrationController::class, 'generateVapid'])->can('platform.integrations.manage')->name('integrations.vapid');

            Route::get('/system', [SystemController::class, 'index'])->can('platform.system.manage')->name('system.index');
            Route::post('/system', [SystemController::class, 'save'])->can('platform.system.manage')->name('system.save');
            Route::post('/system/maintenance', [SystemController::class, 'maintenance'])->can('platform.system.manage')->name('system.maintenance');

            Route::get('/breaches', [BreachController::class, 'index'])->can('platform.breaches.manage')->name('breaches.index');
            Route::post('/breaches', [BreachController::class, 'store'])->can('platform.breaches.manage')->name('breaches.store');
            Route::put('/breaches/{incident}', [BreachController::class, 'update'])->can('platform.breaches.manage')->name('breaches.update');

            Route::post('/security/block-ip', [SecurityCenterController::class, 'blockIp'])->can('platform.security.manage')->name('security.block-ip');
            Route::delete('/security/block-ip/{blockedIp}', [SecurityCenterController::class, 'unblockIp'])->can('platform.security.manage')->name('security.unblock-ip');
            Route::post('/security/users/{user}/reset-2fa', [SecurityCenterController::class, 'resetTwoFactor'])->can('platform.security.manage')->name('security.reset-2fa');
            Route::post('/admins/{admin}/reset', [PlatformAdminController::class, 'resetPassword'])->can('platform.admins.manage')->name('admins.reset');

            Route::get('/plans', [PlanController::class, 'index'])->middleware('subscriptions.on')->can('platform.plans.manage')->name('plans.index');
            Route::post('/plans', [PlanController::class, 'store'])->middleware('subscriptions.on')->can('platform.plans.manage')->name('plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->middleware('subscriptions.on')->can('platform.plans.manage')->name('plans.update');
            Route::post('/coupons', [PlanController::class, 'storeCoupon'])->middleware('subscriptions.on')->can('platform.plans.manage')->name('coupons.store');
            Route::post('/organizations/{organization}/plan', [ReportController::class, 'changePlan'])->middleware('subscriptions.on')->can('platform.plans.manage')->name('subscriptions.change-plan');

            Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->can('platform.flags.manage')->name('feature-flags.index');
            Route::post('/feature-flags', [FeatureFlagController::class, 'store'])->can('platform.flags.manage')->name('feature-flags.store');
            Route::delete('/feature-flags/{flag}', [FeatureFlagController::class, 'destroy'])->can('platform.flags.manage')->name('feature-flags.destroy');

            Route::get('/support', [SupportController::class, 'index'])->can('platform.support.manage')->name('support.index');
            Route::get('/support/{ticket}', [SupportController::class, 'show'])->can('platform.support.manage')->name('support.show');
            Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->can('platform.support.manage')->name('support.reply');

            Route::get('/announcements', [AnnouncementController::class, 'index'])->can('platform.content.manage')->name('announcements.index');
            Route::post('/announcements', [AnnouncementController::class, 'store'])->can('platform.content.manage')->name('announcements.store');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->can('platform.content.manage')->name('announcements.destroy');

            Route::get('/templates', [TemplateController::class, 'index'])->can('platform.content.manage')->name('templates.index');
            Route::post('/templates/emr', [TemplateController::class, 'storeEmr'])->can('platform.content.manage')->name('templates.emr');
            Route::post('/templates/message', [TemplateController::class, 'saveMessage'])->can('platform.content.manage')->name('templates.message');
            Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->can('platform.content.manage')->name('templates.destroy');

            Route::get('/reports', [ReportController::class, 'index'])->can('platform.reports.view')->name('reports.index');

            Route::get('/data-requests', [DataRequestController::class, 'index'])->can('platform.privacy.manage')->name('data-requests.index');
            Route::post('/data-requests/{dataRequest}/resolve', [DataRequestController::class, 'resolve'])->can('platform.privacy.manage')->name('data-requests.resolve');

            Route::get('/doctors', [DoctorController::class, 'index'])->can('platform.doctors.manage')->name('doctors.index');
            Route::post('/doctors/{profile}/verify', [DoctorController::class, 'verify'])->can('platform.doctors.manage')->name('doctors.verify');

            Route::get('/audit-log', [AuditLogController::class, 'index'])->can('platform.audit.view')->name('audit.index');
            Route::get('/audit-log/export', [AuditLogController::class, 'export'])->can('platform.audit.view')->name('audit.export');

            Route::get('/security', [SecurityCenterController::class, 'index'])->can('platform.security.manage')->name('security.index');
            Route::post('/security/users/{user}/logout', [SecurityCenterController::class, 'forceLogout'])->can('platform.security.manage')->name('security.force-logout');

            Route::get('/admins', [PlatformAdminController::class, 'index'])->can('platform.admins.manage')->name('admins.index');
            Route::post('/admins', [PlatformAdminController::class, 'store'])->can('platform.admins.manage')->name('admins.store');
            Route::post('/admins/{admin}/deactivate', [PlatformAdminController::class, 'deactivate'])->can('platform.admins.manage')->name('admins.deactivate');
        });
    });
});
