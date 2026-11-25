<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

class MissingConfigurationException extends EmqxException
{
    public static function forApi(): self
    {
        return new self('The EMQX API is not configured: set EMQX_API_URL, EMQX_API_KEY and EMQX_API_SECRET.');
    }
}
