<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

use LogicException;

class UnsafeTopicException extends LogicException
{
    public static function outsidePrefix(string $topic, string $prefix): self
    {
        return new self("The MQTT topic [{$topic}] is outside the configured prefix [{$prefix}].");
    }
}
