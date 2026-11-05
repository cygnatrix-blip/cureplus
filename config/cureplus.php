<?php

return [

    /*
    | Host that serves the portals. Anything else is treated as a custom domain
    | (Phase 7) and can only ever reach the public booking site.
    */
    'domain' => env('APP_DOMAIN', 'cureplus.cygnatrix.com'),

    'custom_domains' => [
        'connect_host' => env('CONNECT_HOST', 'connect.cureplus.cygnatrix.com'),
        'ipv4' => env('PLATFORM_IPV4', '82.29.191.59'),
        'ipv6' => env('PLATFORM_IPV6', '2a02:4780:a:2211:0:2396:1cf1:7'),
    ],

    // Suggestions on the verification form. A doctor can type another.
    'medical_councils' => [
        'National Medical Commission', 'National Commission for Indian System of Medicine', 'National Commission for Homoeopathy', 'National Commission for Allied and Healthcare Professions', 'State Dental Council', 'State Board of Indian Medicine (Ayurveda, Unani, Siddha)', 'State Homoeopathic Council', 'State Board of Yoga and Naturopathy', 'State Allied and Healthcare Council', 'Indian Dietetic Association', 'Andhra Pradesh Medical Council', 'Arunachal Pradesh Medical Council', 'Assam Medical Council', 'Bihar Medical Council', 'Chhattisgarh Medical Council', 'Delhi Medical Council', 'Goa Medical Council', 'Gujarat Medical Council', 'Haryana Medical Council', 'Himachal Pradesh Medical Council', 'Jammu and Kashmir Medical Council', 'Jharkhand Medical Council', 'Karnataka Medical Council', 'Kerala (Travancore Cochin) Medical Council', 'Madhya Pradesh Medical Council', 'Maharashtra Medical Council', 'Manipur Medical Council', 'Meghalaya Medical Council', 'Mizoram Medical Council', 'Nagaland Medical Council', 'Odisha Council of Medical Registration', 'Punjab Medical Council', 'Rajasthan Medical Council', 'Sikkim Medical Council', 'Tamil Nadu Medical Council', 'Telangana State Medical Council', 'Tripura State Medical Council', 'Uttar Pradesh Medical Council', 'Uttarakhand Medical Council', 'West Bengal Medical Council',
    ],

    'video' => [
        // How long a patient has to pay for a video call they booked before the slot is released.
        'pay_within_minutes' => (int) env('VIDEO_PAY_WITHIN_MINUTES', 30),
    ],

    'organizations' => [
        // New organizations wait for a platform admin unless this is switched off.
        'require_approval' => (bool) env('ORG_REQUIRE_APPROVAL', true),
        // Patients can only book a doctor whose registration has been checked.
        'doctors_need_approval' => (bool) env('DOCTOR_REQUIRE_APPROVAL', true),
    ],

    /*
    | Booking links live at /{org-slug}, so every first-level path the
    | application uses must be unavailable as a slug. Add new top-level routes here.
    */
    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'book', 'brand', 'build', 'connect', 'css', 'display', 'email', 'favicon', 'files', 'forgot-password', 'pay',
        'health', 'help', 'icons', 'img', 'internal', 'invitations', 'js', 'login', 'logout', 'manifests', 'offline', 'patient',
        'privacy', 'register', 'reset-password', 'robots', 'storage', 'support', 'sw', 'terms', 'two-factor', 'up',
        'verify', 'webhooks', 'www',
    ],

    'security' => [
        'max_login_attempts' => (int) env('AUTH_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
        // How long a password-verified login may wait on the 2FA screen.
        'two_factor_pending_minutes' => 10,
        // Roles that must have 2FA before they can use the portal.
        'two_factor_roles' => ['owner', 'doctor'],
        'trusted_proxies' => env('TRUSTED_PROXIES'),
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

    'invitations' => [
        'ttl_days' => 7,
    ],

    // How long a patient's consent to share records with a doctor lasts.
    'consent_days' => (int) env('CONSENT_DAYS', 30),

    // How long a prescription or bill link sent to a patient keeps working: 30 days. Long enough
    // to reopen at the pharmacy or a follow-up, and short enough that an old forwarded link dies.
    'document_link_hours' => (int) env('DOCUMENT_LINK_HOURS', 720),

    'locales' => ['en' => 'English', 'hi' => 'हिन्दी', 'bn' => 'বাংলা'],

    'heartbeat' => [
        // The scheduler counts as unhealthy after this many seconds of silence.
        'max_age_seconds' => 180,
    ],

    'backups' => [
        'disk' => env('BACKUP_DISK', 'local'),
        // Optional second disk for a copy away from this server.
        'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'key' => env('BACKUP_KEY'),
    ],

    'monitoring' => [
        // Gmail's limit. Monitoring warns at 80% of this.
        'daily_email_limit' => (int) env('DAILY_EMAIL_LIMIT', 500),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'token' => env('SMS_HTTP_TOKEN'),
            'sender' => env('SMS_SENDER_ID'),
            'timeout' => (int) env('SMS_HTTP_TIMEOUT', 5),
        ],
    ],

    'captcha' => [
        'driver' => env('CAPTCHA_DRIVER', 'none'),
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],

    /*
    | One installable PWA per portal. Scope keeps each installed app on its own paths.
    */
    'portals' => [
        'admin' => [
            'name' => 'CurePlus Admin',
            'short_name' => 'CP Admin',
            'start_url' => '/admin',
            'scope' => '/admin',
            'theme_color' => '#020617',
            'icon' => 'admin',
        ],
        'app' => [
            'name' => 'CurePlus Clinic',
            'short_name' => 'CurePlus',
            'start_url' => '/app',
            'scope' => '/app',
            'theme_color' => '#088575',
            'icon' => 'app',
        ],
        'patient' => [
            'name' => 'CurePlus Patient',
            'short_name' => 'My Health',
            'start_url' => '/patient',
            'scope' => '/patient',
            'theme_color' => '#2563eb',
            'icon' => 'patient',
        ],
        'display' => [
            'name' => 'CurePlus Queue Display',
            'short_name' => 'Queue',
            'start_url' => '/display',
            'scope' => '/display',
            'theme_color' => '#0f172a',
            'icon' => 'display',
            'display' => 'fullscreen',
        ],
    ],
];
