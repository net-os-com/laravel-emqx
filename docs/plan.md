# net-os/laravel-emqx: implementation plan

Status: approved by Rick on 2026-11-25 and built. Tests, lint and phpstan wait for his approval of the functionality.

## Goal

A Laravel package that gives an app two things on top of EMQX:

1. **MQTT publishing**: publish a payload to one or more topics over a single connection, at least once (QoS 1).
   This takes over `App\Services\Mqtt\MqttPublisher` and `config/mqtt.php` from the NetOS platform app.
2. **MQTT credential management** through the EMQX REST API: create or update a built-in database user, replace that
   user's ACL, and delete the user. This is new and takes over the `MqttCredentialProvisioner` that SOFT-3155 builds
   inside the app first.

Both sit behind contracts that the service provider binds, so the app type-hints the contract and swaps in a fake in
its tests. NetOS-specific things stay in the app: message DTOs, topic building, which env vars go to Balena, and when
credentials are provisioned.

## Findings that shape the design

### EMQX Cloud API (verified against the official docs, 2026-11-25)

| | Dedicated v5 / BYOC | Serverless | Self-hosted 5.x (local docker) |
|---|---|---|---|
| Base URL | console's `apiEndpoint`, e.g. `https://xxx:8443/api/v5` (port is only an example) | `{api}`, not documented | `http://emqx:18083/api/v5` |
| Auth | Basic, App ID + App Secret; a key can be limited to some methods/paths (403 outside them) | Basic, App ID + App Secret | Basic, API key + secret |
| User and ACL API | yes, the paths below | **not documented**: only clients, subscriptions and publish. Users and ACLs are managed in the console or by CSV import | yes, same paths |

The endpoints for Dedicated and self-hosted:

- `POST   /authentication/password_based:built_in_database/users` with `{user_id, password}`: 201. The docs list
  400/404 for errors, not 409.
- `PUT    /authentication/password_based:built_in_database/users/{user_id}` with `{password}`: 200, or 404.
- `DELETE /authentication/password_based:built_in_database/users/{user_id}`: 204, or 404.
- `PUT    /authorization/sources/built_in_database/rules/users/{username}` with `{username, rules}`: 204. No 404 is
  documented, so it may also create the rules; this needs checking against a live broker.
- `POST   /authorization/sources/built_in_database/rules/users` with `[{username, rules}]`: 204, or 409 when the user
  already has rules.
- `DELETE /authorization/sources/built_in_database/rules/users/{username}`: 204, or 404.
- A rule is `{topic, permission: allow|deny, action: publish|subscribe|all}`. Rules are evaluated top to bottom and
  the first match wins. Self-hosted allows 100 rules per user by default.
- Errors come back as `{code, message}`.

**Consequence: if acc/prod end up on Serverless, the per-device credential flow cannot run through an API.** This has
to be settled before buying the tier. The package targets the v5 API, which covers Dedicated, BYOC and the local
container. Any tier-specific path or auth goes in one class (`Http\EmqxEndpoints`), so a different tier only changes
that class.

### Current app code

- `MqttPublisher::publish(Collection $topics, BaseData $payload)`: skips when disabled or when there are no topics.
  In the `test` environment it refuses any topic outside `test/netlink/v1`, and it refuses TLS off outside
  `local`/`testing`. It connects with MQTT 3.1.1, uses a random client id suffix, connects within 10s, publishes QoS 1,
  waits for the acks and always disconnects.
- `MqttPublisher::isEnabled()` (static) needs `host` and `topic_prefix` to be set, and in `test` the prefix must be
  `test/netlink/v1`. Five app classes call it statically, from `shouldQueue()` and similar methods.
- `CheckTestEnvironmentCommand` reads `mqtt.*` and `MqttPublisher::TEST_TOPIC_PREFIX`.
- Tests mock `MqttPublisher::class` and set `mqtt.*` config.

## Package layout

Namespace `NetOs\Emqx`. It mirrors `laravel-balena-api`: spatie/laravel-package-tools, `declare(strict_types=1)`,
final readonly classes, one class per file, actions for writes, and exceptions carrying status/code/url.

