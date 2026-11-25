<?php

declare(strict_types=1);

namespace NetOs\Emqx\Contracts;

use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

interface MqttPublisher
{
    /**
     * Publish the payload on every topic over a single connection, at least once (QoS 1).
     *
     * @param  iterable<array-key, string>  $topics
     * @param  string|array<array-key, mixed>|Jsonable|JsonSerializable  $payload
     */
    public function publish(iterable $topics, string|array|Jsonable|JsonSerializable $payload): void;

    public function isEnabled(): bool;
}
