<?php

namespace App\Exceptions;

use RuntimeException;

class ChatLimitException extends RuntimeException
{
    public function __construct(string $message = 'You can send up to 20 files a day in this chat. Please try again tomorrow.')
    {
        parent::__construct($message);
    }
}
