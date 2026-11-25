<?php

declare(strict_types=1);

namespace NetOs\Emqx\Testing;

use NetOs\Emqx\Contracts\MqttCredentialProvisioner;
use PHPUnit\Framework\Assert;
use SensitiveParameter;

final class FakeMqttCredentialProvisioner implements MqttCredentialProvisioner
{
    /** @var array<string, string> */
    private array $passwords = [];

    /** @var array<string, list<string>> */
    private array $acls = [];

    /** @var list<string> */
    private array $deleted = [];

    public function upsertUser(string $username, #[SensitiveParameter] string $password): void
    {
        $this->passwords[$username] = $password;
    }

    public function replaceAcl(string $username, iterable $topics): void
    {
        $this->acls[$username] = is_array($topics) ? array_values($topics) : iterator_to_array($topics, false);
    }

    public function deleteUser(string $username): void
    {
        unset($this->passwords[$username], $this->acls[$username]);

        $this->deleted[] = $username;
    }

    /**
     * The password a user was last given, to compare with what the app handed out elsewhere.
     */
    public function passwordFor(string $username): ?string
    {
        return $this->passwords[$username] ?? null;
    }

    /**
     * @return list<string>|null
     */
    public function aclFor(string $username): ?array
    {
        return $this->acls[$username] ?? null;
    }

    public function assertUserUpserted(string $username): void
    {
        Assert::assertArrayHasKey($username, $this->passwords, "The MQTT user [{$username}] was not upserted.");
    }

    /**
     * @param  list<string>|null  $topics
     */
    public function assertAclReplaced(string $username, ?array $topics = null): void
    {
        Assert::assertArrayHasKey($username, $this->acls, "The ACL of MQTT user [{$username}] was not replaced.");

        if ($topics !== null) {
            Assert::assertSame($topics, $this->acls[$username], "The ACL of MQTT user [{$username}] has other topics.");
        }
    }

    public function assertUserDeleted(string $username): void
    {
        Assert::assertContains($username, $this->deleted, "The MQTT user [{$username}] was not deleted.");
    }

    public function assertNothingProvisioned(): void
    {
        Assert::assertSame([[], [], []], [$this->passwords, $this->acls, $this->deleted], 'MQTT credentials were provisioned.');
    }
}
