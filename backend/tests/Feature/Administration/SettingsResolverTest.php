<?php

use App\Core\Contracts\DeclaresSettingsSchema;
use App\Core\Services\ApprovalEngine;
use App\Core\ValueObjects\ConfigurationScopeContext;
use App\Core\ValueObjects\SettingDefinition;
use App\Modules\Administration\Exceptions\ConfigurationWriteConflictException;
use App\Modules\Administration\Models\ConfigurationValue;
use App\Modules\Administration\Services\SettingsResolver;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\IdentityOtpSettings;

/**
 * A branch-overridable fixture key -- deliberately not one of Identity's
 * real OTP keys (both global-only, ADR-0018 Decision 4's "burden of
 * proof is on adding branch scope") -- so the altitude chain's
 * branch-beats-global precedence has something real to prove against.
 */
class BranchOverridableTestSettings implements DeclaresSettingsSchema
{
    public static function settingsSchema(): array
    {
        return [
            new SettingDefinition(
                key: 'test.branch-overridable',
                type: 'string',
                eligibleAltitudes: ['global', 'branch'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'operational',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                defaultValue: 'the-default',
            ),
            new SettingDefinition(
                key: 'test.approval-gated',
                type: 'string',
                eligibleAltitudes: ['global'],
                owningModule: 'Test',
                capability: 'policy-configuration-governance',
                dataClassification: 'financial',
                requiredPermissionToView: 'test.view',
                requiredPermissionToEdit: 'test.edit',
                approvalRequired: true,
                approvalPermission: 'test.approve',
                heuristicAcknowledgment: 'Financial + approval_required = true already satisfies the floor; acknowledgment supplied for consistency with sync\'s own requirement when a classification trigger fires alongside an explicit review need.',
            ),
        ];
    }
}

function seedTestPermission(string $name): Permission
{
    $group = PermissionGroup::firstOrCreate(['code' => 'test-group'], ['name' => ['en' => 'x', 'ar' => 'y']]);

    return Permission::firstOrCreate(
        ['name' => $name, 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => $name, 'ar' => $name]],
    );
}

