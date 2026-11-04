<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The code could not be emailed. The person is told to try again rather than
 * left waiting for a message that will never arrive.
 */
class OtpDeliveryException extends RuntimeException {}
