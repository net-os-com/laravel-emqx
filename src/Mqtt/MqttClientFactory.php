<?php

declare(strict_types=1);

namespace NetOs\Emqx\Mqtt;

use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

class MqttClientFactory
{
    /**
     * @throws MqttClientException
     */
    public function connect(MqttSettings $settings): MqttClientContract
    {
        // MQTT 3.1.1, because 3.1 limits the client id to 23 characters.
        $client = new MqttClient(
            (string) $settings->host,
            $settings->port,
            $settings->clientId.'-'.Str::random(8),
            MqttClient::MQTT_3_1_1,
        );

        $client->connect((new ConnectionSettings)
            ->setUsername($settings->username)
            ->setPassword($settings->password())
            ->setUseTls($settings->tls)
            ->setTlsVerifyPeer(true)
            ->setConnectTimeout($settings->timeout), true);

        return $client;
    }
}