function userWithTestPermission(string $permission, ?int $branchId = null): User
{
    // model_has_roles.branch_id is NOT NULL -- withTeam(null) before an
    // assignRole() call fails at the database, not merely resolves the
    // role as "global" the way Role's own branch_id=null column does.
    // Every prior sprint's own permission-granting test helper
    // (e.g. MergeOrchestrationServiceTest::approverUser()) creates a
    // real Branch first for exactly this reason -- found here the same
    // way, by actually running the negative case rather than assuming
    // the pattern generalizes.
    $branchId ??= Branch::factory()->create()->id;

    $user = User::factory()->create();
    withTeam($branchId);
    $role = Role::create(['name' => "role-{$permission}-".uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo(seedTestPermission($permission));
    $user->assignRole($role);

    return $user->fresh();
}

beforeEach(fn () => registerConfigurationSchemas([
    BranchOverridableTestSettings::class,
    IdentityOtpSettings::class,
]));

it('resolves the declared default when no value row exists at any eligible altitude', function () {
    $resolved = app(SettingsResolver::class)->resolve('test.branch-overridable', ConfigurationScopeContext::global());

    expect($resolved->value)->toBe('the-default')
        ->and($resolved->resolvedAtAltitude)->toBeNull()
        ->and($resolved->version)->toBe(0);
});

it('writes a global value and resolves it back with a trace showing the global altitude checked', function () {
    $editor = userWithTestPermission('test.edit');

    app(SettingsResolver::class)->write('test.branch-overridable', 'global-value', ConfigurationScopeContext::global(), 0, $editor);
    $resolved = app(SettingsResolver::class)->resolve('test.branch-overridable', ConfigurationScopeContext::global());

    expect($resolved->value)->toBe('global-value')
        ->and($resolved->resolvedAtAltitude)->toBe('global')
        ->and($resolved->trace)->toHaveCount(1)
        ->and($resolved->trace[0]->altitude)->toBe('global')
        ->and($resolved->trace[0]->hadValue)->toBeTrue()
        ->and($resolved->version)->toBe(1);
});

it('lets a Branch-altitude override win over the Global value at that branch, without affecting other branches', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $editor = userWithTestPermission('test.edit');

    $resolver = app(SettingsResolver::class);
    $resolver->write('test.branch-overridable', 'global-value', ConfigurationScopeContext::global(), 0, $editor);
    $resolver->write('test.branch-overridable', 'branch-a-value', ConfigurationScopeContext::forBranch($branchA->id), 0, $editor);

    $atBranchA = $resolver->resolve('test.branch-overridable', ConfigurationScopeContext::forBranch($branchA->id));
    $atBranchB = $resolver->resolve('test.branch-overridable', ConfigurationScopeContext::forBranch($branchB->id));

    expect($atBranchA->value)->toBe('branch-a-value')
        ->and($atBranchA->resolvedAtAltitude)->toBe('branch')
        ->and($atBranchB->value)->toBe('global-value')
        ->and($atBranchB->resolvedAtAltitude)->toBe('global');
});

it('rejects a write whose expectedVersion does not match the current row (Decision 8)', function () {
    $editor = userWithTestPermission('test.edit');
    $resolver = app(SettingsResolver::class);

    $resolver->write('test.branch-overridable', 'first-value', ConfigurationScopeContext::global(), 0, $editor);

    expect(fn () => $resolver->write('test.branch-overridable', 'stale-write', ConfigurationScopeContext::global(), 0, $editor))
        ->toThrow(ConfigurationWriteConflictException::class);
});

it('accepts a write once the caller re-resolves and supplies the fresh version', function () {
    $editor = userWithTestPermission('test.edit');
    $resolver = app(SettingsResolver::class);

    $resolver->write('test.branch-overridable', 'first-value', ConfigurationScopeContext::global(), 0, $editor);
    $current = $resolver->resolve('test.branch-overridable', ConfigurationScopeContext::global());
    $resolver->write('test.branch-overridable', 'second-value', ConfigurationScopeContext::global(), $current->version, $editor);

    expect($resolver->resolve('test.branch-overridable', ConfigurationScopeContext::global())->value)->toBe('second-value');
});

it('refuses a write from an actor lacking the required edit permission', function () {
    $unprivileged = User::factory()->create();

    expect(fn () => app(SettingsResolver::class)->write('test.branch-overridable', 'x', ConfigurationScopeContext::global(), 0, $unprivileged))
        ->toThrow(RuntimeException::class);
});

it('refuses a write outside the eligible altitude for that key', function () {
    $editor = userWithTestPermission('test.edit');

    // identity.otp.code_length is global-only -- no "branch" in its
    // eligibleAltitudes -- proving the eligibility check independently
    // of the fixture's own permissive key.
    expect(fn () => app(SettingsResolver::class)->write('identity.otp.code_length', 8, ConfigurationScopeContext::forBranch(1), 0, $editor))
        ->toThrow(InvalidArgumentException::class, 'not eligible for branch-altitude');
});

it('routes an approval-required write through ApprovalEngine instead of activating it immediately', function () {
    // Spatie Teams' permission check is scoped by the CURRENT ambient
    // team (PermissionRegistrar::setPermissionsTeamId()), not by
    // whichever team a role happened to be assigned under -- creating a
    // second actor in a different branch silently shifts that ambient
    // context out from under the first. Sharing one branch (and
    // re-asserting it immediately before each permission-sensitive
    // call) avoids the drift; found by running this test, not assumed.
    $branch = Branch::factory()->create();
    $requester = userWithTestPermission('test.edit', $branch->id);
    $approver = userWithTestPermission('test.approve', $branch->id);

    withTeam($branch->id);
    $pending = app(SettingsResolver::class)->write('test.approval-gated', 'proposed-value', ConfigurationScopeContext::global(), 0, $requester);

    expect($pending->status)->toBe(ConfigurationValue::STATUS_PENDING_APPROVAL)
        ->and($pending->approval_request_id)->not->toBeNull();

    $resolved = app(SettingsResolver::class)->resolve('test.approval-gated', ConfigurationScopeContext::global());
    expect($resolved->resolvedAtAltitude)->toBeNull(); // still the default -- the pending row is not active

    app(ApprovalEngine::class)->approve($pending->approvalRequest, $approver);
    app(SettingsResolver::class)->activateApprovedWrite($pending->fresh());

    $resolvedAfterApproval = app(SettingsResolver::class)->resolve('test.approval-gated', ConfigurationScopeContext::global());
    expect($resolvedAfterApproval->value)->toBe('proposed-value')
        ->and($resolvedAfterApproval->resolvedAtAltitude)->toBe('global');
});

it('proves no-self-approval even for a Super Admin account, on an approval-gated Configuration write', function () {
    // Holds BOTH the edit and approve permissions -- if self-approval
    // weren't structurally blocked, this account could rubber-stamp its
    // own Configuration change. is_super_admin = true specifically to
    // prove the block is independent of the Gate::before bypass
    // (docs/DOMAIN_BLUEPRINT.md §8) -- ApprovalEngine's own
    // requester-id guard is a plain PHP comparison, never routed
    // through Gate::check(), the identical structural proof already
    // established for Person Merge (Sprint 3.2).
    $branch = Branch::factory()->create();
    $actor = userWithTestPermission('test.edit', $branch->id);
    $actor->forceFill(['is_super_admin' => true])->save();

    // Permissions are never granted directly to a user (Blueprint §8) --
    // a second role, held by the same account, grants test.approve too.
    // Same branch as the first role, and re-asserted immediately before
    // write() -- see the sibling test above for why the ambient team
    // context must be re-set, not merely set once at user-creation time.
    withTeam($branch->id);
    $approveRole = Role::create(['name' => 'self-approval-proof-role', 'guard_name' => 'sanctum', 'branch_id' => null]);
    $approveRole->givePermissionTo(seedTestPermission('test.approve'));
    $actor->assignRole($approveRole);

    withTeam($branch->id);
    $pending = app(SettingsResolver::class)->write('test.approval-gated', 'x', ConfigurationScopeContext::global(), 0, $actor->fresh());

    expect(fn () => app(ApprovalEngine::class)->approve($pending->approvalRequest, $actor->fresh()))
        ->toThrow(RuntimeException::class, 'the requester may not approve their own request');
});
