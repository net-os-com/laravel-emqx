<?php

declare(strict_types=1);

namespace NetOs\Emqx\Mqtt;

use SensitiveParameter;

final readonly class MqttSettings
{
    /**
     * @param  list<string>  $insecureEnvironments
     * @param  array<string, string>  $environmentTopicPrefixes
     */
    public function __construct(
        public ?string $host,
        public int $port,
        public bool $tls,
        public ?string $username,
        #[SensitiveParameter] private ?string $password,
        public string $clientId,
        public ?string $topicPrefix,
        public int $timeout,
        public array $insecureEnvironments,
        public array $environmentTopicPrefixes,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            host: self::filledString($config['host'] ?? null),
            port: (int) ($config['port'] ?? 8_883),
            tls: (bool) ($config['tls'] ?? true),
            username: self::filledString($config['username'] ?? null),
            password: self::filledString($config['password'] ?? null),
            clientId: self::filledString($config['client_id'] ?? null) ?? 'laravel',
            topicPrefix: self::filledString($config['topic_prefix'] ?? null),
            timeout: (int) ($config['timeout'] ?? 10),
            insecureEnvironments: array_values(array_map('strval', (array) ($config['insecure_environments'] ?? []))),
            environmentTopicPrefixes: array_map('strval', (array) ($config['environment_topic_prefixes'] ?? [])),
        );
    }

    public function password(): ?string
    {
        return $this->password;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'tls' => $this->tls,
            'username' => $this->username,
            'password' => $this->password === null ? null : '[redacted]',
            'clientId' => $this->clientId,
            'topicPrefix' => $this->topicPrefix,
            'timeout' => $this->timeout,
        ];
    }

    private static function filledString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
