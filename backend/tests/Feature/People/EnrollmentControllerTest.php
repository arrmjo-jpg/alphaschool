<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Enrollment;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\Student;

/**
 * UI Sprint 2's SectionAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §31.2) -- SectionAssignment's Landing page search is the first real
 * consumer.
 */
function userWithCatalogPermissionForEnrollmentLookup(bool $granted): User
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

function enrollmentForStudentNamed(string $firstNameEn, int $academicYearId, string $status = 'active'): Enrollment
{
    $person = Person::factory()->create(['first_name_en' => $firstNameEn]);
    $student = Student::factory()->create(['person_id' => $person->id]);

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYearId,
        'status' => $status,
    ]);
}

it('finds an active enrollment by a substring of the student\'s first name, scoped to the given academic year', function () {
    $academicYear = AcademicYear::factory()->create();
    $enrollment = enrollmentForStudentNamed('Zaid', $academicYear->id);
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $response = $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Zai', 'academic_year_id' => $academicYear->id]));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['enrollment_id'])->toBe($enrollment->id)
        ->and($data[0]['student_name_en'])->toContain('Zaid')
        ->and($data[0]['status'])->toBe('active');
});

it('excludes a matching student whose enrollment is not active', function () {
    $academicYear = AcademicYear::factory()->create();
    enrollmentForStudentNamed('Zaid', $academicYear->id, 'withdrawn');
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $response = $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Zai', 'academic_year_id' => $academicYear->id]));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('excludes a matching student enrolled in a different academic year', function () {
    $searchedYear = AcademicYear::factory()->create();
    $otherYear = AcademicYear::factory()->create();
    enrollmentForStudentNamed('Zaid', $otherYear->id);
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $response = $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Zai', 'academic_year_id' => $searchedYear->id]));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('requires at least 2 characters in the search query', function () {
    $academicYear = AcademicYear::factory()->create();
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Z', 'academic_year_id' => $academicYear->id]))
        ->assertUnprocessable();
});

it('requires an academic_year_id', function () {
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Zaid']))->assertUnprocessable();
});

it('shows a single enrollment\'s full context', function () {
    $academicYear = AcademicYear::factory()->create();
    $enrollment = enrollmentForStudentNamed('Layla', $academicYear->id);
    $user = userWithCatalogPermissionForEnrollmentLookup(true);

    $response = $this->actingAs($user)->getJson(route('enrollments.show', ['id' => $enrollment->id]));

    $response->assertOk();
    expect($response->json('enrollment_id'))->toBe($enrollment->id)
        ->and($response->json('student_name_en'))->toContain('Layla')
        ->and($response->json('student_public_id'))->not->toBeEmpty()
        ->and($response->json('branch_id'))->toBe($enrollment->branch_id)
        ->and($response->json('branch_name_en'))->not->toBeEmpty()
        ->and($response->json('academic_year_id'))->toBe($enrollment->academic_year_id)
        ->and($response->json('grade_level_id'))->toBe($enrollment->grade_level_id);
});

it('denies search and show for a user without the permission', function () {
    $academicYear = AcademicYear::factory()->create();
    $enrollment = enrollmentForStudentNamed('Zaid', $academicYear->id);
    $user = userWithCatalogPermissionForEnrollmentLookup(false);

    $this->actingAs($user)->getJson(route('enrollments.search', ['q' => 'Zaid', 'academic_year_id' => $academicYear->id]))->assertForbidden();
    $this->actingAs($user)->getJson(route('enrollments.show', ['id' => $enrollment->id]))->assertForbidden();
});

it('allows a super admin regardless of granted permissions', function () {
    $academicYear = AcademicYear::factory()->create();
    enrollmentForStudentNamed('Zaid', $academicYear->id);
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)->getJson(route('enrollments.search', ['q' => 'Zaid', 'academic_year_id' => $academicYear->id]))->assertOk();
});
