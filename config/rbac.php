<?php

/*
| Source of truth for roles and permissions. `php artisan cureplus:sync-rbac`
| copies this into the database; deploy.sh runs it after every migration.
*/
return [

    'permissions' => [
        'dashboard.view' => ['group' => 'General', 'label' => 'View dashboard'],
        'patients.view' => ['group' => 'Patients', 'label' => 'View patients'],
        'patients.manage' => ['group' => 'Patients', 'label' => 'Register and edit patients'],
        'appointments.view' => ['group' => 'Appointments', 'label' => 'View appointments and queue'],
        'appointments.manage' => ['group' => 'Appointments', 'label' => 'Book, reschedule and cancel'],
        'consultations.manage' => ['group' => 'Clinical', 'label' => 'Record consultations'],
        'vitals.manage' => ['group' => 'Clinical', 'label' => 'Record vitals'],
        'prescriptions.manage' => ['group' => 'Clinical', 'label' => 'Write prescriptions'],
        'billing.view' => ['group' => 'Billing', 'label' => 'View bills and receipts'],
        'billing.manage' => ['group' => 'Billing', 'label' => 'Create bills and take payments'],
        'billing.refund' => ['group' => 'Billing', 'label' => 'Issue refunds and discounts'],
        'reports.view' => ['group' => 'Reports', 'label' => 'View reports'],
        'staff.manage' => ['group' => 'Administration', 'label' => 'Manage staff'],
        'roles.manage' => ['group' => 'Administration', 'label' => 'Change role permissions'],
        'locations.manage' => ['group' => 'Administration', 'label' => 'Manage locations'],
        'settings.manage' => ['group' => 'Administration', 'label' => 'Manage settings'],
        'integrations.manage' => ['group' => 'Administration', 'label' => 'Manage integrations'],
        'subscription.manage' => ['group' => 'Administration', 'label' => 'Manage subscription'],
        'audit.view' => ['group' => 'Administration', 'label' => 'View audit log'],
    ],

    /*
    | Owner always has every permission and cannot be edited: an organization
    | must never be able to lock itself out of its own settings.
    */
    'roles' => [
        'owner' => ['name' => 'Owner', 'editable' => false, 'permissions' => ['*']],
        'doctor' => ['name' => 'Doctor', 'editable' => true, 'permissions' => [
            'dashboard.view', 'patients.view', 'patients.manage', 'appointments.view', 'appointments.manage',
            'consultations.manage', 'vitals.manage', 'prescriptions.manage', 'billing.view',
        ]],
        'receptionist' => ['name' => 'Receptionist', 'editable' => true, 'permissions' => [
            'dashboard.view', 'patients.view', 'patients.manage', 'appointments.view', 'appointments.manage',
            'billing.view', 'billing.manage',
        ]],
        'nurse' => ['name' => 'Nurse', 'editable' => true, 'permissions' => [
            'dashboard.view', 'patients.view', 'appointments.view', 'vitals.manage',
        ]],
        'accountant' => ['name' => 'Accountant', 'editable' => true, 'permissions' => [
            'dashboard.view', 'billing.view', 'billing.refund', 'reports.view',
        ]],
    ],

    /*
    | Platform staff are a separate account type with a fixed ability map.
    */
    'platform_roles' => [
        'super_admin' => ['name' => 'Super admin', 'abilities' => ['*']],
        'support' => ['name' => 'Support', 'abilities' => [
            'platform.organizations.view', 'platform.impersonate', 'platform.audit.view', 'platform.doctors.manage', 'platform.support.manage', 'platform.domains.manage', 'platform.monitoring.view',
        ]],
        'sales' => ['name' => 'Sales', 'abilities' => [
            'platform.organizations.view', 'platform.organizations.manage', 'platform.reports.view',
        ]],
        'finance' => ['name' => 'Finance', 'abilities' => [
            'platform.organizations.view', 'platform.audit.view', 'platform.plans.manage', 'platform.reports.view',
        ]],
    ],

    'platform_abilities' => [
        'platform.organizations.view',
        'platform.organizations.manage',
        'platform.approvals.manage',
        'platform.impersonate',
        'platform.audit.view',
        'platform.security.manage',
        'platform.admins.manage',
        'platform.catalogue.manage',
        'platform.doctors.manage',
        'platform.privacy.manage',
        'platform.plans.manage',
        'platform.flags.manage',
        'platform.support.manage',
        'platform.content.manage',
        'platform.reports.view',
        'platform.domains.manage',
        'platform.monitoring.view',
        'platform.backups.manage',
        'platform.api-keys.manage',
        'platform.integrations.manage',
        'platform.system.manage',
        'platform.breaches.manage',
    ],
];
