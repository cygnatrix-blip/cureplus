<?php

return [
    // Off: no plan pages, limits, trial expiry or suspension. Charges are agreed with each practice directly.
    'enabled' => (bool) env('SUBSCRIPTIONS_ENABLED', false),

    'default_plan' => env('DEFAULT_PLAN', 'starter'),
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    // Each feature and the portal routes it switches. Anything not listed is always available.
    'features' => [
        'billing' => ['label' => 'Billing', 'routes' => ['app.invoices.*', 'app.dues.*', 'app.billing-settings.*']],
        'reports' => ['label' => 'Reports', 'routes' => ['app.reports.*']],
        'follow_ups' => ['label' => 'Follow-up list', 'routes' => ['app.follow-ups.*']],
        'online_booking' => ['label' => 'Online booking', 'routes' => ['app.booking-links.*']],
        'online_payments' => ['label' => 'Online payments', 'routes' => []],
        'whatsapp' => ['label' => 'WhatsApp messages', 'routes' => []],
        'custom_domain' => ['label' => 'Custom domain', 'routes' => ['app.domains.*']],
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'subscription_cycles' => (int) env('RAZORPAY_SUBSCRIPTION_CYCLES', 120),
    ],
];
