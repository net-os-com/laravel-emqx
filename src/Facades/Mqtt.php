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

    public static function fake(bool $enabled = true): FakeMqttPublisher
    {
        $fake = new FakeMqttPublisher($enabled);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MqttPublisher::class;
    }
}
