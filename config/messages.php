<?php

/*
| Every automatic message. WhatsApp sends the named template, which must be approved in
| Meta Business Manager with these body parameters in this order. SMS text must match a
| DLT-registered template word for word before Indian carriers will deliver it.
| Message text stays free of clinical detail: lock screens show it to anyone nearby.
*/
return [
    'events' => [
        'appointment_booked' => [
            'label' => 'Booking confirmations',
            'whatsapp_template' => 'appointment_booked',
            'params' => ['name', 'doctor', 'clinic', 'time', 'token'],
            'text' => 'Hi {name}, your appointment with {doctor} at {clinic} is on {time}. Token {token}.',
            'subject' => 'Your appointment at {clinic}',
            'push_title' => 'Appointment booked',
        ],
        'appointment_reminder' => [
            'label' => 'Appointment reminders',
            'whatsapp_template' => 'appointment_reminder',
            'params' => ['name', 'doctor', 'clinic', 'time'],
            'text' => 'Hi {name}, reminder: your appointment with {doctor} at {clinic} is on {time}.',
            'subject' => 'Appointment reminder from {clinic}',
            'push_title' => 'Appointment reminder',
        ],
        'token_called' => [
            'label' => 'Your turn in the queue',
            'whatsapp_template' => 'token_called',
            'params' => ['name', 'token', 'doctor'],
            'text' => 'Hi {name}, token {token} is being called by {doctor}. Please come in.',
            'subject' => 'Your token has been called',
            'push_title' => 'It is your turn',
        ],
        'follow_up_due' => [
            'label' => 'Follow-up reminders',
            'whatsapp_template' => 'follow_up_due',
            'params' => ['name', 'doctor', 'clinic', 'date'],
            'text' => 'Hi {name}, your follow-up with {doctor} at {clinic} is due on {date}. Please book a visit.',
            'subject' => 'Your follow-up is due',
            'push_title' => 'Follow-up due',
        ],
        'payment_received' => [
            'label' => 'Payment receipts',
            'whatsapp_template' => 'payment_received',
            'params' => ['name', 'amount', 'clinic'],
            'text' => 'Hi {name}, we received {amount} at {clinic}. Thank you.',
            'subject' => 'Payment received by {clinic}',
            'push_title' => 'Payment received',
        ],
        'payment_link' => [
            'label' => 'Payment links',
            'whatsapp_template' => 'payment_link',
            'params' => ['name', 'amount', 'clinic', 'link'],
            'text' => 'Hi {name}, please pay {amount} to {clinic} here: {link}',
            'subject' => 'Your bill from {clinic}',
            'push_title' => 'Payment link',
        ],
        'document_ready' => [
            'label' => 'Prescriptions and bills',
            'whatsapp_template' => 'document_ready',
            'params' => ['name', 'document', 'clinic', 'link'],
            'text' => 'Hi {name}, your {document} from {clinic} is ready: {link}',
            'subject' => 'Your {document} from {clinic}',
            'push_title' => 'Document ready',
        ],
    ],

    // From the platform to practices, sent with the platform's own sender. Not mutable: they concern the account itself.
    'account_events' => [
        'doctor_verification_requested' => ['whatsapp_template' => null, 'params' => [], 'text' => '{doctor} has sent their registration details and is waiting to be checked.', 'subject' => 'Doctor waiting to be checked: {doctor}', 'push_title' => 'Doctor to check'],
        'doctor_verified' => ['whatsapp_template' => 'doctor_verified', 'params' => ['name'], 'text' => 'Hi {name}, your registration is checked. Patients can now book appointments with you.', 'subject' => 'Your registration is checked', 'push_title' => 'Registration checked'],
        'doctor_rejected' => ['whatsapp_template' => 'doctor_rejected', 'params' => ['name', 'reason'], 'text' => 'Hi {name}, we could not confirm your registration. Reason: {reason}. Please sign in and correct your details under My account.', 'subject' => 'We could not confirm your registration', 'push_title' => 'Registration needs attention'],
        'organization_signup' => ['whatsapp_template' => null, 'params' => [], 'text' => 'New signup: {clinic} ({type}) is waiting for approval.', 'subject' => 'New signup waiting for approval: {clinic}', 'push_title' => 'New signup'],
        'organization_resubmitted' => ['whatsapp_template' => null, 'params' => [], 'text' => '{clinic} has fixed its details and is waiting for approval again.', 'subject' => 'Sent for review again: {clinic}', 'push_title' => 'Review again'],
        'organization_approved' => ['whatsapp_template' => 'organization_approved', 'params' => ['name', 'clinic'], 'text' => 'Hi {name}, {clinic} is verified. You can now register patients and share your booking link.', 'subject' => '{clinic} is verified', 'push_title' => 'Account verified'],
        'organization_rejected' => ['whatsapp_template' => 'organization_rejected', 'params' => ['name', 'clinic', 'reason'], 'text' => 'Hi {name}, we could not verify {clinic} yet. Reason: {reason}. Please sign in, fix this and send it again.', 'subject' => 'We could not verify {clinic} yet', 'push_title' => 'Verification needs attention'],
        'organization_restored' => ['whatsapp_template' => 'organization_restored', 'params' => ['name', 'clinic'], 'text' => 'Hi {name}, access to {clinic} has been restored.', 'subject' => 'Access to {clinic} is restored', 'push_title' => 'Access restored'],
    ],

    'staff_events' => [
        'announcement' => ['label' => 'Platform announcements', 'push_title' => 'Announcement', 'text' => '{title}: {body}'],
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'cloud'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'push' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'reminders' => [
        // Nobody wants a clinic reminder at midnight.
        'quiet_before' => 8,
        'quiet_after' => 21,
        'batch_size' => 500,
    ],
];
