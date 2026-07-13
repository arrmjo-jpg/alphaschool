<?php

use App\Core\Services\ApprovalEngine;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\IdentityMaintenance\Models\MergeRequest;
use App\Modules\IdentityMaintenance\Services\MergeOrchestrationService;
use App\Modules\IdentityMaintenance\Support\ApprovalRoutingResolver;
use App\Modules\People\Models\Contact;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Person;

function seedApproveMergePermission(): Permission
{
    $group = PermissionGroup::firstOrCreate(['code' => 'identity-governance'], ['name' => ['en' => 'Identity Governance', 'ar' => 'حوكمة الهوية']]);

    return Permission::firstOrCreate(
        ['name' => 'identity.approve-merge', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'Approve Person Merge', 'ar' => 'اعتماد دمج الأشخاص']],
    );
}

function approverUser(): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);
    $permission = seedApproveMergePermission();
    $role = Role::create(['name' => 'merge-approver', 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

function requesterUser(): User
{
    return User::factory()->create();
}

it('runs a full merge lifecycle end to end and moves real data', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    Employee::factory()->create(['person_id' => $losing->id]);
    Contact::create(['person_id' => $losing->id, 'type' => Contact::TYPE_EMAIL, 'value' => 'a@example.com']);

    $requester = requesterUser();
    $approver = approverUser();

    $mergeRequest = MergeRequest::factory()->create([
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
        'requested_by_id' => $requester->id,
    ]);

    $service = app(MergeOrchestrationService::class);

    $impacts = $service->preview($mergeRequest);
    expect($impacts)->not->toBeEmpty();

    $dryRunResult = $service->dryRun($mergeRequest);
    expect($dryRunResult['passed'])->toBeTrue()
        ->and($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_DRY_RUN_PASSED);

    $service->requestApproval($mergeRequest->fresh());
    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_PENDING_APPROVAL);

    $service->approve($mergeRequest->fresh(), $approver);
    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_APPROVED);

    $executed = $service->execute($mergeRequest->fresh());
    expect($executed->status)->toBe(MergeRequest::STATUS_EXECUTED)
        ->and($executed->losing_person_snapshot)->not->toBeNull();

    expect(Employee::where('person_id', $winning->id)->exists())->toBeTrue()
        ->and(Employee::where('person_id', $losing->id)->exists())->toBeFalse()
        ->and(Contact::where('person_id', $winning->id)->where('value', 'a@example.com')->exists())->toBeTrue()
        ->and($executed->logs()->count())->toBeGreaterThan(0);
});

it('proves no-self-approval even for a Super Admin account', function () {
    seedApproveMergePermission();
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $mergeRequest = MergeRequest::factory()->create([
        'requested_by_id' => $superAdmin->id,
        'status' => MergeRequest::STATUS_PENDING_APPROVAL,
    ]);

    $approvalRequest = app(ApprovalEngine::class)->request(
        $mergeRequest,
        [['required_user_id' => $superAdmin->id]],
        $superAdmin,
    );
    $mergeRequest->update(['approval_request_id' => $approvalRequest->id]);

    // ApprovalEngine's own requester-id guard is independent of the
    // Gate::before Super Admin bypass -- it's a plain PHP comparison,
    // never routed through Gate::check(), so is_super_admin cannot
    // short-circuit it.
    expect(fn () => app(MergeOrchestrationService::class)->approve($mergeRequest->fresh(), $superAdmin))
        ->toThrow(RuntimeException::class);
});

it('rejects a dry run when both Persons already hold an Employee row (structural conflict)', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    Employee::factory()->create(['person_id' => $losing->id]);
    Employee::factory()->create(['person_id' => $winning->id]);

    $mergeRequest = MergeRequest::factory()->create([
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
    ]);

    $result = app(MergeOrchestrationService::class)->dryRun($mergeRequest);

    expect($result['passed'])->toBeFalse()
        ->and($result['conflicts'])->not->toBeEmpty()
        ->and($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_DRAFT);
});

it('excludes the originating DuplicateFlag from the generic reassignment cascade and marks it merged', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    $originatingFlag = DuplicateFlag::factory()->create([
        'source_person_id' => $losing->id,
        'candidate_person_id' => $winning->id,
    ]);
    // An unrelated flag referencing the losing person and a third party
    // -- must cascade normally.
    $thirdParty = Person::factory()->create();
    $unrelatedFlag = DuplicateFlag::factory()->create([
        'source_person_id' => $losing->id,
        'candidate_person_id' => $thirdParty->id,
    ]);

    $requester = requesterUser();
    $approver = approverUser();

    $mergeRequest = MergeRequest::factory()->create([
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
        'duplicate_flag_id' => $originatingFlag->id,
        'requested_by_id' => $requester->id,
    ]);

    $service = app(MergeOrchestrationService::class);
    $service->dryRun($mergeRequest);
    $service->requestApproval($mergeRequest->fresh());
    $service->approve($mergeRequest->fresh(), $approver);
    $service->execute($mergeRequest->fresh());

    expect($originatingFlag->fresh()->status)->toBe(DuplicateFlag::STATUS_MERGED)
        ->and($originatingFlag->fresh()->source_person_id)->toBe($losing->id) // untouched, historical
        ->and($unrelatedFlag->fresh()->source_person_id)->toBe($winning->id); // cascaded normally
});

