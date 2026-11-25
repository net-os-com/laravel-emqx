# Changelog

All notable changes to `laravel-emqx` will be documented in this file.

## v1.0.0 - unreleased

### Added

- `MqttPublisher` contract with a php-mqtt implementation. It publishes to many topics over one connection at QoS 1,
  waits for the acknowledgments, refuses topics outside the configured prefix, and refuses TLS off outside the
  insecure environments. It supports a pinned topic prefix per environment.
- `MqttCredentialProvisioner` contract on the EMQX v5 REST API, with `upsertUser`, `replaceAcl` (allow rules followed
  by a deny on `#`) and `deleteUser`.
- `Mqtt` and `Emqx` facades, each with a `fake()` that records calls and offers assertions.
