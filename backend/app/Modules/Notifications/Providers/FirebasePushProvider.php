<?php

namespace App\Modules\Notifications\Providers;

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\Contracts\TestsCredentials;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Notifications\Contracts\PushProviderContract;
use Illuminate\Support\Facades\Http;

/**
 * Proof provider #3 of 3: credential shape is an FCM v1 service-account
 * triple (project_id/client_email/private_key) -- a PEM private key,
 * genuinely unlike either sibling proof provider's credential shape
 * (SMTP's host/port/user/pass, OAuth's client_id/secret pair). This is
 * the shape that most tests the Vault's encrypted-payload column: a
 * multi-line PEM string round-tripping correctly through the encrypted
 * cast is a stronger proof than a short token would be.
 */
class FirebasePushProvider implements DeclaresProviderSlots, HealthCheckable, PushProviderContract, TestsCredentials
{
    private const SLOT_KEY = 'notifications.push.firebase';

    public function __construct(
        private readonly ProviderCredentialVault $vault,
    ) {}

    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: self::SLOT_KEY,
                capabilityContract: PushProviderContract::class,
                credentialFields: [
                    new ProviderCredentialFieldDefinition('project_id', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('client_email', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('private_key', ProviderCredentialFieldDefinition::TYPE_SECRET),
                ],
                owningModule: 'Notifications',
                requiredPermissionToEdit: 'notifications.manage-push-provider',
            ),
        ];
    }

    public function sendPush(string $deviceToken, string $title, string $body): bool
    {
        $credentials = $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;

        if ($credentials === null) {
            return false;
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withToken($this->signedAccessToken($credentials))
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                ],
            ]);

        return $response->successful();
    }

    /**
     * Structural credential-completeness check (project_id present,
     * private_key looks like a PEM block) -- not a live FCM call, same
     * reasoning as the sibling proof providers.
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
        return filled($credentials['project_id'] ?? null)
            && filled($credentials['client_email'] ?? null)
            && str_contains($credentials['private_key'] ?? '', 'PRIVATE KEY');
    }

    /**
     * A real FCM v1 integration exchanges the service-account key for a
     * short-lived OAuth token via Google's token endpoint (the same
     * shape GoogleOAuthProvider already implements for a different
     * purpose) -- left as a documented seam rather than duplicated here,
     * since implementing the full JWT-signing exchange is beyond what
     * this phase needs to prove (a working credential round-trip through
     * the Vault, not a production-hardened FCM client).
     */
    private function signedAccessToken(array $credentials): string
    {
        return 'unsigned-placeholder-token';
    }
}
