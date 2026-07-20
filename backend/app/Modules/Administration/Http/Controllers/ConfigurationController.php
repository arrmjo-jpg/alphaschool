<?php

namespace App\Modules\Administration\Http\Controllers;

use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Http\Controllers\Controller;
use App\Modules\Administration\Exceptions\ConfigurationWriteConflictException;
use App\Modules\Administration\Http\Requests\WriteSettingRequest;
use App\Modules\Administration\Models\ConfigurationDefinition;
use App\Modules\Administration\Models\ConfigurationValue;
use App\Modules\Administration\Services\SettingsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The Phase E-B thin adapter (docs/ADMIN_DESIGN_SYSTEM.md §26.13,
 * docs/adr/0023-zod-first-api-contracts.md): an HTTP wrapper over the
 * already-complete SettingsResolver/ConfigurationDefinition, no new
 * business logic. Wire shapes match
 * packages/contracts/src/settings/settings.*.ts exactly.
 *
 * View-gating (which categories/fields appear at all) bypasses for
 * `is_super_admin`, mirroring WorkspaceAccessResolver's own coarse
 * nav-gating stance. Edit-gating (`canEdit`, and the real write
 * endpoint below) does not -- it defers entirely to
 * SettingsResolver::write()'s own assertCanEdit(), which has no such
 * bypass by design (pre-existing business logic this phase does not
 * touch). A field the controller marks canEdit: false must never be
 * writable regardless of who is asking; a field it marks canEdit: true
 * must be exactly what SettingsResolver would also allow -- the two
 * checks are computed identically on purpose (see hasEditPermission()).
 *
 * No branch scope is threaded through yet -- Global Context (§24) is
 * itself frozen design, not implemented in code, so there is nothing
 * real to read a branchId from. Every read/write here resolves at
 * ConfigurationScopeContext::global() until that exists; adding branch
 * scoping later is additive to this controller, not a redesign.
 */
class ConfigurationController extends Controller
{
    public function __construct(private readonly SettingsResolver $resolver) {}

    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();

        $visible = ConfigurationDefinition::query()
            ->get()
            ->filter(fn (ConfigurationDefinition $definition) => $this->hasViewPermission($user, $definition))
            ->groupBy('capability');

        $categories = $visible->map(function (Collection $definitions, string $capability) {
            $hasUnresolvedRequired = $definitions->contains(function (ConfigurationDefinition $definition) {
                $resolved = $this->resolver->resolve($definition->key, ConfigurationScopeContext::global());

                return $definition->required && $resolved->value === null;
            });

            return [
                'key' => $capability,
                'status' => $hasUnresolvedRequired ? 'error' : 'ready',
            ];
        })->values();

        return response()->json(['categories' => $categories]);
    }

    public function categorySettings(Request $request, string $capability): JsonResponse
    {
        $user = $request->user();

        $settings = ConfigurationDefinition::query()
            ->where('capability', $capability)
            ->get()
            ->filter(fn (ConfigurationDefinition $definition) => $this->hasViewPermission($user, $definition))
            ->map(function (ConfigurationDefinition $definition) use ($user) {
                $resolved = $this->resolver->resolve($definition->key, ConfigurationScopeContext::global());

                return [
                    'key' => $definition->key,
                    'dataType' => $this->mapDataType($definition->type),
                    'value' => $resolved->value,
                    'resolvedFrom' => $resolved->resolvedAtAltitude ?? 'default',
                    'canEdit' => $this->hasEditPermission($user, $definition),
                    'approvalRequired' => $definition->approval_required,
                    'version' => $resolved->version,
                ];
            })->values();

        return response()->json(['settings' => $settings]);
    }

    public function writeSetting(WriteSettingRequest $request, string $capability, string $key): JsonResponse
    {
        try {
            $result = $this->resolver->write(
                $key,
                $request->validated('value'),
                ConfigurationScopeContext::global(),
                (int) $request->validated('expectedVersion'),
                $request->user(),
            );
        } catch (ConfigurationWriteConflictException $e) {
            $current = $this->resolver->resolve($key, ConfigurationScopeContext::global());

            return response()->json(['message' => $e->getMessage(), 'currentVersion' => $current->version], 409);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'key' => $result->configuration_key,
            'value' => $result->value,
            'version' => $result->version,
            'status' => $result->status === ConfigurationValue::STATUS_PENDING_APPROVAL ? 'pending_approval' : 'active',
        ]);
    }

    private function mapDataType(string $type): string
    {
        return match ($type) {
            'int', 'float' => 'number',
            'bool' => 'boolean',
            default => 'text',
        };
    }

    /**
     * `Model`, not `App\Modules\Identity\Models\User` -- Administration
     * must not depend on any other module directly (ADR-0016 §5,
     * enforced by tests/Architecture/AdministrationPlatformBoundaryTest.php),
     * the exact same reason SettingsResolver::write() itself types its
     * `$actor` parameter as `Model`, not `User`.
     */
    private function hasViewPermission(Model $user, ConfigurationDefinition $definition): bool
    {
        return $user->is_super_admin || $this->hasPermission($user, $definition->required_permission_to_view);
    }

    /**
     * No `is_super_admin` bypass -- computed identically to
     * SettingsResolver::assertCanEdit()'s own strict check, so this
     * flag never promises more than a subsequent write() call would
     * actually allow.
     */
    private function hasEditPermission(Model $user, ConfigurationDefinition $definition): bool
    {
        return $this->hasPermission($user, $definition->required_permission_to_edit);
    }

    private function hasPermission(Model $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, 'sanctum');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
