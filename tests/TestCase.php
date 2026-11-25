<?php

declare(strict_types=1);

namespace NetOs\Emqx\Tests;

use NetOs\Emqx\EmqxServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EmqxServiceProvider::class,
        ];
    }
}
