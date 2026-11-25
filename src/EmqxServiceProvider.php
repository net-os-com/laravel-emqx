<?php

declare(strict_types=1);

namespace NetOs\Emqx;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Contracts\MqttPublisher;
use NetOs\Emqx\Http\EmqxClient;
use NetOs\Emqx\Mqtt\MqttSettings;
use NetOs\Emqx\Mqtt\PhpMqttPublisher;
use NetOs\Emqx\Mqtt\TopicGuard;
use NetOs\Emqx\Provisioning\EmqxCredentialProvisioner;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EmqxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('emqx')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(MqttSettings::class, function (Application $app): MqttSettings {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('emqx.mqtt', []);

            return MqttSettings::fromConfig($config);
        });

        $this->app->bind(TopicGuard::class, fn (Application $app): TopicGuard => new TopicGuard(
            $app->make(MqttSettings::class),
            $app->environment(),
        ));

        $this->app->bind(MqttPublisher::class, PhpMqttPublisher::class);

        $this->app->bind(EmqxClient::class, function (Application $app): EmqxClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('emqx.api', []);

            /** @var array{times?: int} $retry */
            $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];

            return new EmqxClient(
                $app->make(Factory::class),
                is_string($config['url'] ?? null) ? $config['url'] : null,
                is_string($config['key'] ?? null) ? $config['key'] : null,
                is_string($config['secret'] ?? null) ? $config['secret'] : null,
                (int) ($config['timeout'] ?? 10),
                (int) ($retry['times'] ?? 3),
            );
        });

        $this->app->bind(MqttCredentialProvisioner::class, EmqxCredentialProvisioner::class);
    }
}
