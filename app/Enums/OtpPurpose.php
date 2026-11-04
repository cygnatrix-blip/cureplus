<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case VerifyMobile = 'verify_mobile';
    case TwoFactor = 'two_factor';
    case PatientLogin = 'patient_login';
    case ConsentShare = 'consent_share';
}
