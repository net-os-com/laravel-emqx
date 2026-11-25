<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

use Throwable;

class RateLimitException extends EmqxException
{
    public function __construct(
        string $message,
        int $status = 429,
        ?string $errorCode = null,
        ?string $url = null,
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $errorCode, $url, $previous);
    }

    /**
     * Seconds to wait before retrying, when EMQX said so.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
