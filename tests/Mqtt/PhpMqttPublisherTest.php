<?php

declare(strict_types=1);

use Mockery\MockInterface;
use NetOs\Emqx\Contracts\MqttPublisher;
use NetOs\Emqx\Exceptions\InsecureConnectionException;
use NetOs\Emqx\Exceptions\MqttPublishException;
use NetOs\Emqx\Exceptions\UnsafeTopicException;
use NetOs\Emqx\Mqtt\MqttClientFactory;
use NetOs\Emqx\Mqtt\MqttSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;

beforeEach(function () {
    config([
        'emqx.mqtt.host' => 'broker.example.com',
        'emqx.mqtt.topic_prefix' => 'acc/netlink/v1',
        'emqx.mqtt.username' => 'platform',
        'emqx.mqtt.password' => 'broker-secret',
    ]);
});

function mqttClient(): MqttClient&MockInterface
{
    $client = Mockery::mock(MqttClient::class);
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->allows('connect')->andReturn($client);
    app()->instance(MqttClientFactory::class, $factory);

    return $client;
}

function publisher(): MqttPublisher
{
    return app(MqttPublisher::class);
}

it('publishes every topic at QoS 1 over one connection and waits for the acknowledgments', function () {
    $client = mqttClient();
    $client->expects('publish')->with('acc/netlink/v1/a/booking/created', '{"bookingId":1}', 1)->once();
    $client->expects('publish')->with('acc/netlink/v1/b/booking/created', '{"bookingId":1}', 1)->once();
    $client->expects('loop')->with(true, true, 10)->once();
    $client->expects('disconnect')->once();

    publisher()->publish(collect(['acc/netlink/v1/a/booking/created', 'acc/netlink/v1/b/booking/created']), ['bookingId' => 1]);
});

it('connects once for all topics', function () {
    $client = Mockery::mock(MqttClient::class)->shouldIgnoreMissing();
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->expects('connect')
        ->once()
        ->withArgs(fn (MqttSettings $settings): bool => $settings->host === 'broker.example.com' && $settings->password() === 'broker-secret')
        ->andReturn($client);
    app()->instance(MqttClientFactory::class, $factory);

    publisher()->publish(['acc/netlink/v1/a/x', 'acc/netlink/v1/b/x', 'acc/netlink/v1/c/x'], 'hello');
});

it('disconnects when publishing fails', function () {
    $client = mqttClient();
    $client->allows('publish')->andThrow(new DataTransferException(DataTransferException::EXCEPTION_TX_DATA, 'broken pipe'));
    $client->expects('disconnect')->once();

    expect(fn () => publisher()->publish(['acc/netlink/v1/a/x'], 'hello'))
        ->toThrow(MqttPublishException::class, 'Could not publish to the MQTT broker at [broker.example.com:8883].');
});

it('wraps a failed connection without exposing the password', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->allows('connect')->andThrow(new ConnectingToBrokerFailedException(1, 'refused'));
    app()->instance(MqttClientFactory::class, $factory);

    try {
        publisher()->publish(['acc/netlink/v1/a/x'], 'hello');
        $this->fail('No exception was thrown.');
    } catch (MqttPublishException $exception) {
        expect($exception->getMessage())->toBe('Could not connect to the MQTT broker at [broker.example.com:8883].')
            ->and((string) $exception)->not->toContain('broker-secret');
    }
});

it('does nothing without topics', function () {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->expects('connect')->never();
    app()->instance(MqttClientFactory::class, $factory);

    publisher()->publish([], 'hello');
});

it('is disabled without a host or a topic prefix', function (string $key) {
    config(["emqx.mqtt.{$key}" => null]);
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->expects('connect')->never();
    app()->instance(MqttClientFactory::class, $factory);

    expect(publisher()->isEnabled())->toBeFalse();

    publisher()->publish(['acc/netlink/v1/a/x'], 'hello');
})->with(['host', 'topic_prefix']);

it('is disabled in an environment whose pinned prefix differs', function () {
    config(['emqx.mqtt.environment_topic_prefixes' => ['testing' => 'test/netlink/v1']]);

    expect(publisher()->isEnabled())->toBeFalse();

    config(['emqx.mqtt.topic_prefix' => 'test/netlink/v1']);

    expect(publisher()->isEnabled())->toBeTrue();
});

it('ignores pinned prefixes of other environments', function () {
    config(['emqx.mqtt.environment_topic_prefixes' => ['test' => 'test/netlink/v1']]);

    expect(publisher()->isEnabled())->toBeTrue();
});

it('refuses a topic outside the prefix', function (string $topic) {
    $factory = Mockery::mock(MqttClientFactory::class);
    $factory->expects('connect')->never();
    app()->instance(MqttClientFactory::class, $factory);

    expect(fn () => publisher()->publish(['acc/netlink/v1/a/x', $topic], 'hello'))
        ->toThrow(UnsafeTopicException::class, "The MQTT topic [{$topic}] is outside the configured prefix [acc/netlink/v1/].");
})->with([
    'another environment' => 'production/netlink/v1/a/x',
    'the prefix itself' => 'acc/netlink/v1',
    'a longer prefix' => 'acc/netlink/v1x/a',
]);

it('refuses TLS off outside the insecure environments', function () {
    config(['emqx.mqtt.tls' => false]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => publisher()->publish(['acc/netlink/v1/a/x'], 'hello'))
        ->toThrow(InsecureConnectionException::class, 'MQTT TLS can not be turned off in the [production] environment.');
});

it('allows TLS off in an insecure environment', function () {
    config(['emqx.mqtt.tls' => false]);
    $client = mqttClient();
    $client->expects('publish')->once();
    $client->allows(['loop' => null, 'disconnect' => null]);

    publisher()->publish(['acc/netlink/v1/a/x'], 'hello');
});

it('hides the password when dumped', function () {
    $settings = app(MqttSettings::class);

    expect(print_r($settings, true))->not->toContain('broker-secret')
        ->and($settings->password())->toBe('broker-secret');
});
