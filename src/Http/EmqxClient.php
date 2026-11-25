<?php

declare(strict_types=1);

namespace NetOs\Emqx\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use NetOs\Emqx\Exceptions\AuthenticationException;
use NetOs\Emqx\Exceptions\AuthorizationException;
use NetOs\Emqx\Exceptions\ConflictException;
use NetOs\Emqx\Exceptions\EmqxException;
use NetOs\Emqx\Exceptions\MissingConfigurationException;
use NetOs\Emqx\Exceptions\RateLimitException;
use NetOs\Emqx\Exceptions\ResourceNotFoundException;
use NetOs\Emqx\Exceptions\ServerException;
use NetOs\Emqx\Exceptions\ValidationException;
use SensitiveParameter;
use Throwable;

final readonly class EmqxClient
{
    private const int MAX_RETRY_AFTER_SECONDS = 10;

    public function __construct(
        private Factory $http,
        private ?string $baseUrl,
        private ?string $key,
        #[SensitiveParameter] private ?string $secret,
        private int $timeout = 10,
        private int $retryTimes = 3,
    ) {}

    /**
     * EMQX validation errors can echo the submitted values, so every value in
     * $sensitive is redacted from the error message before it is thrown.
     *
     * @param  array<array-key, mixed>|null  $body
     * @param  list<string>  $sensitive
     * @return array<array-key, mixed>
     */
    public function call(string $method, string $path, ?array $body = null, #[SensitiveParameter] array $sensitive = []): array
    {
        if (blank($this->baseUrl) || blank($this->key) || blank($this->secret)) {
            throw MissingConfigurationException::forApi();
        }

        $url = rtrim((string) $this->baseUrl, '/').'/'.ltrim($path, '/');

        try {
            $method = strtoupper($method);
            $response = $this->request($method)->send($method, $url, $body === null ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw new EmqxException("Could not reach the EMQX API at [{$url}].", url: $url, previous: $exception);
        }

        if ($response->failed()) {
            throw $this->exceptionFor($response, $url, [...$sensitive, (string) $this->secret]);
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'key' => $this->key,
            'secret' => $this->secret === null ? null : '[redacted]',
            'timeout' => $this->timeout,
            'retryTimes' => $this->retryTimes,
        ];
    }

    private function request(string $method): PendingRequest
    {
        $request = $this->http
            ->withBasicAuth((string) $this->key, (string) $this->secret)
            ->acceptJson()
            ->timeout($this->timeout);

        // A retried POST whose first attempt did succeed would fail on the resource it created.
        if ($this->retryTimes > 1 && $method !== 'POST') {
            $request = $request->retry(
                $this->retryTimes,
                $this->retryDelay(...),
                $this->shouldRetry(...),
                throw: false,
            );
        }

        return $request;
    }

    private function retryDelay(int $attempt, Throwable $exception): int
    {
        $retryAfter = $exception instanceof RequestException
            ? $exception->response->header('Retry-After')
            : null;

        if (is_numeric($retryAfter)) {
            return (int) ((float) $retryAfter * 1000);
        }

        return $attempt * 1000;
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return $exception instanceof ConnectionException;
        }

        $status = $exception->response->status();
        $retryAfter = $exception->response->header('Retry-After');

        if (is_numeric($retryAfter) && (float) $retryAfter > self::MAX_RETRY_AFTER_SECONDS) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }

    /**
     * @param  list<string>  $sensitive
     */
    private function exceptionFor(Response $response, string $url, array $sensitive): EmqxException
    {
        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : [];
        $status = $response->status();
        $code = is_string($body['code'] ?? null) ? $body['code'] : null;
        $message = Str::limit($this->redact($this->messageFor($response, $body), $sensitive), 500);

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');

            return new RateLimitException($message, $status, $code, $url, is_numeric($retryAfter) ? (int) $retryAfter : null);
        }

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => AuthorizationException::class,
            $status === 404 => ResourceNotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 400, $status === 422 => ValidationException::class,
            $status >= 500 => ServerException::class,
            default => EmqxException::class,
        };

        return new $class($message, $status, $code, $url);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function messageFor(Response $response, array $body): string
    {
        foreach (['message', 'reason'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return "The EMQX API returned HTTP {$response->status()}.";
    }

    /**
     * @param  list<string>  $sensitive
     */
    private function redact(string $message, array $sensitive): string
    {
        $variants = [];

        foreach ($sensitive as $value) {
            if ($value === '') {
                continue;
            }

            $variants[] = $value;
            $variants[] = substr(json_encode($value, JSON_THROW_ON_ERROR), 1, -1);
            $variants[] = substr(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1);
        }

        $variants = array_unique($variants);
        usort($variants, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $variants === [] ? $message : str_replace($variants, '[redacted]', $message);
    }
}
