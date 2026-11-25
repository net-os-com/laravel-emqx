<?php

declare(strict_types=1);

namespace NetOs\Emqx\Facades;

use Illuminate\Support\Facades\Facade;
use NetOs\Emqx\Contracts\MqttPublisher;
use NetOs\Emqx\Testing\FakeMqttPublisher;

/**
 * @method static void publish(iterable<array-key, string> $topics, string|array<array-key, mixed>|\Illuminate\Contracts\Support\Jsonable|\JsonSerializable $payload)
 * @method static bool isEnabled()
 *
 * @see MqttPublisher
 */
class Mqtt extends Facade
{
    protected static $cached = false;

    /**
     * The fake refuses topics outside the configured prefix, like the real publisher.
     */
    public static function fake(bool $enabled = true): FakeMqttPublisher
    {
        $prefix = config('emqx.mqtt.topic_prefix');

        $fake = new FakeMqttPublisher($enabled, is_string($prefix) && trim($prefix) !== '' ? $prefix : null);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MqttPublisher::class;
    }
}
