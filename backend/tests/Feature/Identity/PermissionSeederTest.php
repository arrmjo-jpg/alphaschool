<?php

use App\Core\Models\Organization;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\School;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\PermissionSeeder;

it('seeds permission groups, permissions, and baseline roles idempotently', function () {
    (new PermissionSeeder)->run();
    $countsAfterFirstRun = [
        'groups' => PermissionGroup::count(),
        'permissions' => Permission::count(),
        'roles' => Role::count(),
    ];

    // Running it again must not create duplicates.
    (new PermissionSeeder)->run();

    expect(PermissionGroup::count())->toBe($countsAfterFirstRun['groups'])
        ->and(Permission::count())->toBe($countsAfterFirstRun['permissions'])
        ->and(Role::count())->toBe($countsAfterFirstRun['roles']);
});

it('seeds every baseline role as global, not scoped to any branch', function () {
    (new PermissionSeeder)->run();

    $roles = Role::all();

    expect($roles)->not->toBeEmpty();
    foreach ($roles as $role) {
        expect($role->branch_id)->toBeNull();
    }
});

it('grants the registrar role real people permissions, and leaves teacher/hr_manager/accountant empty', function () {
    (new PermissionSeeder)->run();

    $registrar = Role::where('name', 'registrar')->firstOrFail();
    $teacher = Role::where('name', 'teacher')->firstOrFail();

    expect($registrar->permissions()->pluck('name')->all())
        // identity.review-duplicates added Sprint 3.1 (Addendum C10) --
        // ordinary registrar work, distinct from the higher-stakes
        // identity.approve-merge/approve-anonymization the same
        // Identity Governance group seeds but assigns to no role yet.
        ->toEqual(['people.view', 'people.create', 'people.update', 'identity.review-duplicates'])
        ->and($teacher->permissions()->count())->toBe(0);
});

it('seeds exactly one organization and one school', function () {
    (new OrganizationSeeder)->run();
    (new OrganizationSeeder)->run();

    expect(Organization::count())->toBe(1)
        ->and(School::count())->toBe(1);
});
