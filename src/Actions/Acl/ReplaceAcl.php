<?php

declare(strict_types=1);

namespace NetOs\Emqx\Actions\Acl;

use NetOs\Emqx\Data\AclRule;
use NetOs\Emqx\Exceptions\ResourceNotFoundException;
use NetOs\Emqx\Http\EmqxClient;
use NetOs\Emqx\Http\EmqxEndpoints;

/**
 * Replace all ACL rules of a user. EMQX evaluates them in the given order.
 *
 * Creating comes after a failed replace, because EMQX does not document
 * whether a replace creates the rules of a user that has none yet.
 */
final readonly class ReplaceAcl
{
    public function __construct(
        private EmqxClient $client,
        private EmqxEndpoints $endpoints,
    ) {}

    /**
     * @param  list<AclRule>  $rules
     */
    public function __invoke(string $username, array $rules): void
    {
        $entry = [
            'username' => $username,
            'rules' => array_map(fn (AclRule $rule): array => $rule->toArray(), $rules),
        ];

        try {
            $this->client->call('PUT', $this->endpoints->aclRulesFor($username), $entry);
        } catch (ResourceNotFoundException) {
            $this->client->call('POST', $this->endpoints->aclRules(), [$entry]);
        }
    }
}
