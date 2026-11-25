<?php

declare(strict_types=1);

namespace NetOs\Emqx\Data;

use NetOs\Emqx\Enums\AclAction;
use NetOs\Emqx\Enums\AclPermission;

final readonly class AclRule
{
    public function __construct(
        public string $topic,
        public AclPermission $permission,
        public AclAction $action = AclAction::All,
    ) {}

    public static function allow(string $topic, AclAction $action = AclAction::All): self
    {
        return new self($topic, AclPermission::Allow, $action);
    }

    public static function deny(string $topic, AclAction $action = AclAction::All): self
    {
        return new self($topic, AclPermission::Deny, $action);
    }

    /**
     * @return array{topic: string, permission: string, action: string}
     */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'permission' => $this->permission->value,
            'action' => $this->action->value,
        ];
    }
}
