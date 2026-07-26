<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown by the JWT codec on a malformed token or a bad signature. */
final class TokenInvalidException extends RuntimeException {}
