<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every error raised by the EMQX REST API.
 *
 * Carries the HTTP status, the EMQX error code and the URL, but never the
 * request body or the credentials: the body of a user call holds a password.
 */
class EmqxException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly ?string $url = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
