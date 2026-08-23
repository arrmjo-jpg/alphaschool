<?php

use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * Mirrors AcademicYearPolicyTest's own pattern (Sprint 4.1) -- all three
 * admissions.* permissions are seeded but deliberately unassigned to
 * any real role, so each grant/deny path is built ad hoc per test here.
 */
function userWithAdmissionsPermission(?string $permissionName, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    $group = PermissionGroup::firstOrCreate(['code' => 'admissions-test'], ['name' => ['en' => 'x', 'ar' => 'y']]);

    foreach (['admissions.manage-applications', 'admissions.manage-policy', 'admissions.submit-assessment-score'] as $name) {
        Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'sanctum'],
            ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
        );
    }

    if ($permissionName !== null) {
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
        $role->givePermissionTo(Permission::where('name', $permissionName)->where('guard_name', 'sanctum')->first());
        $user->assignRole($role);
    }

    return $user;
}

it('grants full Applicant management to admissions.manage-applications', function () {
    $user = userWithAdmissionsPermission('admissions.manage-applications');
    $applicant = Applicant::factory()->create();

    expect($user->can('update', $applicant))->toBeTrue();
    expect($user->can('decide', $applicant))->toBeTrue();
    expect($user->can('recordAssessmentResult', $applicant))->toBeTrue();
});

it('denies policy-changing actions to admissions.manage-applications alone', function () {
    $user = userWithAdmissionsPermission('admissions.manage-applications');

    expect($user->can('managePolicy', Applicant::class))->toBeFalse();
});

it('grants only assessment-score submission to an Interviewer-shaped grant', function () {
    $user = userWithAdmissionsPermission('admissions.submit-assessment-score');
    $applicant = Applicant::factory()->create();

    expect($user->can('recordAssessmentResult', $applicant))->toBeTrue();
    expect($user->can('update', $applicant))->toBeFalse();
    expect($user->can('decide', $applicant))->toBeFalse();
});

it('denies everything to a user with no admissions permission', function () {
    $user = userWithAdmissionsPermission(null);
    $applicant = Applicant::factory()->create();

    expect($user->can('update', $applicant))->toBeFalse();
    expect($user->can('decide', $applicant))->toBeFalse();
    expect($user->can('recordAssessmentResult', $applicant))->toBeFalse();
});
