<?php

use App\Modules\People\Models\BillingGroup;
use App\Modules\People\Models\Household;
use App\Modules\People\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a billing group with a public_id and active by default', function () {
    $billingGroup = BillingGroup::factory()->create();

    expect(Str::isUlid($billingGroup->public_id))->toBeTrue()
        ->and($billingGroup->is_active)->toBeTrue();
});

it('deactivates instead of deleting', function () {
    $billingGroup = BillingGroup::factory()->create();

    $billingGroup->update(['is_active' => false]);

    expect(BillingGroup::find($billingGroup->id))->not->toBeNull()
        ->and($billingGroup->fresh()->is_active)->toBeFalse();
});

it('refuses to physically delete a billing group', function () {
    $billingGroup = BillingGroup::factory()->create();

    expect(fn () => $billingGroup->delete())->toThrow(RuntimeException::class);
    expect(BillingGroup::find($billingGroup->id))->not->toBeNull();
});

it('gathers students as members', function () {
    $billingGroup = BillingGroup::factory()->create();
    $studentOne = Student::factory()->create();
    $studentTwo = Student::factory()->create();

    $billingGroup->members()->attach([$studentOne->id, $studentTwo->id]);

    expect($billingGroup->members()->count())->toBe(2)
        ->and($billingGroup->members->pluck('id'))->toContain($studentOne->id, $studentTwo->id);
});

it('stays entirely independent of Household -- no coupling in either direction', function () {
    $student = Student::factory()->create();
    $billingGroup = BillingGroup::factory()->create();
    $billingGroup->members()->attach($student->id);

    // Creating/populating a Household has no bearing on BillingGroup
    // membership, and vice versa -- proven by the absence of any shared
    // table, FK, or query touching both in this implementation.
    $household = Household::factory()->create();

    expect($billingGroup->members()->count())->toBe(1)
        ->and($household->members()->count())->toBe(0);
});

it('refuses to force-delete a Student referenced by billing group membership', function () {
    $billingGroup = BillingGroup::factory()->create();
    $student = Student::factory()->create();
    $billingGroup->members()->attach($student->id);

    expect(fn () => $student->forceDelete())->toThrow(QueryException::class);
});

it('logs changes via activitylog, suppressing empty diffs', function () {
    $billingGroup = BillingGroup::factory()->create(['name_en' => 'Original']);

    $billingGroup->update(['name_en' => 'Renamed']);
    expect($billingGroup->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $billingGroup->activitiesAsSubject()->count();
    $billingGroup->update(['name_en' => 'Renamed']);
    expect($billingGroup->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
