<?php

declare(strict_types=1);

namespace NetOs\Emqx\Http;

/**
 * Every EMQX API path, relative to the configured /api/v5 base URL.
 *
 * EMQX Cloud Serverless serves these paths without documenting them, so a
 * change of tier or API version only has to change this class.
 */
final readonly class EmqxEndpoints
{
    private const string AUTHENTICATOR = 'password_based%3Abuilt_in_database';

    public function users(): string
    {
        return 'authentication/'.self::AUTHENTICATOR.'/users';
    }

    public function user(string $username): string
    {
        return $this->users().'/'.rawurlencode($username);
    }

    public function aclRules(): string
    {
        return 'authorization/sources/built_in_database/rules/users';
    }

    public function aclRulesFor(string $username): string
    {
        return $this->aclRules().'/'.rawurlencode($username);
    }
}
