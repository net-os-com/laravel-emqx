# Changelog

All notable changes to `laravel-emqx` will be documented in this file.

## v1.0.0 - 2026-11-25

First release.

### Added

- `MqttPublisher` contract with a php-mqtt implementation. It publishes to many topics over one connection at QoS 1 and waits for the broker's acknowledgments. It throws an `MqttPublishException` when connecting or publishing fails, or when a message is not acknowledged in time. It refuses topics outside the configured prefix, refuses TLS off outside the insecure environments, and supports a pinned topic prefix per environment.
- `MqttCredentialProvisioner` contract on the EMQX v5 REST API. `upsertUser` creates or updates a user, `replaceAcl` writes allow rules followed by a deny on `#`, and `deleteUser` removes the user and its ACL. It works on EMQX Cloud Serverless, Dedicated and BYOC, and on self-hosted EMQX 5.
- Basic auth, timeout and retries on connection failures, 429 and 5xx. `Retry-After` is honored up to 10 seconds, and a `POST` is never retried. Errors map to typed exceptions that carry the status, the EMQX error code and the URL, never the request body or a secret.
- `Mqtt` and `Emqx` facades, each with a `fake()` that records calls and offers assertions. The publisher fake refuses topics outside the configured prefix, like the real publisher.