```
config/emqx.php
src/
  EmqxServiceProvider.php
  Contracts/
    MqttPublisher.php                 publish(iterable<string> $topics, Payload $payload): void; isEnabled(): bool
    MqttCredentialProvisioner.php     upsertUser, replaceAcl, deleteUser
  Facades/
    Mqtt.php                          -> Contracts\MqttPublisher, with Mqtt::fake()
    Emqx.php                          -> Contracts\MqttCredentialProvisioner, with Emqx::fake()
  Mqtt/
    PhpMqttPublisher.php              implements MqttPublisher on php-mqtt/client
    MqttClientFactory.php             builds MqttClient + ConnectionSettings (injected so tests replace it)
    MqttSettings.php                  readonly DTO built from config('emqx.mqtt')
    TopicGuard.php                    the topic prefix and TLS rules, in one place
    PayloadEncoder.php                turns string|array|Jsonable|JsonSerializable into a JSON string
  Http/
    EmqxClient.php                    Laravel HTTP client: base URL, basic auth, timeout, retry, error mapping
    EmqxEndpoints.php                 every API path; the only class that changes per tier/version
  Provisioning/
    EmqxCredentialProvisioner.php     implements MqttCredentialProvisioner by calling the actions below
  Actions/
    Users/UpsertUser.php              PUT; on 404 POST
    Users/DeleteUser.php              DELETE; treats 404 as done
    Acl/ReplaceAcl.php                PUT the rules (POST when PUT 404s on a live broker)
    Acl/DeleteAcl.php                 DELETE; treats 404 as done
  Data/
    AclRule.php                       topic, permission, action; toArray()
  Enums/
    AclPermission.php                 Allow, Deny
    AclAction.php                     Publish, Subscribe, All
  Exceptions/
    EmqxException.php                 base: status, EMQX error code, url; never the request body
    AuthenticationException.php       401
    AuthorizationException.php        403 (also a Cloud key that lacks this path)
    ResourceNotFoundException.php     404
    ValidationException.php           400 / 422
    ConflictException.php             409
    RateLimitException.php            429, with retryAfter()
    ServerException.php               5xx
    MissingConfigurationException.php no API url/key/secret, or no broker host
    MqttPublishException.php          connect/publish/ack failure, wraps the php-mqtt exception (message only, no settings)
    UnsafeTopicException.php          topic outside the configured prefix
    InsecureConnectionException.php   TLS off outside the allowed environments
  Testing/
    FakeMqttPublisher.php             records publishes; assertPublished(topic, ?callback), assertPublishedTimes, assertNothingPublished
    FakeMqttCredentialProvisioner.php records calls; assertUserUpserted(username), assertAclReplaced(username, topics), assertUserDeleted, assertNothingProvisioned
tests/ ...                            Pest + Testbench, see Testing
```

### Contracts

```php
interface MqttPublisher
{
    /** @param iterable<int, string> $topics */
    public function publish(iterable $topics, string|array|Jsonable|JsonSerializable $payload): void;

    public function isEnabled(): bool;
}

interface MqttCredentialProvisioner
{
    public function upsertUser(string $username, #[SensitiveParameter] string $password): void;

    /** @param iterable<int, string> $topics */
    public function replaceAcl(string $username, iterable $topics): void;

    public function deleteUser(string $username): void;
}
```

- `publish` accepts the app's `BaseData` unchanged, because spatie Data implements `Jsonable`. The package does not
  depend on spatie/laravel-data.
- `replaceAcl` writes, in order, `allow all` on each topic and then `deny all` on `#`. That last rule is always added,
  so a device can never fall through to the cluster-wide `no_match` fallback. The method signature matches the app's
  interim interface, so the switch-over is a namespace change.
- `deleteUser` deletes the ACL rules first and then the user. Both steps treat a 404 as already done, so retrying is
  safe. Unlinking (SOFT-3142) will need this.
- Upsert tries `PUT` first, because a registration retry will usually find the user already there, and falls back to
  `POST` on a 404. Detecting an existing user from a POST error is not possible, because the docs do not list a 409 for
  it.

### Configuration (`config/emqx.php`, tag `emqx-config`)

```php
return [
    'api' => [
        'url' => env('EMQX_API_URL'),                    // full apiEndpoint including /api/v5
        'key' => env('EMQX_API_KEY'),
        'secret' => env('EMQX_API_SECRET'),
        'timeout' => (int) env('EMQX_API_TIMEOUT', 10),
        'retry' => ['times' => (int) env('EMQX_API_RETRY_TIMES', 3)],
    ],

    'mqtt' => [
        'host' => env('MQTT_HOST'),
        'port' => (int) env('MQTT_PORT', 8_883),
        'tls' => env('MQTT_TLS', true) !== false,
        'username' => env('MQTT_USERNAME') ?: null,
        'password' => env('MQTT_PASSWORD') ?: null,
        'client_id' => env('MQTT_CLIENT_ID') ?: 'laravel',
        'topic_prefix' => env('MQTT_TOPIC_PREFIX'),
        'timeout' => (int) env('MQTT_TIMEOUT', 10),
        'insecure_environments' => ['local', 'testing'],
        'environment_topic_prefixes' => [],              // e.g. ['test' => 'test/netlink/v1']
    ],
];
```

