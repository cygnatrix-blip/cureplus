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
        'video_consult' => [
            'label' => 'Video call details',
            'whatsapp_template' => 'video_consult',
            'params' => ['name', 'doctor', 'clinic', 'time', 'link'],
            'text' => 'Hi {name}, your video call with {doctor} of {clinic} is on {time}. Join here: {link}',
            'subject' => 'Your video call with {clinic}',
            'push_title' => 'Video call details',
        ],
        'video_consult_now' => [
            'label' => 'Video call starting',
            'whatsapp_template' => 'video_consult_now',
            'params' => ['name', 'doctor', 'link'],
            'text' => 'Hi {name}, {doctor} is ready for your video call. Please join now: {link}',
            'subject' => 'Your video call is starting',
            'push_title' => 'Your video call is starting',
        ],
        'video_payment_missed' => [
            'label' => 'Unpaid video call released',
            'whatsapp_template' => 'video_payment_missed',
            'params' => ['name', 'doctor', 'clinic', 'time'],
            'text' => 'Hi {name}, your video call with {doctor} of {clinic} on {time} was released because payment did not reach us. Please book again.',
            'subject' => 'Your video call was released',
            'push_title' => 'Video call released',
        ],
        'chat_message' => [
            'label' => 'Messages from your doctor',
            'free_only' => true,
            'whatsapp_template' => null,
            'params' => ['name', 'doctor', 'clinic'],
            'text' => 'Hi {name}, {doctor} at {clinic} sent you a message. Sign in to read it.',
            'subject' => 'New message from {doctor}',
            'push_title' => 'New message from your doctor',
        ],
        'appointment_payment_missed' => [
            'label' => 'Unpaid booking released',
            'whatsapp_template' => 'appointment_payment_missed',
            'params' => ['name', 'doctor', 'clinic', 'time'],
            'text' => 'Hi {name}, your appointment with {doctor} at {clinic} on {time} was released because payment did not reach us. Please book again.',
            'subject' => 'Your appointment was released',
            'push_title' => 'Appointment released',
        ],
        'upi_payment_not_found' => [
            'label' => 'UPI payment not found',
            'whatsapp_template' => 'upi_payment_not_found',
            'params' => ['name', 'amount', 'clinic'],
            'text' => 'Hi {name}, {clinic} could not find your UPI payment of {amount}. Please check the transaction ID and send it again, or contact the clinic.',
            'subject' => '{clinic} could not find your payment',
            'push_title' => 'Payment not found',
        ],
        'video_refund_needed' => [
            'label' => 'Booking paid too late',
            'whatsapp_template' => 'video_refund_needed',
            'params' => ['name', 'clinic', 'time'],
            'text' => 'Hi {name}, a patient of {clinic} paid for an appointment on {time} after its slot had gone. Please refund or rebook them.',
            'subject' => 'A patient of {clinic} needs a refund or a new slot',
            'push_title' => 'Refund or rebook a patient',
        ],
        'appointment_cancelled' => [
            'label' => 'Appointment cancellations',
            'whatsapp_template' => 'appointment_cancelled',
            'params' => ['name', 'doctor', 'clinic', 'time'],
            'text' => 'Hi {name}, your appointment with {doctor} at {clinic} on {time} has been cancelled. Please contact the clinic to book again.',
            'subject' => 'Your appointment at {clinic} was cancelled',
            'push_title' => 'Appointment cancelled',
        ],
        'appointment_moved' => [
            'label' => 'Appointment changes',
            'whatsapp_template' => 'appointment_moved',
            'params' => ['name', 'doctor', 'clinic', 'time'],
            'text' => 'Hi {name}, your appointment with {doctor} at {clinic} has moved to {time}.',
            'subject' => 'Your appointment at {clinic} has moved',
            'push_title' => 'Appointment moved',
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
        'organization_access_changed' => ['whatsapp_template' => 'organization_access_changed', 'params' => ['name', 'clinic', 'date'], 'text' => 'Hi {name}, access to {clinic} now runs until: {date}.', 'subject' => 'Your CurePlus access for {clinic}', 'push_title' => 'Access updated'],
        'organization_access_ending' => ['whatsapp_template' => 'organization_access_ending', 'params' => ['name', 'clinic', 'date'], 'text' => 'Hi {name}, access to {clinic} ends on {date}. Please contact us to renew so your work is not interrupted.', 'subject' => 'Your CurePlus access for {clinic} ends on {date}', 'push_title' => 'Access ending soon'],
        'organization_signup' => ['whatsapp_template' => null, 'params' => [], 'text' => 'New signup: {clinic} ({type}) is waiting for approval.', 'subject' => 'New signup waiting for approval: {clinic}', 'push_title' => 'New signup'],
        'organization_resubmitted' => ['whatsapp_template' => null, 'params' => [], 'text' => '{clinic} has fixed its details and is waiting for approval again.', 'subject' => 'Sent for review again: {clinic}', 'push_title' => 'Review again'],
        'organization_approved' => ['whatsapp_template' => 'organization_approved', 'params' => ['name', 'clinic'], 'text' => 'Hi {name}, {clinic} is verified. You can now register patients and share your booking link.', 'subject' => '{clinic} is verified', 'push_title' => 'Account verified'],
        'organization_rejected' => ['whatsapp_template' => 'organization_rejected', 'params' => ['name', 'clinic', 'reason'], 'text' => 'Hi {name}, we could not verify {clinic} yet. Reason: {reason}. Please sign in, fix this and send it again.', 'subject' => 'We could not verify {clinic} yet', 'push_title' => 'Verification needs attention'],
        'organization_restored' => ['whatsapp_template' => 'organization_restored', 'params' => ['name', 'clinic'], 'text' => 'Hi {name}, access to {clinic} has been restored.', 'subject' => 'Access to {clinic} is restored', 'push_title' => 'Access restored'],
    ],

    'staff_events' => [
        'announcement' => ['label' => 'Platform announcements', 'push_title' => 'Announcement', 'text' => '{title}: {body}'],
        'chat_message_staff' => ['label' => 'Messages from patients', 'push_title' => 'New patient message', 'text' => 'A patient sent you a message about their appointment on {time}.'],
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
