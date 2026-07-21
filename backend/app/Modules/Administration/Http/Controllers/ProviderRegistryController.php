<?php

namespace App\Modules\Administration\Http\Controllers;

use App\Core\Contracts\TestsCredentials;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Http\Controllers\Controller;
use App\Modules\Administration\Exceptions\ProviderCredentialWriteConflictException;
use App\Modules\Administration\Http\Requests\TestProviderCredentialsRequest;
use App\Modules\Administration\Http\Requests\WriteProviderCredentialsRequest;
use App\Modules\Administration\Models\ProviderCredential;
use App\Modules\Administration\Models\ProviderRegistration;
use App\Modules\Administration\Services\HealthCheckRunner;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Phase F-B thin adapter (docs/ADMIN_DESIGN_SYSTEM.md §27.13): an
 * HTTP wrapper over the already-complete ProviderManager/
 * ProviderCredentialVault/HealthCheckRunner, no new business logic --
 * mirrors ConfigurationController's own adapter discipline exactly.
 *
 * View-gating (which slots appear at all, and their metadata) has no
 * per-slot permission to check (§27.2's second real difference from
 * Configuration Platform -- ProviderSlotDefinition never declared a
 * `requiredPermissionToView`), so every slot's metadata and health
 * status is visible to any authenticated user reaching this controller
 * at all; real gatekeeping for the workspace itself lives one layer up,
 * in routing/WorkspaceAccessResolver. Edit-gating (`canEdit`, testing,
 * and the real write endpoint) defers entirely to each slot's own
 * `required_permission_to_edit`, checked identically in all three
 * places on purpose (see hasEditPermission()) -- no is_super_admin
 * bypass, matching ProviderCredentialVault::assertCanEdit()'s own
 * strict behavior exactly, the same asymmetry ConfigurationController
 * already established for Configuration Platform.
 *
 * No branch scope is threaded through yet, for the identical reason
 * ConfigurationController doesn't: Global Context (§24) is frozen
 * design, not implemented in code. Every read/write here resolves at
 * ConfigurationScopeContext::global() until that exists.
 */
class ProviderRegistryController extends Controller
{
    public function __construct(
        private readonly ProviderManager $providerManager,
        private readonly ProviderCredentialVault $vault,
        private readonly HealthCheckRunner $healthCheckRunner,
    ) {}

    public function slots(): JsonResponse
    {
        $slots = ProviderRegistration::query()
            ->get()
            ->map(function (ProviderRegistration $registration) {
                $resolved = $this->vault->resolve($registration->slot_key, ConfigurationScopeContext::global());
                $configured = $resolved->credentials !== null;
                $health = $this->healthCheckRunner->check($registration->slot_key);

                return [
                    'key' => $registration->slot_key,
                    'owningModule' => $registration->owning_module,
                    'status' => $this->mapStatus($health['status'], $configured),
                ];
            })->values();

        return response()->json(['slots' => $slots]);
    }

    public function slot(Request $request, string $slotKey): JsonResponse
    {
        $registration = ProviderRegistration::where('slot_key', $slotKey)->firstOrFail();
        $resolved = $this->vault->resolve($slotKey, ConfigurationScopeContext::global());
        $provider = $this->providerManager->resolve($slotKey);

        return response()->json([
            'key' => $registration->slot_key,
            'credentialFields' => $registration->credential_fields,
            'configured' => $resolved->credentials !== null,
            'canEdit' => $this->hasEditPermission($request->user(), $registration),
            'canTest' => $provider instanceof TestsCredentials,
            'version' => $resolved->version,
        ]);
    }

    /**
     * §27.5's Edit->Test->Save rule: the given credentials are handed
     * directly to the resolved Provider's own testCredentials() --
     * ProviderCredentialVault is never invoked here, so nothing is ever
     * persisted regardless of the result. Gated by the same edit
     * permission as a real write, not left open to any viewer -- testing
     * is part of the edit flow, not a separate lower-stakes action.
     */
    public function testCredentials(TestProviderCredentialsRequest $request, string $slotKey): JsonResponse
    {
        $registration = ProviderRegistration::where('slot_key', $slotKey)->firstOrFail();

        if (! $this->hasEditPermission($request->user(), $registration)) {
            return response()->json(['message' => "You do not have permission to test the '{$slotKey}' provider."], 403);
        }

        $provider = $this->providerManager->resolve($slotKey);

        if (! $provider instanceof TestsCredentials) {
            return response()->json(['message' => "The '{$slotKey}' provider does not support connection testing."], 422);
        }

        $ok = $provider->testCredentials($request->validated('credentials'));

        return response()->json(['ok' => $ok]);
    }

    public function writeCredentials(WriteProviderCredentialsRequest $request, string $slotKey): JsonResponse
    {
        try {
            $result = $this->vault->write(
                $slotKey,
                $request->validated('credentials'),
                ConfigurationScopeContext::global(),
                (int) $request->validated('expectedVersion'),
                $request->user(),
            );
        } catch (ProviderCredentialWriteConflictException $e) {
            $current = $this->vault->resolve($slotKey, ConfigurationScopeContext::global());

            return response()->json(['message' => $e->getMessage(), 'currentVersion' => $current->version], 409);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'version' => $result->version,
            'status' => $result->status === ProviderCredential::STATUS_PENDING_APPROVAL ? 'pending_approval' : 'active',
        ]);
    }

    /**
     * An unconfigured slot is always `needs-setup`, regardless of what
     * HealthCheckRunner reports -- every real Provider's own
     * healthCheck() already returns false when no credential is stored
     * (a `null` Vault resolution), which would otherwise surface as the
     * indistinguishable-looking `unhealthy`/Error state for a slot that
     * was simply never configured, not one that's genuinely broken.
     */
    private function mapStatus(string $healthCheckStatus, bool $configured): string
    {
        if (! $configured) {
            return 'needs-setup';
        }

        return match ($healthCheckStatus) {
            'healthy' => 'ready',
            'unhealthy' => 'error',
            'not_checkable' => 'disabled',
            default => 'needs-setup',
        };
    }

    /**
     * `Model`, not `App\Modules\Identity\Models\User` -- Administration
     * must not depend on any other module directly (ADR-0016 §5),
     * mirroring ConfigurationController's own identical convention.
     */
    private function hasEditPermission(Model $user, ProviderRegistration $registration): bool
    {
        try {
            return $user->hasPermissionTo($registration->required_permission_to_edit, 'sanctum');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
