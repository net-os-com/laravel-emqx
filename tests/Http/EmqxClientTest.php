<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use NetOs\Emqx\Exceptions\AuthenticationException;
use NetOs\Emqx\Exceptions\AuthorizationException;
use NetOs\Emqx\Exceptions\ConflictException;
use NetOs\Emqx\Exceptions\EmqxException;
use NetOs\Emqx\Exceptions\MissingConfigurationException;
use NetOs\Emqx\Exceptions\RateLimitException;
use NetOs\Emqx\Exceptions\ResourceNotFoundException;
use NetOs\Emqx\Exceptions\ServerException;
use NetOs\Emqx\Exceptions\ValidationException;
use NetOs\Emqx\Http\EmqxClient;

beforeEach(function () {
    config([
        'emqx.api.url' => 'https://emqx.example.com:8443/api/v5/',
        'emqx.api.key' => 'app-id',
        'emqx.api.secret' => 'app-secret',
    ]);

    Http::preventStrayRequests();
    Sleep::fake();
});

it('sends basic auth and JSON to the configured base url', function () {
    Http::fake(['*' => Http::response(['user_id' => 'abc'], 201)]);

    $result = app(EmqxClient::class)->call('POST', '/some/path', ['user_id' => 'abc']);

    expect($result)->toBe(['user_id' => 'abc']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://emqx.example.com:8443/api/v5/some/path'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('app-id:app-secret'))
        && $request->hasHeader('Accept', 'application/json')
        && $request->data() === ['user_id' => 'abc']);
});

it('returns an empty array for an empty response', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    expect(app(EmqxClient::class)->call('DELETE', 'some/path'))->toBe([]);
});

it('requires the API to be configured', function (string $key) {
    config(["emqx.api.{$key}" => null]);

    expect(fn () => app(EmqxClient::class)->call('GET', 'some/path'))
        ->toThrow(MissingConfigurationException::class, 'The EMQX API is not configured');

    Http::assertNothingSent();
})->with(['url', 'key', 'secret']);

it('maps error statuses to exceptions', function (int $status, string $class) {
    config(['emqx.api.retry.times' => 1]);
    Http::fake(['*' => Http::response(['code' => 'SOME_CODE', 'message' => 'Something went wrong'], $status)]);

    try {
        app(EmqxClient::class)->call('GET', 'some/path');
        $this->fail('No exception was thrown.');
    } catch (EmqxException $exception) {
        expect($exception)->toBeInstanceOf($class)
            ->and($exception->getMessage())->toBe('Something went wrong')
            ->and($exception->status)->toBe($status)
            ->and($exception->errorCode)->toBe('SOME_CODE')
            ->and($exception->url)->toBe('https://emqx.example.com:8443/api/v5/some/path');
    }
})->with([
    [400, ValidationException::class],
    [401, AuthenticationException::class],
    [403, AuthorizationException::class],
    [404, ResourceNotFoundException::class],
    [409, ConflictException::class],
    [422, ValidationException::class],
    [418, EmqxException::class],
    [500, ServerException::class],
    [503, ServerException::class],
]);

it('reads the reason when there is no message', function () {
    Http::fake(['*' => Http::response(['code' => 'BAD_REQUEST', 'reason' => 'Bad input'], 400)]);

    expect(fn () => app(EmqxClient::class)->call('GET', 'some/path'))->toThrow(ValidationException::class, 'Bad input');
});

it('falls back to the status when the body has no message', function () {
    Http::fake(['*' => Http::response('', 400)]);

    expect(fn () => app(EmqxClient::class)->call('GET', 'some/path'))
        ->toThrow(ValidationException::class, 'The EMQX API returned HTTP 400.');
});

it('redacts sensitive values and the API secret that EMQX echoes back', function () {
    Http::fake(['*' => Http::response([
        'code' => 'BAD_REQUEST',
        'message' => '{"kind":"validation_error","value":"device-password","auth":"app-secret"}',
    ], 400)]);

    try {
        app(EmqxClient::class)->call('PUT', 'some/path', ['password' => 'device-password'], ['device-password']);
        $this->fail('No exception was thrown.');
    } catch (ValidationException $exception) {
        expect((string) $exception)->not->toContain('device-password')->not->toContain('app-secret')
            ->and($exception->getMessage())->toBe('{"kind":"validation_error","value":"[redacted]","auth":"[redacted]"}');
    }
});

it('retries 429 and server errors, honoring Retry-After', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['code' => 'TOO_MANY_REQUESTS'], 429, ['Retry-After' => '2'])
        ->push(['code' => 'INTERNAL_ERROR'], 500)
        ->push(['ok' => true], 200)]);

    expect(app(EmqxClient::class)->call('GET', 'some/path'))->toBe(['ok' => true]);

    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(2000)->milliseconds(), Sleep::for(2000)->milliseconds()]);
});

it('does not retry client errors', function () {
    Http::fake(['*' => Http::response(['code' => 'BAD_REQUEST'], 400)]);

    expect(fn () => app(EmqxClient::class)->call('GET', 'some/path'))->toThrow(ValidationException::class);

    Http::assertSentCount(1);
});

it('reports the retry delay of a 429 that keeps coming', function () {
    Http::fake(['*' => Http::response(['code' => 'TOO_MANY_REQUESTS', 'message' => 'Slow down'], 429, ['Retry-After' => '7'])]);

    try {
        app(EmqxClient::class)->call('GET', 'some/path');
        $this->fail('No exception was thrown.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter())->toBe(7);
    }

    Http::assertSentCount(3);
});

it('wraps connection failures', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    expect(fn () => app(EmqxClient::class)->call('GET', 'some/path'))
        ->toThrow(EmqxException::class, 'Could not reach the EMQX API at [https://emqx.example.com:8443/api/v5/some/path].');
});

it('hides the secret when dumped', function () {
    expect(print_r(app(EmqxClient::class), true))->not->toContain('app-secret');
});
