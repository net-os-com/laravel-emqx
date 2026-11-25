<?php

declare(strict_types=1);

namespace NetOs\Emqx\Actions\Users;

use NetOs\Emqx\Exceptions\ResourceNotFoundException;
use NetOs\Emqx\Http\EmqxClient;
use NetOs\Emqx\Http\EmqxEndpoints;
use SensitiveParameter;

/**
 * Create a built-in database user, or set its password when it exists.
 *
 * Updating comes first because a re-run usually finds the user already
 * there, and EMQX does not document a distinct error for creating a user
 * that exists.
 */
final readonly class UpsertUser
{
    public function __construct(
        private EmqxClient $client,
        private EmqxEndpoints $endpoints,
    ) {}

    public function __invoke(string $username, #[SensitiveParameter] string $password): void
    {
        try {
            $this->client->call('PUT', $this->endpoints->user($username), ['password' => $password], [$password]);
        } catch (ResourceNotFoundException) {
            $this->client->call('POST', $this->endpoints->users(), [
                'user_id' => $username,
                'password' => $password,
                'is_superuser' => false,
            ], [$password]);
        }
    }
}
