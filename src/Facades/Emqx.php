<?php

declare(strict_types=1);

namespace NetOs\Emqx\Facades;

use Illuminate\Support\Facades\Facade;
use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Testing\FakeMqttCredentialProvisioner;

/**
 * @method static void upsertUser(string $username, string $password)
 * @method static void replaceAcl(string $username, iterable<array-key, string> $topics)
 * @method static void deleteUser(string $username)
 *
 * @see MqttCredentialProvisioner
 */
class Emqx extends Facade
{
    protected static $cached = false;

    public static function fake(): FakeMqttCredentialProvisioner
    {
        $fake = new FakeMqttCredentialProvisioner;

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MqttCredentialProvisioner::class;
    }
}