- The MQTT env names stay the same as in the app today, so no deploy changes them. The new names are `EMQX_API_*`
  and `MQTT_TIMEOUT`.
- `isEnabled()` needs both `host` and `topic_prefix`. When `environment_topic_prefixes` has an entry for the current
  environment, `topic_prefix` must also equal that entry. Today's NetOS rule (`test` → `test/netlink/v1`) therefore
  becomes config in the app's published file, not code.
- `publish()` refuses any topic outside `{topic_prefix}/`, in every environment, not only `test`. This is stricter than
  today, but every NetOS topic already lives under the prefix.
- TLS can only be off in `insecure_environments`.
- The API secret and the MQTT password are read in the service provider and passed in through constructors. They are
  never placed in an exception, a log, `__toString` or a debug dump. Constructor parameters that hold secrets get
  `#[SensitiveParameter]`. `EmqxException` keeps the EMQX `code` and `message` from the response, but never the request
  body (which holds the password) and never the Authorization header.

### HTTP client

Copied from `BalenaClient`'s approach:

- One `send()` method that applies basic auth, `acceptJson`, the timeout, and the retry.
- Retries only on connection errors, 429 and 5xx, honoring `Retry-After` and otherwise backing off linearly.
- `throw: false`, then maps the status to an exception class.
- Usernames go into paths through `rawurlencode`. The `password_based:built_in_database` id is sent with the colon
  encoded as `%3A`, which works for both Cloud and self-hosted.
- Any 2xx counts as success, because a user PUT returns 200 while an ACL PUT returns 204.

### Service provider

`EmqxServiceProvider extends PackageServiceProvider`: `name('emqx')->hasConfigFile()`. It binds:

- Everything is bound per resolve (not as singletons), so config changes in tests apply. The facades do not cache
  their instance for the same reason.
- `MqttSettings`, built from config.
- `MqttPublisher` to `PhpMqttPublisher`.
- `EmqxClient`, built from `emqx.api`. A missing url, key or secret throws
  `MissingConfigurationException` on the first call, not at boot, so an app without EMQX config still boots.
- `MqttCredentialProvisioner` to `EmqxCredentialProvisioner`.

`Mqtt::fake()` and `Emqx::fake()` swap the fakes into the container and return them, the same way `Http::fake()` and
`Queue::fake()` work.

## Dependencies

- `php: ^8.4`
- `illuminate/contracts`, `illuminate/http` and `illuminate/support`: `^12.0||^13.0`
- `spatie/laravel-package-tools: ^1.16`
- `php-mqtt/client: ^2.3`
- Dev dependencies: the same set as laravel-balena-api (pest 4 + arch + laravel plugins, testbench 10/11, larastan 3,
  pint, phpstan extension-installer/deprecation/phpunit).
- CI matrix: PHP 8.4/8.5 × Laravel 12/13.

Repo files are copied from the sibling: `.editorconfig`, `.gitattributes`, `.gitignore`, `phpunit.xml.dist`,
`phpstan.neon.dist` (level 5, `config/` excluded), `.github/workflows` (tests, phpstan, pint, dependabot), `README.md`,
`CHANGELOG.md` and `LICENSE.md`. The one deviation: the sibling's `.gitignore` ignores `/docs`, but this repo keeps
`docs/plan.md` tracked.

## Testing

Tests are written only after Rick approves the functionality.

- **Publisher.** `MqttClientFactory` is replaced with a Mockery double of `MqttClient`. The tests cover:
  - one connection for many topics, QoS 1, waiting for acks, and disconnecting even when a publish fails;
  - nothing happens when disabled or when there are no topics;
  - the prefix guard, the environment prefix rule and the TLS guard;
  - payload encoding.
- **Provisioner.** `Http::fake()` with `Http::preventStrayRequests()`. The tests cover:
  - the exact paths, verbs and bodies, including the `%3A` encoding and the rule order ending in deny `#`;
  - upsert falling back from PUT to POST on 404;
  - delete treating 404 as done;
  - the status → exception mapping and retries on 429/5xx only;
  - **no password or secret anywhere in an exception message, in `body`, or in `(string) $e`**.
- **Fakes.** Their assertions pass and fail as expected.
- **Arch.** No `dd`/`dump`/`ray`, contracts are interfaces, and classes under `Actions` and `Data` are final readonly.
- **Optional, opt-in.** An `@group integration` test against the local docker EMQX (`EMQX_INTEGRATION_URL`) confirms
  the PUT-on-missing-rules behavior. It is skipped by default.

## How the NetOS app switches over

Done in the monorepo by the SOFT-3155 session, not by this one.

