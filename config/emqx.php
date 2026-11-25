<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EMQX REST API
    |--------------------------------------------------------------------------
    |
    | Used to manage MQTT credentials: built-in database users and their ACL
    | rules. The url is the deployment's full API endpoint including /api/v5,
    | e.g. https://xxx.ala.eu-central-1.emqxsl.com:8443/api/v5 on EMQX Cloud
    | or http://emqx:18083/api/v5 for a local broker. The key and secret are
    | the App ID and App Secret of an API key, sent as basic auth.
    |
    | Retries apply only to 429 and 5xx responses and connection failures.
    |
    */

    'api' => [
        'url' => env('EMQX_API_URL'),
        'key' => env('EMQX_API_KEY'),
        'secret' => env('EMQX_API_SECRET'),
        'timeout' => (int) env('EMQX_API_TIMEOUT', 10),
        'retry' => [
            'times' => (int) env('EMQX_API_RETRY_TIMES', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MQTT publishing
    |--------------------------------------------------------------------------
    |
    | Messages are only published when both the host and the topic prefix are
    | set, and every topic must live under the prefix. TLS can only be turned
    | off in the insecure environments, e.g. for a local broker container.
    |
    | environment_topic_prefixes pins the prefix per environment: publishing
    | is disabled in a listed environment whose prefix differs, so a shared
    | broker never receives another environment's messages.
    |
    */

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
        'environment_topic_prefixes' => [],
    ],

];
