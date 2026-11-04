<?php

namespace App\Enums;

enum LoginResult
{
    case Success;
    case Invalid;
    case Locked;
}
