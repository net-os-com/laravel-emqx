<?php

declare(strict_types=1);

namespace NetOs\Emqx\Provisioning;

use NetOs\Emqx\Actions\Acl\DeleteAcl;
use NetOs\Emqx\Actions\Acl\ReplaceAcl;
use NetOs\Emqx\Actions\Users\DeleteUser;
use NetOs\Emqx\Actions\Users\UpsertUser;
use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use NetOs\Emqx\Data\AclRule;
use SensitiveParameter;

final readonly class EmqxCredentialProvisioner implements MqttCredentialProvisioner
{
    public function __construct(
        private UpsertUser $upsertUser,
        private DeleteUser $deleteUser,
        private ReplaceAcl $replaceAcl,
        private DeleteAcl $deleteAcl,
    ) {}

    public function upsertUser(string $username, #[SensitiveParameter] string $password): void
    {
        ($this->upsertUser)($username, $password);
    }

    public function replaceAcl(string $username, iterable $topics): void
    {
        $rules = [];

        foreach ($topics as $topic) {
            $rules[] = AclRule::allow($topic);
        }

        $rules[] = AclRule::deny('#');

        ($this->replaceAcl)($username, $rules);
    }

    public function deleteUser(string $username): void
    {
        ($this->deleteAcl)($username);
        ($this->deleteUser)($username);
    }
}
