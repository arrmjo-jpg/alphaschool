<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Models\Term;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Employee;
use Database\Seeders\ReasonCodeSeeder;

/**
 * UI Sprint 2's TeacherAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §32.6/§32.8) -- mirrors HomeroomAssignmentControllerTest.php's own
 * coverage shape exactly, anchored on SubjectOffering instead of
 * Section, same structural relationship (anchor fixed by the route,
 * Employee is the real form field).
 */
function userWithCatalogPermissionForTeacherAssignment(bool $granted): User
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

function subjectOfferingForController(string $academicYearStatus = 'active'): SubjectOffering
{
    $academicYear = AcademicYear::factory()->create(['status' => $academicYearStatus]);

    return SubjectOffering::factory()->create([
        'section_id' => Section::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'term_id' => Term::factory()->create(['academic_year_id' => $academicYear->id])->id,
        'academic_year_id' => $academicYear->id,
    ]);
}

it('lists a subject offering\'s teacher assignment timeline, most recent first', function () {
    $this->seed(ReasonCodeSeeder::class);
    $offering = subjectOfferingForController();
    $older = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now()->subMonths(6),
    ]);
    $older->closeAssignment(new ReasonCode('teacher_reassigned'), now()->subMonths(3));
    $newer = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now()->subMonths(3),
    ]);

    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->getJson(route('academic.teacher-assignments.index', ['subject_offering_id' => $offering->id]));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($newer->id)
        ->and($data[1]['id'])->toBe($older->id)
        ->and($data[1]['status'])->toBe('ended')
        ->and($data[1]['reason_code'])->toBe('teacher_reassigned');
});

it('creates a teacher assignment for an open academic year', function () {
    $offering = subjectOfferingForController();
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.teacher-assignments.store'), [
        'subject_offering_id' => $offering->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('active')
        ->and($response->json('employee_id'))->toBe($employee->id)
        ->and($response->json('employee_name_en'))->not->toBeEmpty();
    $this->assertDatabaseHas('teacher_assignments', ['subject_offering_id' => $offering->id, 'employee_id' => $employee->id]);
});

it('honors a caller-specified effective_from from the Create form', function () {
    $offering = subjectOfferingForController();
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.teacher-assignments.store'), [
        'subject_offering_id' => $offering->id,
        'employee_id' => $employee->id,
        'effective_from' => '2026-02-01',
    ]);

    $response->assertCreated();
    expect($response->json('effective_from'))->toBe('2026-02-01');
});

it('rejects creating a teacher assignment for a closed academic year with a 422 attached to employee_id, not a 500', function () {
    $offering = subjectOfferingForController('closed');
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.teacher-assignments.store'), [
        'subject_offering_id' => $offering->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.employee_id.0'))->toContain('closed');
});

it('rejects a second active teacher for the same subject offering with a 422, not a 500', function () {
    $offering = subjectOfferingForController();
    TeacherAssignment::factory()->create(['subject_offering_id' => $offering->id]);
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.teacher-assignments.store'), [
        'subject_offering_id' => $offering->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.employee_id.0'))->not->toBeEmpty();
});

it('closes a teacher assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    $offering = subjectOfferingForController();
    $assignment = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now()->subMonth(),
    ]);
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.teacher-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'teacher_reassigned',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('ended')
        ->and($response->json('reason_code'))->toBe('teacher_reassigned')
        ->and($response->json('ended_by_id'))->toBe($user->id)
        ->and($response->json('ended_by_name_en'))->not->toBeEmpty();
});

it('rejects closing a same-day assignment with a 422 attached to effective_until, not reason_code', function () {
    $this->seed(ReasonCodeSeeder::class);
    $offering = subjectOfferingForController();
    $assignment = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now(),
    ]);
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.teacher-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'teacher_reassigned',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.effective_until.0'))->toContain('DateRange')
        ->and($response->json('errors.reason_code'))->toBeNull();
});

it('rejects closing with an unregistered reason code as a 422 attached to reason_code', function () {
    $this->seed(ReasonCodeSeeder::class);
    $offering = subjectOfferingForController();
    $assignment = TeacherAssignment::factory()->create(['subject_offering_id' => $offering->id]);
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.teacher-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'not_a_real_reason',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.reason_code.0'))->not->toBeEmpty();
});

it('cancels a teacher assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    $offering = subjectOfferingForController();
    $assignment = TeacherAssignment::factory()->create(['subject_offering_id' => $offering->id]);
    $user = userWithCatalogPermissionForTeacherAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.teacher-assignments.cancel', ['id' => $assignment->id]), [
        'reason_code' => 'entered_in_error',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('cancelled')
        ->and($response->json('reason_code'))->toBe('entered_in_error');
});

it('denies teacher assignment actions for a user without the permission', function () {
    $offering = subjectOfferingForController();
    $user = userWithCatalogPermissionForTeacherAssignment(false);

    $this->actingAs($user)->getJson(route('academic.teacher-assignments.index', ['subject_offering_id' => $offering->id]))->assertForbidden();
});

it('has no update or delete route registered for teacher assignments', function () {
    $routes = collect(app('router')->getRoutes())->filter(
        fn ($route) => str_contains($route->uri(), 'teacher-assignments'),
    );

    expect($routes->contains(fn ($route) => in_array('DELETE', $route->methods())))->toBeFalse()
        ->and($routes->contains(fn ($route) => in_array('PUT', $route->methods())))->toBeFalse();
});
