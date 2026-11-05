<?php

/*
| Every kind of practitioner who can register, by medical system. Doctor profiles, the medicine
| catalogue and the platform's templates all use these exact names.
*/
return [
    'systems' => [
        'Allopathy' => [
            'General Physician', 'Dentist', 'Gynecologist', 'Pediatrician', 'Dermatologist', 'Orthopedic', 'ENT', 'Ophthalmologist', 'Psychiatrist', 'Cardiologist', 'Neurologist', 'Urologist', 'Gastroenterologist', 'Oncologist',
            'Endocrinologist', 'Diabetologist', 'Pulmonologist', 'Nephrologist', 'Rheumatologist', 'Hematologist', 'General Surgeon', 'Neurosurgeon',
        ],
        'Ayurveda' => ['Ayurveda'],
        'Homeopathy' => ['Homeopathy'],
        'Unani' => ['Unani'],
        'Siddha' => ['Siddha'],
        'Yoga & Naturopathy' => ['Yoga & Naturopathy'],
        'Allied Health' => ['Physiotherapist', 'Dietitian', 'Occupational Therapist', 'Speech Therapist', 'Clinical Psychologist', 'Optometrist'],
    ],

    'other' => 'Other',

    /*
    | Allied health professionals prescribe only within their own field: products filed under
    | their specialty, plus anything named here.
    */
    'allied_also_allowed' => [
        'Dietitian' => ['Vitamin D3', 'Vitamin C', 'Vitamin B Complex', 'Multivitamin', 'Zinc Sulphate', 'Calcium Carbonate', 'Calcium + Vitamin D3', 'Ferrous Ascorbate', 'Folic Acid', 'Methylcobalamin', 'ORS', 'Psyllium Husk', 'Probiotic Capsule'],
    ],
];