it('runs a full rollback lifecycle, restoring moved data', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    Employee::factory()->create(['person_id' => $losing->id]);

    $requester = requesterUser();
    $approver = approverUser();

    $mergeRequest = MergeRequest::factory()->create([
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
        'requested_by_id' => $requester->id,
    ]);

    $service = app(MergeOrchestrationService::class);
    $service->dryRun($mergeRequest);
    $service->requestApproval($mergeRequest->fresh());
    $service->approve($mergeRequest->fresh(), $approver);
    $service->execute($mergeRequest->fresh());

    expect(Employee::where('person_id', $winning->id)->exists())->toBeTrue();

    $service->requestRollback($mergeRequest->fresh());
    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_ROLLBACK_REQUESTED);

    $service->approveRollback($mergeRequest->fresh(), $approver);
    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_ROLLBACK_APPROVED);

    $rolledBack = $service->rollback($mergeRequest->fresh());

    expect($rolledBack->status)->toBe(MergeRequest::STATUS_ROLLED_BACK)
        ->and(Employee::where('person_id', $losing->id)->exists())->toBeTrue()
        ->and(Employee::where('person_id', $winning->id)->exists())->toBeFalse();
});

it('blocks rollback when dependent data changed since the merge', function () {
    $losing = Person::factory()->create();
    $winning = Person::factory()->create();
    Employee::factory()->create(['person_id' => $losing->id]);

    $requester = requesterUser();
    $approver = approverUser();

    $mergeRequest = MergeRequest::factory()->create([
        'losing_person_id' => $losing->id,
        'winning_person_id' => $winning->id,
        'requested_by_id' => $requester->id,
    ]);

    $service = app(MergeOrchestrationService::class);
    $service->dryRun($mergeRequest);
    $service->requestApproval($mergeRequest->fresh());
    $service->approve($mergeRequest->fresh(), $approver);
    $service->execute($mergeRequest->fresh());

    // Something else changes the moved Employee row's person_id since
    // the merge -- rollback must detect this and refuse entirely.
    $anotherPerson = Person::factory()->create();
    Employee::where('person_id', $winning->id)->update(['person_id' => $anotherPerson->id]);

    $service->requestRollback($mergeRequest->fresh());
    $service->approveRollback($mergeRequest->fresh(), $approver);

    expect(fn () => $service->rollback($mergeRequest->fresh()))->toThrow(RuntimeException::class);
    expect($mergeRequest->fresh()->status)->toBe(MergeRequest::STATUS_ROLLBACK_APPROVED); // never entered rolling_back
});

it('refuses to execute a MergeRequest that is not currently approved', function () {
    $mergeRequest = MergeRequest::factory()->create(); // draft

    expect(fn () => app(MergeOrchestrationService::class)->execute($mergeRequest))
        ->toThrow(RuntimeException::class);
});

it('ApprovalRoutingResolver throws when zero roles hold the permission', function () {
    Permission::firstOrCreate(['name' => 'identity.approve-merge', 'guard_name' => 'sanctum'], [
        'permission_group_id' => PermissionGroup::firstOrCreate(['code' => 'identity-governance'], ['name' => ['en' => 'x', 'ar' => 'y']])->id,
        'display_name' => ['en' => 'x', 'ar' => 'y'],
    ]);

    expect(fn () => app(ApprovalRoutingResolver::class)->resolveSteps('identity.approve-merge'))
        ->toThrow(RuntimeException::class);
});

it('ApprovalRoutingResolver throws when more than one role holds the permission', function () {
    $permission = seedApproveMergePermission();

    withTeam(null);
    Role::create(['name' => 'role-a', 'guard_name' => 'sanctum', 'branch_id' => null])->givePermissionTo($permission);
    Role::create(['name' => 'role-b', 'guard_name' => 'sanctum', 'branch_id' => null])->givePermissionTo($permission);

    expect(fn () => app(ApprovalRoutingResolver::class)->resolveSteps('identity.approve-merge'))
        ->toThrow(RuntimeException::class);
});

it('ApprovalRoutingResolver resolves cleanly when exactly one role holds the permission', function () {
    $permission = seedApproveMergePermission();

    withTeam(null);
    Role::create(['name' => 'the-one-role', 'guard_name' => 'sanctum', 'branch_id' => null])->givePermissionTo($permission);

    $steps = app(ApprovalRoutingResolver::class)->resolveSteps('identity.approve-merge');

    expect($steps)->toBe([['required_role' => 'the-one-role']]);
});
