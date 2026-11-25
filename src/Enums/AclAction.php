<?php

declare(strict_types=1);

namespace NetOs\Emqx\Enums;

enum AclAction: string
{
    case Publish = 'publish';
    case Subscribe = 'subscribe';
    case All = 'all';
}
