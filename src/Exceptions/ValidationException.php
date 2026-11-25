<?php

declare(strict_types=1);

namespace NetOs\Emqx\Exceptions;

/**
 * EMQX rejected the request (400 or 422).
 */
class ValidationException extends EmqxException {}
