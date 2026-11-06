<?php

namespace App\Exceptions;

use RuntimeException;

class ChatClosedException extends RuntimeException
{
    public function __construct(string $message = 'This chat is closed.')
    {
        parent::__construct($message);
    }
}
