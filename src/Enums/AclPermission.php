<?php

declare(strict_types=1);

namespace NetOs\Emqx\Enums;

enum AclPermission: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
