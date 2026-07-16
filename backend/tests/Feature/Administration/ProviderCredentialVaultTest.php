<?php

use App\Core\Contracts\DeclaresProviderSlots;
use App\Core\Services\ApprovalEngine;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\ProviderSlotDefinition;
use App\Modules\Administration\Exceptions\ProviderCredentialWriteConflictException;
use App\Modules\Administration\Models\ProviderCredential;
use App\Modules\Administration\Services\ProviderCredentialVault;
use App\Modules\Administration\Services\ProviderRegistry;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * A fixture Provider slot -- deliberately two credential fields of
 * different sensitivity to prove the Vault round-trips an arbitrary
 * shape, not one hardcoded key.
 */
class VaultTestProvider implements DeclaresProviderSlots
{
    public static function providerSlots(): array
    {
        return [
            new ProviderSlotDefinition(
                slotKey: 'test.vault-provider',
                capabilityContract: 'test.category',
                credentialFields: ['api_key', 'api_secret'],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
            ),
            new ProviderSlotDefinition(
                slotKey: 'test.vault-approval-gated',
                capabilityContract: 'test.category',
                credentialFields: ['token'],
                owningModule: 'Test',
                requiredPermissionToEdit: 'test.manage-provider',
                approvalRequired: true,
                approvalPermission: 'test.approve-provider',
            ),
        ];
    }
}

function seedVaultTestPermission(string $name): Permission
{
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);

    return Permission::firstOrCreate(
        ['name' => $name, 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => $name, 'ar' => $name]],
    );
}

function userWithVaultTestPermission(string $permission, ?int $branchId = null): User
{
    $branchId ??= Branch::factory()->create()->id;

    $user = User::factory()->create();
    withTeam($branchId);
    $role = Role::create(['name' => "role-{$permission}-".uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo(seedVaultTestPermission($permission));
    $user->assignRole($role);

    return $user->fresh();
}

beforeEach(function () {
    config(['administration.registered_provider_slots' => [VaultTestProvider::class]]);
    app(ProviderRegistry::class)->sync();
});

it('resolves null when no credential has ever been written for a slot', function () {
    $resolved = app(ProviderCredentialVault::class)->resolve('test.vault-provider', ConfigurationScopeContext::global());

    expect($resolved->credentials)->toBeNull()
        ->and($resolved->resolvedAtAltitude)->toBeNull()
        ->and($resolved->version)->toBe(0);
});

it('writes a global credential and resolves it back decrypted, with a trace', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');

    app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'k1', 'api_secret' => 's1'], ConfigurationScopeContext::global(), 0, $editor);
    $resolved = app(ProviderCredentialVault::class)->resolve('test.vault-provider', ConfigurationScopeContext::global());

    expect($resolved->credentials)->toBe(['api_key' => 'k1', 'api_secret' => 's1'])
        ->and($resolved->resolvedAtAltitude)->toBe('global')
        ->and($resolved->trace)->toHaveCount(1)
        ->and($resolved->version)->toBe(1);
});

it('lets a Branch-altitude credential override the Global one at that branch only', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $editor = userWithVaultTestPermission('test.manage-provider');

    $vault = app(ProviderCredentialVault::class);
    $vault->write('test.vault-provider', ['api_key' => 'global-key', 'api_secret' => 'global-secret'], ConfigurationScopeContext::global(), 0, $editor);
    $vault->write('test.vault-provider', ['api_key' => 'branch-a-key', 'api_secret' => 'branch-a-secret'], ConfigurationScopeContext::forBranch($branchA->id), 0, $editor);

    $atBranchA = $vault->resolve('test.vault-provider', ConfigurationScopeContext::forBranch($branchA->id));
    $atBranchB = $vault->resolve('test.vault-provider', ConfigurationScopeContext::forBranch($branchB->id));

    expect($atBranchA->credentials['api_key'])->toBe('branch-a-key')
        ->and($atBranchB->credentials['api_key'])->toBe('global-key');
});

it('rejects a write whose expectedVersion does not match the current row', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');
    $vault = app(ProviderCredentialVault::class);

    $vault->write('test.vault-provider', ['api_key' => 'k1', 'api_secret' => 's1'], ConfigurationScopeContext::global(), 0, $editor);

    expect(fn () => $vault->write('test.vault-provider', ['api_key' => 'stale', 'api_secret' => 'stale'], ConfigurationScopeContext::global(), 0, $editor))
        ->toThrow(ProviderCredentialWriteConflictException::class);
});

