<?php

declare(strict_types=1);

namespace NetOs\Emqx\Mqtt;

use NetOs\Emqx\Exceptions\InsecureConnectionException;
use NetOs\Emqx\Exceptions\UnsafeTopicException;

final readonly class TopicGuard
{
    public function __construct(
        private MqttSettings $settings,
        private string $environment,
    ) {}

    public function allowsPublishing(): bool
    {
        if ($this->settings->host === null || $this->settings->topicPrefix === null) {
            return false;
        }

        $pinned = $this->settings->environmentTopicPrefixes[$this->environment] ?? null;

        return $pinned === null || rtrim($pinned, '/') === rtrim($this->settings->topicPrefix, '/');
    }

    /**
     * @param  list<string>  $topics
     */
    public function assertWithinPrefix(array $topics): void
    {
        $prefix = rtrim((string) $this->settings->topicPrefix, '/').'/';

        foreach ($topics as $topic) {
            if (! str_starts_with($topic, $prefix)) {
                throw UnsafeTopicException::outsidePrefix($topic, $prefix);
            }
        }
    }

    public function assertTlsAllowed(): void
    {
        if (! $this->settings->tls && ! in_array($this->environment, $this->settings->insecureEnvironments, true)) {
            throw InsecureConnectionException::inEnvironment($this->environment);
        }
    }
}
