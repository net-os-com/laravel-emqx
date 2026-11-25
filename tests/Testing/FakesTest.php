<?php

declare(strict_types=1);

use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Contracts\MqttPublisher;
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
