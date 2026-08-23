<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\GradeLevel;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SectionAssignment;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Enrollment;
use Database\Seeders\ReasonCodeSeeder;

/**
 * UI Sprint 2's SectionAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §31.6) -- mirrors HomeroomAssignmentControllerTest.php's own coverage
 * shape exactly, anchored on Enrollment instead of Section.
 */
function userWithCatalogPermissionForSectionAssignment(bool $granted): User
{
    $branch = Branch::factory()->create();
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

function matchingEnrollmentAndSectionForController(string $academicYearStatus = 'active'): array
{
    $branch = Branch::factory()->create();
    $academicYear = AcademicYear::factory()->create(['status' => $academicYearStatus]);
    $gradeLevel = GradeLevel::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    $section = Section::factory()->create([
        'branch_id' => $branch->id,
        'academic_year_id' => $academicYear->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    return [$enrollment, $section];
}

it('lists an enrollment\'s section assignment timeline, most recent first', function () {
    $this->seed(ReasonCodeSeeder::class);
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $older = SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
        'effective_from' => now()->subMonths(6),
    ]);
    $older->closeAssignment(new ReasonCode('section_transfer'), now()->subMonths(3));

    $newSection = Section::factory()->create([
        'branch_id' => $enrollment->branch_id,
        'academic_year_id' => $enrollment->academic_year_id,
        'grade_level_id' => $enrollment->grade_level_id,
    ]);
    $newer = SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $newSection->id,
        'effective_from' => now()->subMonths(3),
    ]);

    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->getJson(route('academic.section-assignments.index', ['enrollment_id' => $enrollment->id]));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($newer->id)
        ->and($data[1]['id'])->toBe($older->id)
        ->and($data[1]['status'])->toBe('ended')
        ->and($data[1]['reason_code'])->toBe('section_transfer');
});

it('creates a section assignment for an active enrollment and an open academic year', function () {
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('active')
        ->and($response->json('section_id'))->toBe($section->id)
        ->and($response->json('section_name'))->not->toBeEmpty()
        ->and($response->json('grade_level_name_en'))->not->toBeEmpty();
    $this->assertDatabaseHas('section_assignments', ['enrollment_id' => $enrollment->id, 'section_id' => $section->id]);
});

it('honors a caller-specified effective_from from the Create form', function () {
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
        'effective_from' => '2026-02-01',
    ]);

    $response->assertCreated();
    expect($response->json('effective_from'))->toBe('2026-02-01');
});

it('rejects creating a section assignment for a closed academic year with a 422 attached to section_id, not a 500', function () {
    [$enrollment, $section] = matchingEnrollmentAndSectionForController('closed');
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.section_id.0'))->toContain('closed');
});

it('rejects creating a section assignment for a withdrawn enrollment with a 422, not a 500', function () {
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $enrollment->update(['status' => Enrollment::STATUS_WITHDRAWN]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.section_id.0'))->not->toBeEmpty();
});

it('rejects a section outside the enrollment\'s own branch/academic_year/grade_level as a 422, not a 500', function () {
    [$enrollment] = matchingEnrollmentAndSectionForController();
    $unrelatedSection = Section::factory()->create();
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $unrelatedSection->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.section_id.0'))->toContain('do not agree');
});

it('rejects a second active section assignment for the same enrollment with a 422, not a 500', function () {
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    SectionAssignment::factory()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id]);
    $anotherSection = Section::factory()->create([
        'branch_id' => $section->branch_id,
        'academic_year_id' => $section->academic_year_id,
        'grade_level_id' => $section->grade_level_id,
    ]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.section-assignments.store'), [
        'enrollment_id' => $enrollment->id,
        'section_id' => $anotherSection->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.section_id.0'))->not->toBeEmpty();
});

it('closes a section assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $assignment = SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
        'effective_from' => now()->subMonth(),
    ]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.section-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'section_transfer',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('ended')
        ->and($response->json('reason_code'))->toBe('section_transfer')
        ->and($response->json('ended_by_id'))->toBe($user->id)
        ->and($response->json('ended_by_name_en'))->not->toBeEmpty();
});

it('rejects closing a same-day assignment with a 422 attached to effective_until, not reason_code', function () {
    $this->seed(ReasonCodeSeeder::class);
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $assignment = SectionAssignment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'section_id' => $section->id,
        'effective_from' => now(),
    ]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.section-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'section_transfer',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.effective_until.0'))->toContain('DateRange')
        ->and($response->json('errors.reason_code'))->toBeNull();
});

it('rejects closing with an unregistered reason code as a 422 attached to reason_code', function () {
    $this->seed(ReasonCodeSeeder::class);
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $assignment = SectionAssignment::factory()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.section-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'not_a_real_reason',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.reason_code.0'))->not->toBeEmpty();
});

it('cancels a section assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    [$enrollment, $section] = matchingEnrollmentAndSectionForController();
    $assignment = SectionAssignment::factory()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id]);
    $user = userWithCatalogPermissionForSectionAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.section-assignments.cancel', ['id' => $assignment->id]), [
        'reason_code' => 'entered_in_error',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('cancelled')
        ->and($response->json('reason_code'))->toBe('entered_in_error');
});

it('denies section assignment actions for a user without the permission', function () {
    [$enrollment] = matchingEnrollmentAndSectionForController();
    $user = userWithCatalogPermissionForSectionAssignment(false);

    $this->actingAs($user)->getJson(route('academic.section-assignments.index', ['enrollment_id' => $enrollment->id]))->assertForbidden();
});

it('has no update or delete route registered for section assignments', function () {
    $routes = collect(app('router')->getRoutes())->filter(
        fn ($route) => str_contains($route->uri(), 'section-assignments'),
    );

    expect($routes->contains(fn ($route) => in_array('DELETE', $route->methods())))->toBeFalse()
        ->and($routes->contains(fn ($route) => in_array('PUT', $route->methods())))->toBeFalse();
});
