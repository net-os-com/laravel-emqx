<?php

declare(strict_types=1);

use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Contracts\MqttPublisher;
use NetOs\Emqx\Exceptions\UnsafeTopicException;
use NetOs\Emqx\Facades\Emqx;
use NetOs\Emqx\Facades\Mqtt;
use PHPUnit\Framework\AssertionFailedError;

it('swaps the publisher for a fake that records messages', function () {
    $fake = Mqtt::fake();

    app(MqttPublisher::class)->publish(collect(['a/1', 'a/2']), ['bookingId' => 1]);
    Mqtt::publish(['a/1'], 'hello');

    expect(app(MqttPublisher::class))->toBe($fake)
        ->and(Mqtt::isEnabled())->toBeTrue();

    $fake->assertPublished('a/1');
    $fake->assertPublished('a/2', fn (mixed $payload, string $topic): bool => $payload === ['bookingId' => 1] && $topic === 'a/2');
    $fake->assertPublishedTimes('a/1', 2);
    $fake->assertNotPublished('a/3');

    expect($fake->published('a/1'))->toHaveCount(2)
        ->and(fn () => $fake->assertPublished('a/2', fn (mixed $payload): bool => $payload === 'hello'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingPublished())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNotPublished('a/1'))->toThrow(AssertionFailedError::class);
});

it('fakes a disabled publisher', function () {
    $fake = Mqtt::fake(enabled: false);

    Mqtt::publish(['a/1'], 'hello');

    expect(Mqtt::isEnabled())->toBeFalse();
    $fake->assertNothingPublished();
});

it('swaps the provisioner for a fake that records credentials', function () {
    $fake = Emqx::fake();

    expect(app(MqttCredentialProvisioner::class))->toBe($fake);
    $fake->assertNothingProvisioned();

    Emqx::upsertUser('abc', 'secret');
    Emqx::replaceAcl('abc', collect(['a/abc/#']));

    $fake->assertUserUpserted('abc');
    $fake->assertAclReplaced('abc');
    $fake->assertAclReplaced('abc', ['a/abc/#']);

    expect($fake->passwordFor('abc'))->toBe('secret')
        ->and($fake->aclFor('abc'))->toBe(['a/abc/#'])
        ->and(fn () => $fake->assertAclReplaced('abc', ['other/#']))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertUserUpserted('xyz'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertUserDeleted('abc'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingProvisioned())->toThrow(AssertionFailedError::class);

    Emqx::deleteUser('abc');

    $fake->assertUserDeleted('abc');

    expect($fake->passwordFor('abc'))->toBeNull()
        ->and($fake->aclFor('abc'))->toBeNull();
});

it('refuses topics outside the configured prefix, like the real publisher', function () {
    config(['emqx.mqtt.topic_prefix' => 'acc/netlink/v1']);
    $fake = Mqtt::fake();

    Mqtt::publish(['acc/netlink/v1/a/x'], 'hello');

    expect(fn () => Mqtt::publish(['production/netlink/v1/a/x'], 'hello'))
        ->toThrow(UnsafeTopicException::class, 'The MQTT topic [production/netlink/v1/a/x] is outside the configured prefix [acc/netlink/v1/].');

    $fake->assertPublishedTimes('acc/netlink/v1/a/x', 1);
    $fake->assertNotPublished('production/netlink/v1/a/x');
});
