<?php

declare(strict_types=1);

namespace NetOs\Emqx\Mqtt;

use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use NetOs\Emqx\Contracts\MqttPublisher;
use NetOs\Emqx\Exceptions\MqttPublishException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

final readonly class PhpMqttPublisher implements MqttPublisher
{
    public function __construct(
        private MqttSettings $settings,
        private TopicGuard $guard,
        private MqttClientFactory $clients,
        private PayloadEncoder $encoder,
    ) {}

    public function publish(iterable $topics, string|array|Jsonable|JsonSerializable $payload): void
    {
        $topics = is_array($topics) ? array_values($topics) : iterator_to_array($topics, false);

        if (! $this->isEnabled() || $topics === []) {
            return;
        }

        $this->guard->assertWithinPrefix($topics);
        $this->guard->assertTlsAllowed();

        $message = $this->encoder->encode($payload);

        try {
            $client = $this->clients->connect($this->settings);
        } catch (MqttClientException $exception) {
            throw MqttPublishException::couldNotConnect($this->settings, $exception);
        }

        try {
            foreach ($topics as $topic) {
                $client->publish($topic, $message, MqttClient::QOS_AT_LEAST_ONCE);
            }

            $client->loop(true, true, $this->settings->timeout);
        } catch (MqttClientException $exception) {
            throw MqttPublishException::couldNotPublish($this->settings, $exception);
        } finally {
            $client->disconnect();
        }
    }

    public function isEnabled(): bool
    {
        return $this->guard->allowsPublishing();
    }
}
