<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Employee;
use Database\Seeders\ReasonCodeSeeder;

/**
 * UI Sprint 2's HomeroomAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §30.6) -- Timeline/Action-shaped, not a thin AcademicMasterDataController
 * adapter: no update/destroy at all, close/cancel are distinct actions.
 */
function userWithCatalogPermissionForHomeroomAssignment(bool $granted, ?Branch $branch = null): User
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

it('lists a section\'s homeroom assignment timeline, most recent first', function () {
    $this->seed(ReasonCodeSeeder::class);
    $section = Section::factory()->create();
    $older = HomeroomAssignment::factory()->create([
        'section_id' => $section->id,
        'effective_from' => now()->subMonths(6),
    ]);
    $older->closeAssignment(new ReasonCode('teacher_reassigned'), now()->subMonths(3));
    $newer = HomeroomAssignment::factory()->create([
        'section_id' => $section->id,
        'effective_from' => now()->subMonths(3),
    ]);
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->getJson(route('academic.homeroom-assignments.index', ['section_id' => $section->id]));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($newer->id)
        ->and($data[1]['id'])->toBe($older->id)
        ->and($data[1]['status'])->toBe('ended')
        ->and($data[1]['reason_code'])->toBe('teacher_reassigned');
});

it('creates a homeroom assignment for an open academic year', function () {
    $academicYear = AcademicYear::factory()->create(['status' => 'active']);
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.homeroom-assignments.store'), [
        'section_id' => $section->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('active')
        ->and($response->json('employee_id'))->toBe($employee->id)
        ->and($response->json('employee_name_en'))->not->toBeEmpty();
    $this->assertDatabaseHas('homeroom_assignments', ['section_id' => $section->id, 'employee_id' => $employee->id]);
});

it('honors a caller-specified effective_from from the Create form (§30.4), not just now()', function () {
    $academicYear = AcademicYear::factory()->create(['status' => 'active']);
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.homeroom-assignments.store'), [
        'section_id' => $section->id,
        'employee_id' => $employee->id,
        'effective_from' => '2026-02-01',
    ]);

    $response->assertCreated();
    expect($response->json('effective_from'))->toBe('2026-02-01');
});

it('rejects creating a homeroom assignment for a closed academic year with a 422, not a 500', function () {
    $academicYear = AcademicYear::factory()->create(['status' => 'closed']);
    $section = Section::factory()->create(['academic_year_id' => $academicYear->id]);
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.homeroom-assignments.store'), [
        'section_id' => $section->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('closed')
        // §30.4: surfaces as an ordinary inline field error via the
        // frontend's mapServerErrors, not just a top-level message --
        // Section is fixed by the route, not a form field on the Create
        // form, so this attaches to employee_id, the nearest real field.
        ->and($response->json('errors.employee_id.0'))->toContain('closed');
});

it('rejects a second active homeroom teacher for the same section with a 422, not a 500', function () {
    $section = Section::factory()->create();
    HomeroomAssignment::factory()->create(['section_id' => $section->id]);
    $employee = Employee::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->postJson(route('academic.homeroom-assignments.store'), [
        'section_id' => $section->id,
        'employee_id' => $employee->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.employee_id.0'))->not->toBeEmpty();
});

it('closes a homeroom assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    // effective_from must predate "today" -- close() defaults
    // effective_until to today, and the half-open range's own invariant
    // (DateRange) rejects an empty [today, today) interval.
    $assignment = HomeroomAssignment::factory()->create(['effective_from' => now()->subMonth()]);
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.homeroom-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'teacher_reassigned',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('ended')
        ->and($response->json('reason_code'))->toBe('teacher_reassigned')
        ->and($response->json('ended_by_id'))->toBe($user->id)
        ->and($response->json('ended_by_name_en'))->not->toBeEmpty();
});

it('rejects closing with a reason code not registered for this context, as a 422', function () {
    $this->seed(ReasonCodeSeeder::class);
    $assignment = HomeroomAssignment::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.homeroom-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'not_a_real_reason',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.reason_code.0'))->not->toBeEmpty();
});

it('rejects closing with a malformed reason code string as a 422, not a 500', function () {
    $assignment = HomeroomAssignment::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.homeroom-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'NOT-VALID',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.reason_code.0'))->not->toBeEmpty();
});

it('rejects closing a same-day assignment with a 422 attached to effective_until, not reason_code (found live)', function () {
    // A real bug found live during UI Sprint 2 verification: closing an
    // assignment created today defaults effective_until to today too,
    // producing an empty [today, today) DateRange -- rejected by the
    // half-open interval's own invariant, not a reason-code problem, so
    // the inline field error must not mislead the user into re-picking
    // the reason.
    $this->seed(ReasonCodeSeeder::class);
    $assignment = HomeroomAssignment::factory()->create(['effective_from' => now()]);
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.homeroom-assignments.close', ['id' => $assignment->id]), [
        'reason_code' => 'teacher_reassigned',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.effective_until.0'))->toContain('DateRange')
        ->and($response->json('errors.reason_code'))->toBeNull();
});

it('cancels a homeroom assignment with a valid reason code', function () {
    $this->seed(ReasonCodeSeeder::class);
    $assignment = HomeroomAssignment::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(true);

    $response = $this->actingAs($user)->patchJson(route('academic.homeroom-assignments.cancel', ['id' => $assignment->id]), [
        'reason_code' => 'entered_in_error',
    ]);

    $response->assertOk();
    expect($response->json('status'))->toBe('cancelled')
        ->and($response->json('reason_code'))->toBe('entered_in_error');
});

it('denies homeroom assignment actions for a user without the permission', function () {
    $section = Section::factory()->create();
    $user = userWithCatalogPermissionForHomeroomAssignment(false);

    $this->actingAs($user)->getJson(route('academic.homeroom-assignments.index', ['section_id' => $section->id]))->assertForbidden();
});

it('has no update or delete route registered for homeroom assignments', function () {
    $routes = collect(app('router')->getRoutes())->filter(
        fn ($route) => str_contains($route->uri(), 'homeroom-assignments'),
    );

    expect($routes->contains(fn ($route) => in_array('DELETE', $route->methods())))->toBeFalse()
        ->and($routes->contains(fn ($route) => in_array('PUT', $route->methods())))->toBeFalse();
});
