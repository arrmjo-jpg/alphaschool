<?php

use App\Modules\Academic\Models\Section;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\Term;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

/**
 * UI Sprint 2's TeacherAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §32.3) -- TeacherAssignment's own Landing page (its third cascading
 * picker) and Timeline/Create/Close/Cancel pages are the first real
 * consumers.
 */
function userWithCatalogPermissionForSubjectOffering(bool $granted): User
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

it('lists all subject offerings with no filters, fully resolved', function () {
    $offering = SubjectOffering::factory()->create();
    $user = userWithCatalogPermissionForSubjectOffering(true);

    $response = $this->actingAs($user)->getJson(route('academic.subject-offerings.index'));

    $response->assertOk();
    $data = collect($response->json('data'));
    $match = $data->firstWhere('id', $offering->id);
    expect($match)->not->toBeNull()
        ->and($match['subject_name_en'])->not->toBeEmpty()
        ->and($match['section_name'])->not->toBeEmpty()
        ->and($match['term_name_en'])->not->toBeEmpty()
        ->and($match['academic_year_name_en'])->not->toBeEmpty();
});

it('filters subject offerings by section_id and term_id, deriving only real offerings for the Landing page\'s third picker (§32.2)', function () {
    $matching = SubjectOffering::factory()->create();
    $unrelated = SubjectOffering::factory()->create();
    $user = userWithCatalogPermissionForSubjectOffering(true);

    $response = $this->actingAs($user)->getJson(route('academic.subject-offerings.index', [
        'section_id' => $matching->section_id,
        'term_id' => $matching->term_id,
    ]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($matching->id)
        ->and($ids)->not->toContain($unrelated->id);
});

it('returns an empty list for a section/term combination with no real offering, not an error', function () {
    $section = Section::factory()->create();
    $term = Term::factory()->create();
    $user = userWithCatalogPermissionForSubjectOffering(true);

    $response = $this->actingAs($user)->getJson(route('academic.subject-offerings.index', [
        'section_id' => $section->id,
        'term_id' => $term->id,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('shows a single subject offering\'s full context', function () {
    $offering = SubjectOffering::factory()->create();
    $user = userWithCatalogPermissionForSubjectOffering(true);

    $response = $this->actingAs($user)->getJson(route('academic.subject-offerings.show', ['id' => $offering->id]));

    $response->assertOk();
    expect($response->json('id'))->toBe($offering->id)
        ->and($response->json('subject_id'))->toBe($offering->subject_id)
        ->and($response->json('section_id'))->toBe($offering->section_id)
        ->and($response->json('term_id'))->toBe($offering->term_id)
        ->and($response->json('academic_year_id'))->toBe($offering->academic_year_id)
        ->and($response->json('subject_name_en'))->not->toBeEmpty()
        ->and($response->json('section_name'))->not->toBeEmpty()
        ->and($response->json('term_name_en'))->not->toBeEmpty()
        ->and($response->json('academic_year_name_en'))->not->toBeEmpty();
});

it('denies listing for a user without the permission', function () {
    $user = userWithCatalogPermissionForSubjectOffering(false);

    $this->actingAs($user)->getJson(route('academic.subject-offerings.index'))->assertForbidden();
});

it('allows a super admin regardless of granted permissions', function () {
    SubjectOffering::factory()->create();
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)->getJson(route('academic.subject-offerings.index'))->assertOk();
});

it('has no create/update/delete route registered for subject offerings', function () {
    $routes = collect(app('router')->getRoutes())->filter(
        fn ($route) => str_contains($route->uri(), 'subject-offerings'),
    );

    expect($routes->contains(fn ($route) => in_array('POST', $route->methods())))->toBeFalse()
        ->and($routes->contains(fn ($route) => in_array('PATCH', $route->methods())))->toBeFalse()
        ->and($routes->contains(fn ($route) => in_array('DELETE', $route->methods())))->toBeFalse();
});
