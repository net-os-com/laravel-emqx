<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

/**
 * The EMQX resource already exists or a limit was reached (409).
 */
class ConflictException extends EmqxException {}
