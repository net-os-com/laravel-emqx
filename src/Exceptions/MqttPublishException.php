<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

use NetOs\Emqx\Mqtt\MqttSettings;
use RuntimeException;
use Throwable;

class MqttPublishException extends RuntimeException
{
    public static function couldNotConnect(MqttSettings $settings, Throwable $previous): self
    {
        return new self("Could not connect to the MQTT broker at [{$settings->host}:{$settings->port}].", previous: $previous);
    }

    public static function couldNotPublish(MqttSettings $settings, Throwable $previous): self
    {
        return new self("Could not publish to the MQTT broker at [{$settings->host}:{$settings->port}].", previous: $previous);
    }

    public static function notAcknowledged(MqttSettings $settings, int $count): self
    {
        return new self("The MQTT broker at [{$settings->host}:{$settings->port}] did not acknowledge {$count} message(s) in time.");
    }
}
