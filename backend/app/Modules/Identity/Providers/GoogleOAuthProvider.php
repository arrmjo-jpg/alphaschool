<?php

namespace App\Modules\Identity\Providers;

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\Contracts\TestsCredentials;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Identity\Contracts\OAuthProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Proof provider #2 of 3: credential shape is a client_id/client_secret
 * pair -- deliberately unlike SmtpEmailProvider's five-field quintet or
 * FirebasePushProvider's service-account shape, proving the Vault and
 * Registry impose no assumed shape.
 */
class GoogleOAuthProvider implements DeclaresProviderSlots, HealthCheckable, OAuthProviderContract, TestsCredentials
{
    private const SLOT_KEY = 'identity.federation.google-oauth';

    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly ProviderCredentialVault $vault,
    ) {}

    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: self::SLOT_KEY,
                capabilityContract: OAuthProviderContract::class,
                credentialFields: [
                    new ProviderCredentialFieldDefinition('client_id', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('client_secret', ProviderCredentialFieldDefinition::TYPE_SECRET),
                ],
                owningModule: 'Identity',
                requiredPermissionToEdit: 'identity.manage-oauth-provider',
            ),
        ];
    }

    public function getAuthorizationUrl(string $redirectUri, array $scopes): string
    {
        $credentials = $this->credentialsOrFail();

        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
        ]);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $credentials = $this->credentialsOrFail();

        $response = Http::asForm()
            ->timeout(10)
            ->connectTimeout(5)
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Structural credential-completeness check, not a live Google
     * endpoint call -- see SmtpEmailProvider::healthCheck()'s identical
     * reasoning.
     */
    public function healthCheck(): bool
    {
        $credentials = $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;

        if ($credentials === null) {
            return false;
        }

        return $this->isStructurallyComplete($credentials);
    }

    /**
     * §27.5's Edit->Test->Save rule -- validates the given, unsaved
     * credentials directly, the Vault is never touched.
     */
    public function testCredentials(array $credentials): bool
    {
        return $this->isStructurallyComplete($credentials);
    }

    private function isStructurallyComplete(array $credentials): bool
    {
        return filled($credentials['client_id'] ?? null) && filled($credentials['client_secret'] ?? null);
    }

    private function credentialsOrFail(): array
    {
        $credentials = $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;

        if ($credentials === null) {
            throw new RuntimeException('GoogleOAuthProvider: no credentials configured for '.self::SLOT_KEY.'.');
        }

        return $credentials;
    }
}
