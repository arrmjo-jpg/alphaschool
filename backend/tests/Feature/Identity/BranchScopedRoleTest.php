<?php

use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

function withTeam(?int $branchId): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
}

it('grants a role in one branch without granting it in another', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branchA->id);
    $role = Role::create(['name' => 'teacher', 'guard_name' => 'sanctum', 'branch_id' => null]);
    $user->assignRole($role);

    withTeam($branchA->id);
    expect($user->fresh()->hasRole('teacher'))->toBeTrue();

    withTeam($branchB->id);
    expect($user->fresh()->hasRole('teacher'))->toBeFalse();
});

it('lets the same role be assigned independently per branch to different users', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $teacherInA = User::factory()->create();
    $teacherInB = User::factory()->create();
    $role = Role::create(['name' => 'teacher', 'guard_name' => 'sanctum', 'branch_id' => null]);

    withTeam($branchA->id);
    $teacherInA->assignRole($role);

    withTeam($branchB->id);
    $teacherInB->assignRole($role);

    withTeam($branchA->id);
    expect($teacherInA->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($teacherInB->fresh()->hasRole('teacher'))->toBeFalse();

    withTeam($branchB->id);
    expect($teacherInB->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($teacherInA->fresh()->hasRole('teacher'))->toBeFalse();
});

it('lets a super admin pass every check in a branch created after the bypass logic already existed, with no role grant at all', function () {
    // The bypass itself was built in Sprint 2.2; this branch (and its
    // Team context) is created fresh, right here, well after that --
    // the exact scenario the Playbook's DoD names explicitly.
    $newBranch = Branch::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $ordinaryUser = User::factory()->create();

    Gate::define('do-something-branch-scoped', fn (User $user) => false);

    withTeam($newBranch->id);

    expect(Gate::forUser($superAdmin)->allows('do-something-branch-scoped'))->toBeTrue()
        ->and($superAdmin->hasRole('teacher'))->toBeFalse() // no grant exists -- the bypass, not a role, is what passes
        ->and(Gate::forUser($ordinaryUser)->allows('do-something-branch-scoped'))->toBeFalse();
});