it('never overwrites a credential in place -- each write appends a new version and closes the prior one', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');
    $vault = app(ProviderCredentialVault::class);

    $vault->write('test.vault-provider', ['api_key' => 'v1', 'api_secret' => 's1'], ConfigurationScopeContext::global(), 0, $editor);
    $current = $vault->resolve('test.vault-provider', ConfigurationScopeContext::global());
    $vault->write('test.vault-provider', ['api_key' => 'v2', 'api_secret' => 's2'], ConfigurationScopeContext::global(), $current->version, $editor);

    // Mirrors SettingsResolver::writeDirectly()'s own versioned-write
    // convention exactly: the prior row's status stays 'active', but its
    // effective_until is set to now(), which currentRow()'s own
    // effective_until > now() filter excludes from future resolution --
    // the row is never deleted or status-flipped, only closed. Both rows
    // remain permanently queryable (Blueprint §7's "never overwrite
    // history"), found by actually running this assertion rather than
    // assuming a STATUS_SUPERSEDED transition that only the approval
    // path (activateApprovedWrite()) performs.
    expect(ProviderCredential::where('slot_key', 'test.vault-provider')->count())->toBe(2)
        ->and(ProviderCredential::where('slot_key', 'test.vault-provider')->whereNotNull('effective_until')->count())->toBe(1)
        ->and($vault->resolve('test.vault-provider', ConfigurationScopeContext::global())->credentials['api_key'])->toBe('v2');
});

it('refuses a write from an actor lacking the required edit permission', function () {
    $unprivileged = User::factory()->create();

    expect(fn () => app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'x', 'api_secret' => 'y'], ConfigurationScopeContext::global(), 0, $unprivileged))
        ->toThrow(RuntimeException::class);
});

it('refuses a write missing a declared credential field', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');

    expect(fn () => app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'x'], ConfigurationScopeContext::global(), 0, $editor))
        ->toThrow(InvalidArgumentException::class, 'missing required credential field');
});

it('refuses a write carrying an undeclared credential field', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');

    expect(fn () => app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'x', 'api_secret' => 'y', 'extra_field' => 'z'], ConfigurationScopeContext::global(), 0, $editor))
        ->toThrow(InvalidArgumentException::class, 'unexpected credential field');
});

it('routes an approval-required credential write through ApprovalEngine instead of activating it immediately', function () {
    $branch = Branch::factory()->create();
    $requester = userWithVaultTestPermission('test.manage-provider', $branch->id);
    $approver = userWithVaultTestPermission('test.approve-provider', $branch->id);

    withTeam($branch->id);
    $pending = app(ProviderCredentialVault::class)->write('test.vault-approval-gated', ['token' => 'proposed'], ConfigurationScopeContext::global(), 0, $requester);

    expect($pending->status)->toBe(ProviderCredential::STATUS_PENDING_APPROVAL)
        ->and($pending->approval_request_id)->not->toBeNull();

    $resolved = app(ProviderCredentialVault::class)->resolve('test.vault-approval-gated', ConfigurationScopeContext::global());
    expect($resolved->credentials)->toBeNull();

    app(ApprovalEngine::class)->approve($pending->approvalRequest, $approver);
    app(ProviderCredentialVault::class)->activateApprovedWrite($pending->fresh());

    $resolvedAfterApproval = app(ProviderCredentialVault::class)->resolve('test.vault-approval-gated', ConfigurationScopeContext::global());
    expect($resolvedAfterApproval->credentials)->toBe(['token' => 'proposed']);
});

it('never exposes a decrypted credential value in the model\'s array/JSON representation', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');
    app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'super-secret-key', 'api_secret' => 'super-secret-value'], ConfigurationScopeContext::global(), 0, $editor);

    $row = ProviderCredential::where('slot_key', 'test.vault-provider')->first();
    $serialized = json_encode($row);

    expect($serialized)->not->toContain('super-secret-key')
        ->and($serialized)->not->toContain('super-secret-value')
        ->and($row->toArray())->not->toHaveKey('credentials');
});

it('stores the credential column as ciphertext at rest, never plaintext', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');
    app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'plaintext-marker-value', 'api_secret' => 's1'], ConfigurationScopeContext::global(), 0, $editor);

    $rawColumn = DB::table('provider_credentials')->where('slot_key', 'test.vault-provider')->value('credentials');

    expect($rawColumn)->not->toContain('plaintext-marker-value');
});

it('never writes a credential value diff into the Audit Engine -- only who/when, in either direction', function () {
    $editor = userWithVaultTestPermission('test.manage-provider');
    app(ProviderCredentialVault::class)->write('test.vault-provider', ['api_key' => 'audit-proof-key', 'api_secret' => 'audit-proof-secret'], ConfigurationScopeContext::global(), 0, $editor);

    $activityLog = Activity::query()
        ->where('subject_type', ProviderCredential::class)
        ->latest('id')
        ->first();

    expect($activityLog)->not->toBeNull();

    $serializedActivity = json_encode($activityLog);
    expect($serializedActivity)->not->toContain('audit-proof-key')
        ->and($serializedActivity)->not->toContain('audit-proof-secret')
        ->and($activityLog->properties->toArray())->toBe([]);
});
