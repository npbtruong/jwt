<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown by the JWT codec when a token's `exp` has passed. */
final class TokenExpiredException extends RuntimeException {}
