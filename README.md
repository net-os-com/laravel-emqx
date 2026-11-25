# Laravel EMQX

MQTT publishing and EMQX credential management for Laravel.

- **Publishing**: send a payload to one or more topics over a single connection, at least once (QoS 1), with guards
  against topics outside your prefix and against plain-text connections outside local development.
- **Credentials**: create or update a user in EMQX's built-in database, replace its ACL rules and delete it, through
  the EMQX v5 REST API.

Both sit behind a contract, so your code depends on the interface and your tests swap in a fake.

```php
use NetOs\Emqx\Facades\Emqx;
use NetOs\Emqx\Facades\Mqtt;

Mqtt::publish(["netlink/v1/{$uuid}/booking/created"], ['bookingId' => 42]);

Emqx::upsertUser($uuid, $password);
Emqx::replaceAcl($uuid, ["netlink/v1/{$uuid}/#"]);
Emqx::deleteUser($uuid);
```

## Installation

```bash
composer require net-os/laravel-emqx
```

```bash
php artisan vendor:publish --tag="emqx-config"
```

## Configuration

```dotenv
# REST API (credential management)
EMQX_API_URL=https://xxx.ala.eu-central-1.emqxsl.com:8443/api/v5
EMQX_API_KEY=app-id
EMQX_API_SECRET=app-secret
EMQX_API_TIMEOUT=10
EMQX_API_RETRY_TIMES=3

# Broker (publishing)
MQTT_HOST=xxx.ala.eu-central-1.emqxsl.com
MQTT_PORT=8883
MQTT_TLS=true
MQTT_USERNAME=platform
MQTT_PASSWORD=secret
MQTT_CLIENT_ID=my-app
MQTT_TOPIC_PREFIX=production/netlink/v1
MQTT_TIMEOUT=10
```

`EMQX_API_URL` is the deployment's full API endpoint including `/api/v5`, as shown on the deployment overview in the
EMQX Cloud console. For a local broker it is `http://emqx:18083/api/v5`. The key and secret are the App ID and App
Secret of an API key, sent as basic auth.

Messages are only published when both `MQTT_HOST` and `MQTT_TOPIC_PREFIX` are set. The publisher has two guards:

- **Topic guard.** A topic outside `{MQTT_TOPIC_PREFIX}/` throws an `UnsafeTopicException`, in every environment.
- **TLS guard.** Turning TLS off throws an `InsecureConnectionException`, unless the app environment is listed in
  `emqx.mqtt.insecure_environments` (`local` and `testing` by default).

To pin the prefix per environment, for example when several environments share one broker, list it in the published
config. In a listed environment, publishing is disabled whenever the configured prefix differs:

```php
'environment_topic_prefixes' => ['test' => 'test/netlink/v1'],
```

## Publishing

Type-hint `NetOs\Emqx\Contracts\MqttPublisher` or use the `Mqtt` facade.

```php
public function __construct(private MqttPublisher $mqtt) {}

if ($this->mqtt->isEnabled()) {
    $this->mqtt->publish($topics, $payload);
}
```

- **Topics** are any iterable of strings, so a `Collection` works.
- **The payload** is sent as-is if it is a string. A `Jsonable` (spatie/laravel-data objects, Eloquent models) is
  sent as its `toJson()`. Arrays and `JsonSerializable` objects are JSON encoded.
- **Delivery.** All topics go over one connection. The publisher waits up to `MQTT_TIMEOUT` seconds for the broker's
  acknowledgments and always disconnects. Failures throw an `MqttPublishException`.

## Credentials

Type-hint `NetOs\Emqx\Contracts\MqttCredentialProvisioner` or use the `Emqx` facade.

- **`upsertUser($username, $password)`** sets the password of an existing user, or creates the user when it does not
  exist.
- **`replaceAcl($username, $topics)`** replaces all of the user's rules. Each topic gets an `allow` rule for publish
  and subscribe, and a final `deny` on `#` closes everything else. Because the deny rule is always there, the user
  never falls through to the deployment's default for unmatched topics.
- **`deleteUser($username)`** deletes the user's rules and then the user. A user that does not exist counts as
  deleted, so it is safe to retry.

### Errors

API errors throw a subclass of `NetOs\Emqx\Exceptions\EmqxException`:

| Exception | When |
|---|---|
| `AuthenticationException` | 401: bad API key or secret |
| `AuthorizationException` | 403: the key may not call this endpoint |
| `ResourceNotFoundException` | 404 |
| `ValidationException` | 400 or 422 |
| `ConflictException` | 409 |
| `RateLimitException` | 429, with `retryAfter()` |
| `ServerException` | 5xx |
| `MissingConfigurationException` | `EMQX_API_URL`, `EMQX_API_KEY` or `EMQX_API_SECRET` is missing |

Connection failures, 429 and 5xx are retried up to `EMQX_API_RETRY_TIMES` times, honoring `Retry-After`. An exception
carries the HTTP status, the EMQX error code and the URL. It never carries the request body or the API secret, and any
password that EMQX echoes back in an error message is redacted.

### EMQX Cloud tiers

The client uses the EMQX v5 paths `authentication/password_based:built_in_database/users` and
`authorization/sources/built_in_database/rules/users`.

- **Dedicated, BYOC and self-hosted EMQX 5:** these paths are documented.
- **Serverless:** these paths work, but EMQX does not document them, so EMQX could change them without notice.

All paths live in `NetOs\Emqx\Http\EmqxEndpoints`.

## Testing your app

```php
use NetOs\Emqx\Facades\Emqx;
use NetOs\Emqx\Facades\Mqtt;

$mqtt = Mqtt::fake();
// ...
$mqtt->assertPublished('netlink/v1/abc/booking/created', fn (mixed $payload) => $payload->bookingId === 42);
$mqtt->assertPublishedTimes('netlink/v1/abc/booking/created', 1);
$mqtt->assertNothingPublished();

Mqtt::fake(enabled: false); // isEnabled() returns false

$emqx = Emqx::fake();
// ...
$emqx->assertUserUpserted('abc');
$emqx->assertAclReplaced('abc', ['netlink/v1/abc/#']);
$emqx->passwordFor('abc'); // compare with the password the app handed to the device
$emqx->assertUserDeleted('abc');
```

Both fakes replace the contract in the container, so injected dependencies receive the fake too.

## License

The MIT License (MIT). See [License File](LICENSE.md) for more information.
