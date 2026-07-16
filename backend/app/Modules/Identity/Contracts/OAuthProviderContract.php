<?php

namespace App\Modules\Identity\Contracts;

/**
 * The capability contract every federated-identity OAuth Provider
 * implements (docs/adr/0019-integration-platform-architecture.md
 * Decision 1). Federation itself is Access Governance's concern
 * (ADR-0019 Decision 2), not Connectivity's -- this contract only
 * covers the Provider Registry's piece of it: exchanging vendor OAuth
 * credentials for a token, the same generic vendor-relationship shape
 * every other Provider has. What the platform does with the resulting
 * identity is Access Governance's own, separate concern, untouched here.
 */
interface OAuthProviderContract
{
    /**
     * @param  string[]  $scopes
     */
    public function getAuthorizationUrl(string $redirectUri, array $scopes): string;

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in?: int}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;
}