1. `composer require net-os/laravel-emqx`. Until the package is on GitHub/Packagist, add a `path` repository to
   `~/Code/laravel-emqx`. Remove `php-mqtt/client` from the app's `composer.json`, because the package pulls it in.
2. Publish the config with `php artisan vendor:publish --tag=emqx-config`. Set
   `'environment_topic_prefixes' => ['test' => 'test/netlink/v1']` and `'client_id' => ... ?: 'netos-platform'`.
   Delete `config/mqtt.php` and the interim `config/emqx.php`.
3. Config keys move from `mqtt.*` to `emqx.mqtt.*`, and from the interim `emqx.api_url/api_key/api_secret` to
   `emqx.api.url/key/secret`. The places that change are the two `Publish*MessageAction`s (`topic_prefix`),
   `CheckTestEnvironmentCommand`, and the tests that set `mqtt.*`.
4. Env: `MQTT_*` stays unchanged. Add `EMQX_API_URL`, `EMQX_API_KEY` and `EMQX_API_SECRET` to `.env.example`
   (local: `http://emqx:18083/api/v5`, with a bootstrap API key in docker-compose) and to the acc/prod secrets once
   the key exists.
5. Code:
   - Delete `App\Services\Mqtt\MqttPublisher`. Inject `NetOs\Emqx\Contracts\MqttPublisher` in both publish actions.
   - Replace the five static `MqttPublisher::isEnabled()` calls with `Mqtt::isEnabled()` or the injected contract.
   - `MqttPublisher::TEST_TOPIC_PREFIX` becomes `config('emqx.mqtt.environment_topic_prefixes.test')`.
   - Delete the app's `MqttCredentialProvisioner` contract, its implementation and its binding. `RegisterDeviceAction`
     injects `NetOs\Emqx\Contracts\MqttCredentialProvisioner`.
6. Tests: `mock(MqttPublisher::class)` becomes `Mqtt::fake()` with assertions, or a mock of the new contract.
   Provisioner tests in the app use `Emqx::fake()`. The `Http::fake()` tests of the EMQX paths move into this package.

What stays in the app: `BookingMessageData`, `BookableStatusMessageData`, the topic strings
`{prefix}/{uuid}/booking/...`, `QueueEnum::MQTT`, and all device registration logic.

## Build order after approval

1. Scaffold: composer.json, the repo files copied from the sibling, the config, and the service provider.
2. Publisher: settings, guard, payload, client factory, `PhpMqttPublisher`, the `Mqtt` facade and its fake.
3. API: endpoints, client, exceptions, actions, provisioner, the `Emqx` facade and its fake.
4. README (installation, config, both contracts, fakes, the Serverless limitation) and the CHANGELOG entry
   `v1.0.0 - unreleased`.
5. After Rick approves the functionality: tests, pint, phpstan.
6. Local commits only when Rick asks. No GitHub repo, no push, no Packagist.

## Decisions

- **Topic guard:** `publish()` refuses topics outside the prefix in every environment (Rick, 2026-11-25).
- **Default `client_id`:** `laravel`, which the app overrides to `netos-platform` (Rick, 2026-11-25).

## Open question

**EMQX Cloud tier.** Acc/prod run on **Serverless** (Europe/AWS, 1,000 sessions, pay as you go). The Serverless API
documents only clients, subscriptions and publish. It documents no user or ACL endpoints, and Serverless supports
neither JWT nor HTTP (external) authentication. Before the API part is built:

1. Create an API key on the Serverless deployment.
2. Probe once for an undocumented v5 user/ACL API, using a throwaway username:
   - `GET {api}/authentication/password_based:built_in_database/users`
   - `GET {api}/authorization/sources/built_in_database/rules/users`
   - Result 2026-11-25: the users probe returned **200** on the Serverless deployment, at
     `https://{deployment}.ala.eu-central-1.emqxsl.com:8443/api/v5`. It listed `platform` and two users named after
     32-hex device uuids. The ACL probe also returned **200**: one rule set, for `platform`. Its rule shape matches the
     v5 docs and includes `qos` and `retain`. Both endpoints work on Serverless without being documented, so the plan
     stays as it is. An integration test against the local v5 broker will catch it if EMQX changes this.
3. If they had returned 404 or 403, the options would have been to upgrade to Dedicated Flex or to drop per-device
   credentials.

Things noticed during the probe (for the app, not the package):

- test, acceptance and production share one deployment (the `platform` rules cover all three prefixes). A device
  user's ACL is therefore limited to its own environment's prefix. But any environment's API key can overwrite or
  delete every user, production included. The test environment should get its own deployment, or no API key at all.
- `platform` is only allowed `{prefix}/+/booking/#`. The bookable status messages go to `{prefix}/{uuid}/resource/bookable`,
  so they get through only if the deployment's `no_match` default is allow.
