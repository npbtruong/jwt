<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a client_id / client_secret pair fails verification.
 * Mapped to a 401 envelope by the exception handler.
 */
final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Invalid client credentials')
    {
        parent::__construct($message);
    }
}
