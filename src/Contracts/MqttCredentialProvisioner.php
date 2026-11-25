<?php

declare(strict_types=1);

namespace NetOs\Emqx\Contracts;

use SensitiveParameter;

interface MqttCredentialProvisioner
{
    public function upsertUser(string $username, #[SensitiveParameter] string $password): void;

    /**
     * Replace the user's ACL with allow rules on the given topics, followed by a deny on everything else.
     *
     * @param  iterable<array-key, string>  $topics
     */
    public function replaceAcl(string $username, iterable $topics): void;

    /**
     * Delete the user and its ACL. A user that does not exist counts as deleted.
     */
    public function deleteUser(string $username): void;
}
