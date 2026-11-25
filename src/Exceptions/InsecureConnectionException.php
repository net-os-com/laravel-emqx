<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

use LogicException;

class InsecureConnectionException extends LogicException
{
    public static function inEnvironment(string $environment): self
    {
        return new self("MQTT TLS can not be turned off in the [{$environment}] environment.");
    }
}
