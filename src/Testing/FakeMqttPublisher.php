<?php

declare(strict_types=1);

namespace NetOs\Emqx\Testing;

use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use NetOs\Emqx\Contracts\MqttPublisher;
use PHPUnit\Framework\Assert;

final class FakeMqttPublisher implements MqttPublisher
{
    /** @var list<array{topic: string, payload: string|array<array-key, mixed>|Jsonable|JsonSerializable}> */
    private array $published = [];

    public function __construct(private bool $enabled = true) {}

    public function publish(iterable $topics, string|array|Jsonable|JsonSerializable $payload): void
    {
        if (! $this->enabled) {
            return;
        }

        foreach ($topics as $topic) {
            $this->published[] = ['topic' => $topic, 'payload' => $payload];
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * @return list<array{topic: string, payload: string|array<array-key, mixed>|Jsonable|JsonSerializable}>
     */
    public function published(?string $topic = null): array
    {
        return array_values(array_filter(
            $this->published,
            fn (array $message): bool => $topic === null || $message['topic'] === $topic,
        ));
    }

    /**
     * @param  (callable(mixed, string): bool)|null  $callback  receives the payload and the topic
     */
    public function assertPublished(string $topic, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->published($topic),
            fn (array $message): bool => $callback === null || $callback($message['payload'], $message['topic']),
        );

        Assert::assertNotEmpty($matching, "No matching MQTT message was published on [{$topic}].");
    }

    public function assertPublishedTimes(string $topic, int $times): void
    {
        $count = count($this->published($topic));

        Assert::assertSame($times, $count, "Expected {$times} MQTT messages on [{$topic}], got {$count}.");
    }

    public function assertNotPublished(string $topic): void
    {
        Assert::assertEmpty($this->published($topic), "An MQTT message was published on [{$topic}].");
    }

    public function assertNothingPublished(): void
    {
        Assert::assertEmpty($this->published, 'MQTT messages were published.');
    }
}
