<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('NetOs\Emqx\Contracts')
    ->toBeInterfaces();

arch('actions and data are final and readonly')
    ->expect(['NetOs\Emqx\Actions', 'NetOs\Emqx\Data'])
    ->toBeFinal()
    ->toBeReadonly();

arch('every file declares strict types')
    ->expect('NetOs\Emqx')
    ->toUseStrictTypes();

arch('API exceptions extend the base exception')
    ->expect([
        'NetOs\Emqx\Exceptions\AuthenticationException',
        'NetOs\Emqx\Exceptions\AuthorizationException',
        'NetOs\Emqx\Exceptions\ConflictException',
        'NetOs\Emqx\Exceptions\MissingConfigurationException',
        'NetOs\Emqx\Exceptions\RateLimitException',
        'NetOs\Emqx\Exceptions\ResourceNotFoundException',
        'NetOs\Emqx\Exceptions\ServerException',
        'NetOs\Emqx\Exceptions\ValidationException',
    ])
    ->toExtend('NetOs\Emqx\Exceptions\EmqxException');
