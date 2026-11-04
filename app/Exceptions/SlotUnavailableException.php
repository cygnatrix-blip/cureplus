<?php

namespace App\Exceptions;

use RuntimeException;

class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'That slot has just been taken. Please choose another.')
    {
        parent::__construct($message);
    }
}
