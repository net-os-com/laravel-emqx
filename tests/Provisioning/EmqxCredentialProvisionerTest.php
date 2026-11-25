<?php

declare(strict_types=1);

namespace NetOs\Emqx\Tests\Provisioning;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Exceptions\AuthorizationException;
use NetOs\Emqx\Exceptions\ValidationException;
use NetOs\Emqx\Facades\Emqx;

const EMQX_API = 'https://emqx.example.com:8443/api/v5';
const USERS = EMQX_API.'/authentication/password_based%3Abuilt_in_database/users';
const RULES = EMQX_API.'/authorization/sources/built_in_database/rules/users';

beforeEach(function () {
    config([
        'emqx.api.url' => EMQX_API,
        'emqx.api.key' => 'app-id',
        'emqx.api.secret' => 'app-secret',
    ]);

    Http::preventStrayRequests();
    Sleep::fake();
});

function provisioner(): MqttCredentialProvisioner
{
    return app(MqttCredentialProvisioner::class);
}

/**
 * @return list<array{0: string, 1: string, 2: array<array-key, mixed>}>
 */
function sentRequests(): array
{
    return Http::recorded()
        ->map(fn (array $pair): array => [$pair[0]->method(), $pair[0]->url(), $pair[0]->data()])
        ->all();
}

it('updates the password of an existing user', function () {
    Http::fake([USERS.'/abc' => Http::response(['user_id' => 'abc', 'is_superuser' => false])]);

    provisioner()->upsertUser('abc', 'new-password');

    expect(sentRequests())->toBe([
        ['PUT', USERS.'/abc', ['password' => 'new-password']],
    ]);
});

it('creates a user that does not exist yet', function () {
    Http::fake([
        USERS.'/abc' => Http::response(['code' => 'NOT_FOUND', 'message' => 'User not found'], 404),
        USERS => Http::response(['user_id' => 'abc'], 201),
    ]);

    provisioner()->upsertUser('abc', 'new-password');

    expect(sentRequests())->toBe([
        ['PUT', USERS.'/abc', ['password' => 'new-password']],
        ['POST', USERS, ['user_id' => 'abc', 'password' => 'new-password', 'is_superuser' => false]],
    ]);
});

it('encodes the username in the path', function () {
    Http::fake([USERS.'/a%2Fb%20c' => Http::response([])]);

    provisioner()->upsertUser('a/b c', 'new-password');

    Http::assertSent(fn (Request $request): bool => $request->url() === USERS.'/a%2Fb%20c');
});

it('never exposes the password in an error', function () {
    Http::fake([USERS.'/abc' => Http::response([
        'code' => 'BAD_REQUEST',
        'message' => 'invalid password: new-password',
    ], 400)]);

    try {
        provisioner()->upsertUser('abc', 'new-password');
        $this->fail('No exception was thrown.');
    } catch (ValidationException $exception) {
        expect((string) $exception)->not->toContain('new-password')
            ->and($exception->getMessage())->toBe('invalid password: [redacted]');
    }
});

it('replaces the ACL with allow rules followed by a deny on everything', function () {
    Http::fake([RULES.'/abc' => Http::response(null, 204)]);

    provisioner()->replaceAcl('abc', collect(['acc/netlink/v1/abc/#', 'acc/netlink/v1/broadcast/#']));

    expect(sentRequests())->toBe([
        ['PUT', RULES.'/abc', [
            'username' => 'abc',
            'rules' => [
                ['topic' => 'acc/netlink/v1/abc/#', 'permission' => 'allow', 'action' => 'all'],
                ['topic' => 'acc/netlink/v1/broadcast/#', 'permission' => 'allow', 'action' => 'all'],
                ['topic' => '#', 'permission' => 'deny', 'action' => 'all'],
            ],
        ]],
    ]);
});

it('creates the ACL when the user has none yet', function () {
    Http::fake([
        RULES.'/abc' => Http::response(['code' => 'NOT_FOUND'], 404),
        RULES => Http::response(null, 204),
    ]);

    provisioner()->replaceAcl('abc', ['acc/netlink/v1/abc/#']);

    $rules = [
        ['topic' => 'acc/netlink/v1/abc/#', 'permission' => 'allow', 'action' => 'all'],
        ['topic' => '#', 'permission' => 'deny', 'action' => 'all'],
    ];

    expect(sentRequests())->toBe([
        ['PUT', RULES.'/abc', ['username' => 'abc', 'rules' => $rules]],
        ['POST', RULES, [['username' => 'abc', 'rules' => $rules]]],
    ]);
});

it('denies everything when given no topics', function () {
    Http::fake([RULES.'/abc' => Http::response(null, 204)]);

    provisioner()->replaceAcl('abc', []);

    Http::assertSent(fn (Request $request): bool => $request->data()['rules'] === [
        ['topic' => '#', 'permission' => 'deny', 'action' => 'all'],
    ]);
});

it('deletes the ACL and then the user', function () {
    Http::fake([
        RULES.'/abc' => Http::response(null, 204),
        USERS.'/abc' => Http::response(null, 204),
    ]);

    provisioner()->deleteUser('abc');

    expect(sentRequests())->toBe([
        ['DELETE', RULES.'/abc', []],
        ['DELETE', USERS.'/abc', []],
    ]);
});

it('treats a user that is already gone as deleted', function () {
    Http::fake(['*' => Http::response(['code' => 'NOT_FOUND'], 404)]);

    provisioner()->deleteUser('abc');

    Http::assertSentCount(2);
});

it('lets other errors through', function () {
    Http::fake(['*' => Http::response(['code' => 'FORBIDDEN', 'message' => 'Not allowed'], 403)]);

    expect(fn () => provisioner()->deleteUser('abc'))->toThrow(AuthorizationException::class, 'Not allowed');
});

it('is reachable through the facade', function () {
    Http::fake([USERS.'/abc' => Http::response([])]);

    Emqx::upsertUser('abc', 'new-password');

    Http::assertSentCount(1);
});
