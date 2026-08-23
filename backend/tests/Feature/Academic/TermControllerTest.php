<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 1-B's fourth entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * the first real FK-scoped entity and first real applyExtraFilters()
 * consumer.
 */
function userWithCatalogPermissionForTerm(bool $granted, ?Branch $branch = null): User
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

it('creates a term scoped to an academic year', function () {
    $academicYear = AcademicYear::factory()->create();
    $user = userWithCatalogPermissionForTerm(true);

    $response = $this->actingAs($user)->postJson(route('academic.terms.store'), [
        'academic_year_id' => $academicYear->id,
        'name_en' => 'First Term',
        'name_ar' => 'الفصل الأول',
        'sequence_order' => 1,
        'start_date' => $academicYear->start_date->toDateString(),
        'end_date' => $academicYear->start_date->addMonths(4)->toDateString(),
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('terms', ['academic_year_id' => $academicYear->id, 'name_en' => 'First Term', 'status' => 'upcoming']);
});

it('rejects an unknown academic_year_id', function () {
    $user = userWithCatalogPermissionForTerm(true);

    $this->actingAs($user)->postJson(route('academic.terms.store'), [
        'academic_year_id' => 999999,
        'name_en' => 'Bad Term',
        'name_ar' => 'فصل خاطئ',
        'sequence_order' => 1,
        'start_date' => '2026-09-01',
        'end_date' => '2027-01-01',
    ])->assertUnprocessable();
});

it('allows the same sequence_order across two different academic years', function () {
    $yearOne = AcademicYear::factory()->create();
    $yearTwo = AcademicYear::factory()->create();
    Term::factory()->create(['academic_year_id' => $yearOne->id, 'sequence_order' => 1]);
    $user = userWithCatalogPermissionForTerm(true);

    $response = $this->actingAs($user)->postJson(route('academic.terms.store'), [
        'academic_year_id' => $yearTwo->id,
        'name_en' => 'First Term Year Two',
        'name_ar' => 'الفصل الأول للسنة الثانية',
        'sequence_order' => 1,
        'start_date' => $yearTwo->start_date->toDateString(),
        'end_date' => $yearTwo->start_date->addMonths(4)->toDateString(),
    ]);

    $response->assertCreated();
});

it('rejects a duplicate sequence_order within the same academic year', function () {
    $academicYear = AcademicYear::factory()->create();
    Term::factory()->create(['academic_year_id' => $academicYear->id, 'sequence_order' => 1]);
    $user = userWithCatalogPermissionForTerm(true);

    $this->actingAs($user)->postJson(route('academic.terms.store'), [
        'academic_year_id' => $academicYear->id,
        'name_en' => 'Duplicate Order',
        'name_ar' => 'ترتيب مكرر',
        'sequence_order' => 1,
        'start_date' => $academicYear->start_date->toDateString(),
        'end_date' => $academicYear->start_date->addMonths(4)->toDateString(),
    ])->assertUnprocessable();
});

it('filters the list by academic_year_id via applyExtraFilters', function () {
    $yearOne = AcademicYear::factory()->create();
    $yearTwo = AcademicYear::factory()->create();
    Term::factory()->create(['academic_year_id' => $yearOne->id, 'sequence_order' => 1]);
    Term::factory()->create(['academic_year_id' => $yearOne->id, 'sequence_order' => 2]);
    Term::factory()->create(['academic_year_id' => $yearTwo->id, 'sequence_order' => 1]);
    $user = userWithCatalogPermissionForTerm(true);

    $response = $this->actingAs($user)->getJson(route('academic.terms.index', ['academic_year_id' => $yearOne->id]));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2);
});

it('closes a term via close(), matching AcademicYear own idempotent behavior', function () {
    $term = Term::factory()->create(['status' => Term::STATUS_ACTIVE]);
    $user = userWithCatalogPermissionForTerm(true);

    $this->actingAs($user)->patchJson(route('academic.terms.status', ['id' => $term->id]), ['status' => 'closed'])->assertOk();
    $this->assertDatabaseHas('terms', ['id' => $term->id, 'status' => 'closed']);

    // Idempotent -- closing again must not throw.
    $this->actingAs($user)->patchJson(route('academic.terms.status', ['id' => $term->id]), ['status' => 'closed'])->assertOk();
});
