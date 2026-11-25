<?php

declare(strict_types=1);

namespace NetOs\Emqx\Actions\Acl;

use NetOs\Emqx\Exceptions\ResourceNotFoundException;
use NetOs\Emqx\Http\EmqxClient;
use NetOs\Emqx\Http\EmqxEndpoints;

final readonly class DeleteAcl
{
    public function __construct(
        private EmqxClient $client,
        private EmqxEndpoints $endpoints,
    ) {}

    public function __invoke(string $username): void
    {
        try {
            $this->client->call('DELETE', $this->endpoints->aclRulesFor($username));
        } catch (ResourceNotFoundException) {
            return;
        }
    }
}
