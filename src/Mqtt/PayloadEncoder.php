<?php

declare(strict_types=1);

namespace NetOs\Emqx\Mqtt;

use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

final readonly class PayloadEncoder
{
    /**
     * @param  string|array<array-key, mixed>|Jsonable|JsonSerializable  $payload
     */
    public function encode(string|array|Jsonable|JsonSerializable $payload): string
    {
        return match (true) {
            is_string($payload) => $payload,
            $payload instanceof Jsonable => $payload->toJson(),
            default => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
