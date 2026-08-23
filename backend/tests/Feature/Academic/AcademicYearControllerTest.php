<?php

use App\Modules\Academic\Events\AcademicYearClosed;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 1-B's first real Academic entity adapter
 * (docs/ADMIN_DESIGN_SYSTEM.md §28.17) -- verified as a real HTTP
 * Feature test, not raw curl, for the same Spatie Teams-in-one-process
 * reason ConfigurationControllerTest/ProviderRegistryControllerTest
 * already document.
 */
function userWithCatalogPermission(bool $granted, ?Branch $branch = null): User
{
    $branch ??= Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);

    if ($granted) {
        $group = PermissionGroup::firstOrCreate(['code' => 'academic'], ['name' => ['en' => 'Academic', 'ar' => 'الأكاديمي']]);
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'sanctum', 'branch_id' => null]);
        $permission = Permission::firstOrCreate(
            ['name' => 'academic.manage-catalog', 'guard_name' => 'sanctum'],
            ['permission_group_id' => $group->id, 'display_name' => ['en' => 'x', 'ar' => 'y']],
        );
        $role->givePermissionTo($permission);
        $user->assignRole($role);
    }

    return $user->fresh();
}

it('lists academic years for a permitted user, paginated', function () {
    AcademicYear::factory()->count(3)->create();
    $user = userWithCatalogPermission(true);

    $response = $this->actingAs($user)->getJson(route('academic.academic-years.index'));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
});

it('denies listing for a user without the permission', function () {
    $user = userWithCatalogPermission(false);

    $this->actingAs($user)->getJson(route('academic.academic-years.index'))->assertForbidden();
});

it('allows a super admin regardless of granted permissions', function () {
    AcademicYear::factory()->create();
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)->getJson(route('academic.academic-years.index'))->assertOk();
});

it('creates an academic year with valid data', function () {
    $user = userWithCatalogPermission(true);

    $response = $this->actingAs($user)->postJson(route('academic.academic-years.store'), [
        'name_en' => '2026-2027',
        'name_ar' => '٢٠٢٦-٢٠٢٧',
        'start_date' => '2026-09-01',
        'end_date' => '2027-06-30',
    ]);

    $response->assertCreated();
    // Asserts the JSON response itself, not just the DB row -- the bug
    // this guards against (found live, UI Sprint 1-B) was specifically
    // that a freshly ::create()'d instance's in-memory `status` was
    // absent from its own JSON representation even though the DB row
    // was correct throughout (the migration's own DB-level default
    // always applied); assertDatabaseHas alone would pass either way.
    expect($response->json('status'))->toBe('upcoming');
    $this->assertDatabaseHas('academic_years', ['name_en' => '2026-2027', 'status' => 'upcoming']);
});

it('rejects creation with end_date before start_date', function () {
    $user = userWithCatalogPermission(true);

    $response = $this->actingAs($user)->postJson(route('academic.academic-years.store'), [
        'name_en' => 'Bad Year',
        'name_ar' => 'سنة خاطئة',
        'start_date' => '2026-09-01',
        'end_date' => '2026-01-01',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
});

it('updates an existing academic year', function () {
    $academicYear = AcademicYear::factory()->create(['name_en' => 'Old Name']);
    $user = userWithCatalogPermission(true);

    $response = $this->actingAs($user)->patchJson(route('academic.academic-years.update', ['id' => $academicYear->id]), [
        'name_en' => 'New Name',
        'name_ar' => $academicYear->name_ar,
        'start_date' => $academicYear->start_date->toDateString(),
        'end_date' => $academicYear->end_date->toDateString(),
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('academic_years', ['id' => $academicYear->id, 'name_en' => 'New Name']);
});

it('closes an academic year via close(), dispatching AcademicYearClosed exactly once', function () {
    Event::fake([AcademicYearClosed::class]);
    $academicYear = AcademicYear::factory()->create(['status' => AcademicYear::STATUS_ACTIVE]);
    $user = userWithCatalogPermission(true);

    $response = $this->actingAs($user)->patchJson(route('academic.academic-years.status', ['id' => $academicYear->id]), [
        'status' => 'closed',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('academic_years', ['id' => $academicYear->id, 'status' => 'closed']);
    Event::assertDispatchedTimes(AcademicYearClosed::class, 1);
});

it('closing an already-closed academic year is idempotent, dispatching no event', function () {
    Event::fake([AcademicYearClosed::class]);
    $academicYear = AcademicYear::factory()->create(['status' => AcademicYear::STATUS_CLOSED]);
    $user = userWithCatalogPermission(true);

    $this->actingAs($user)->patchJson(route('academic.academic-years.status', ['id' => $academicYear->id]), [
        'status' => 'closed',
    ])->assertOk();

    Event::assertNotDispatched(AcademicYearClosed::class);
});

it('has no delete route registered for academic years', function () {
    expect(collect(app('router')->getRoutes())->contains(
        fn ($route) => str_contains($route->uri(), 'academic-years') && in_array('DELETE', $route->methods()),
    ))->toBeFalse();
});
