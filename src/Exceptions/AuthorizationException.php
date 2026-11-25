<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

/**
 * The API key is not allowed to call this endpoint (403).
 */
class AuthorizationException extends EmqxException {}
