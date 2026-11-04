<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A billing rule was broken. The message is written for the person at the desk.
 */
class BillingException extends RuntimeException {}
