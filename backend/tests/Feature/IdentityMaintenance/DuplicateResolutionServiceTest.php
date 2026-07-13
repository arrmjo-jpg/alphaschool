<?php

use App\Core\Services\DuplicateDetectionService;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PermissionGroup;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\IdentityMaintenance\Services\DuplicateResolutionService;
use App\Modules\People\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;

function seedReviewDuplicatesPermission(): Permission
{
    $group = PermissionGroup::firstOrCreate(['code' => 'identity-governance'], ['name' => ['en' => 'Identity Governance', 'ar' => 'حوكمة الهوية']]);

    return Permission::firstOrCreate(
        ['name' => 'identity.review-duplicates', 'guard_name' => 'sanctum'],
        ['permission_group_id' => $group->id, 'display_name' => ['en' => 'Review Duplicate Persons', 'ar' => 'مراجعة الأشخاص المكررين']],
    );
}

function reviewerUser(): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create();

    withTeam($branch->id);
    $permission = seedReviewDuplicatesPermission();
    $role = Role::create(['name' => 'duplicate-reviewer', 'guard_name' => 'sanctum', 'branch_id' => null]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    return $user->fresh();
}

it('finds a real duplicate candidate via search_key narrowing and real scoring', function () {
    $probe = Person::factory()->create(['first_name_en' => 'Mohammed', 'family_name_en' => 'Al-Rashid', 'dob' => '2015-03-10', 'nationality' => 'JO']);
    $candidate = Person::factory()->create(['first_name_en' => 'Mohammed', 'family_name_en' => 'Al-Rashid', 'dob' => '2015-03-10', 'nationality' => 'JO']);
    Person::factory()->create(['first_name_en' => 'Zainab', 'family_name_en' => 'Otherfamily']); // unrelated, must not appear

    $matches = app(DuplicateResolutionService::class)->scanForCandidates($probe->fresh());

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->subject->id)->toBe($candidate->id)
        ->and($matches[0]->tier)->toBe(DuplicateDetectionService::TIER_LIKELY);
});

it('flags candidates found by a scan, persisting one DuplicateFlag per match', function () {
    $probe = Person::factory()->create(['first_name_en' => 'Sara', 'family_name_en' => 'Hamdan', 'dob' => '2010-01-01', 'nationality' => 'JO']);
    $candidate = Person::factory()->create(['first_name_en' => 'Sara', 'family_name_en' => 'Hamdan', 'dob' => '2010-01-01', 'nationality' => 'JO']);

    $service = app(DuplicateResolutionService::class);
    $matches = $service->scanForCandidates($probe->fresh());
    $flags = $service->flagCandidates($probe->fresh(), $matches);

    expect($flags)->toHaveCount(1)
        ->and($flags[0])->toBeInstanceOf(DuplicateFlag::class)
        ->and($flags[0]->source_person_id)->toBe($probe->id)
        ->and($flags[0]->candidate_person_id)->toBe($candidate->id)
        ->and($flags[0]->status)->toBe(DuplicateFlag::STATUS_PENDING);
});

it('is idempotent -- flagging the same scan results twice does not throw or duplicate rows', function () {
    $probe = Person::factory()->create(['first_name_en' => 'Yousef', 'family_name_en' => 'Nasser', 'dob' => '2012-05-05', 'nationality' => 'JO']);
    Person::factory()->create(['first_name_en' => 'Yousef', 'family_name_en' => 'Nasser', 'dob' => '2012-05-05', 'nationality' => 'JO']);

    $service = app(DuplicateResolutionService::class);
    $matches = $service->scanForCandidates($probe->fresh());

    $service->flagCandidates($probe->fresh(), $matches);
    $service->flagCandidates($probe->fresh(), $matches); // second scan, same result

    expect(DuplicateFlag::where('source_person_id', $probe->id)->count())->toBe(1);
});

it('resolves a flag as a merge candidate, recording the reviewer and notes', function () {
    $flag = DuplicateFlag::factory()->create();
    $reviewer = reviewerUser();

    $resolved = app(DuplicateResolutionService::class)->resolveAsMergeCandidate($flag, $reviewer, 'Looks like the same student.');

    expect($resolved->status)->toBe(DuplicateFlag::STATUS_MERGE_CANDIDATE)
        ->and($resolved->resolved_by_id)->toBe($reviewer->id)
        ->and($resolved->resolved_at)->not->toBeNull()
        ->and($resolved->resolution_notes)->toBe('Looks like the same student.');
});

it('dismisses a flag, recording the reviewer', function () {
    $flag = DuplicateFlag::factory()->create();
    $reviewer = reviewerUser();

    $resolved = app(DuplicateResolutionService::class)->dismiss($flag, $reviewer, 'Twins, not duplicates.');

    expect($resolved->status)->toBe(DuplicateFlag::STATUS_DISMISSED)
        ->and($resolved->resolved_by_id)->toBe($reviewer->id);
});

it('rejects resolving a flag without the identity.review-duplicates permission', function () {
    seedReviewDuplicatesPermission(); // exists system-wide, just not granted to this user
    $flag = DuplicateFlag::factory()->create();
    $unprivilegedUser = User::factory()->create();

    expect(fn () => app(DuplicateResolutionService::class)->resolveAsMergeCandidate($flag, $unprivilegedUser))
        ->toThrow(AuthorizationException::class);
});

it('rejects dismissing a flag without the identity.review-duplicates permission', function () {
    seedReviewDuplicatesPermission();
    $flag = DuplicateFlag::factory()->create();
    $unprivilegedUser = User::factory()->create();

    expect(fn () => app(DuplicateResolutionService::class)->dismiss($flag, $unprivilegedUser))
        ->toThrow(AuthorizationException::class);
});
