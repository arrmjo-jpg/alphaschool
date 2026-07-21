<?php

namespace App\Modules\Notifications\Providers;

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Contracts\HealthCheckable;
use App\Core\Contracts\TestsCredentials;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderCredentialFieldDefinition;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Notifications\Contracts\EmailProviderContract;
use App\Modules\Notifications\Mail\SmtpRelayMail;
use Illuminate\Support\Facades\Mail;

/**
 * Proof provider #1 of 3 (Playbook Phase 2's genericity requirement):
 * credential shape is a classic host/port/username/password/encryption
 * quintet, deliberately unlike either sibling proof provider's shape.
 * Self-registers its own Provider Registry slot -- see class docblock
 * on App\Modules\Administration\Services\ProviderRegistry for the
 * self-registration convention this relies on.
 */
class SmtpEmailProvider implements DeclaresProviderSlots, EmailProviderContract, HealthCheckable, TestsCredentials
{
    private const SLOT_KEY = 'notifications.email.smtp';

    public function __construct(
        private readonly ProviderCredentialVault $vault,
    ) {}

    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: self::SLOT_KEY,
                capabilityContract: EmailProviderContract::class,
                credentialFields: [
                    new ProviderCredentialFieldDefinition('host', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('port', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('username', ProviderCredentialFieldDefinition::TYPE_TEXT),
                    new ProviderCredentialFieldDefinition('password', ProviderCredentialFieldDefinition::TYPE_PASSWORD),
                    new ProviderCredentialFieldDefinition('encryption', ProviderCredentialFieldDefinition::TYPE_TEXT),
                ],
                owningModule: 'Notifications',
                requiredPermissionToEdit: 'notifications.manage-email-provider',
            ),
        ];
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $credentials = $this->vault->resolve(self::SLOT_KEY, ConfigurationScopeContext::global())->credentials;

        if ($credentials === null) {
            return false;
        }

        config(['mail.mailers.smtp-provider' => [
            'transport' => 'smtp',
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'encryption' => $credentials['encryption'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]]);

        Mail::mailer('smtp-provider')->to($to)->send(new SmtpRelayMail($subject, $body));

        return true;
    }

    /**
     * Validates credential completeness/shape -- a conservative v1
     * check, not a live SMTP handshake (Playbook Phase 2 specifies
     * "Health-Check Runner v1 (synchronous)" without mandating live
     * vendor connectivity, and a live network call has no place in this
     * codebase's test suite, which never reaches a real external vendor).
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
     * credentials directly, the Vault is never touched. Same
     * conservative completeness check as healthCheck(), just against a
     * different source of values.
     */
    public function testCredentials(array $credentials): bool
    {
        return $this->isStructurallyComplete($credentials);
    }

    private function isStructurallyComplete(array $credentials): bool
    {
        return filled($credentials['host'] ?? null)
            && is_numeric($credentials['port'] ?? null)
            && filled($credentials['username'] ?? null)
            && filled($credentials['password'] ?? null);
    }
}
