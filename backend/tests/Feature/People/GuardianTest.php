<?php

use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a guardian referencing a person with a default active status', function () {
    $guardian = Guardian::factory()->create();

    expect($guardian->person_id)->not->toBeNull()
        ->and($guardian->lifecycle_status)->toBe(Guardian::STATUS_ACTIVE)
        ->and(Str::isUlid($guardian->public_id))->toBeTrue();
});

it('rejects a second guardian for the same person', function () {
    $person = Person::factory()->create();
    Guardian::factory()->create(['person_id' => $person->id]);

    Guardian::factory()->create(['person_id' => $person->id]);
})->throws(QueryException::class);

it('soft deletes rather than hard deletes', function () {
    $guardian = Guardian::factory()->create();
    $id = $guardian->id;

    $guardian->delete();

    expect(Guardian::find($id))->toBeNull()
        ->and(Guardian::withTrashed()->find($id))->not->toBeNull();
});

it('reassigns its own person_id when a merge moves it to another person', function () {
    $oldPerson = Person::factory()->create();
    $newPerson = Person::factory()->create();
    $guardian = Guardian::factory()->create(['person_id' => $oldPerson->id]);

    $guardian->reassignPerson($oldPerson->id, $newPerson->id);

    expect($guardian->fresh()->person_id)->toBe($newPerson->id);
});

it('treats anonymizePerson as a no-op, since it holds no personally-identifying field', function () {
    $guardian = Guardian::factory()->create();
    $before = $guardian->fresh()->toArray();

    $guardian->anonymizePerson($guardian->person_id);

    expect($guardian->fresh()->toArray())->toEqual($before);
});

it('logs lifecycle_status changes via activitylog, suppressing empty diffs', function () {
    $guardian = Guardian::factory()->create();

    $guardian->update(['lifecycle_status' => Guardian::STATUS_INACTIVE]);

    expect($guardian->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $guardian->activitiesAsSubject()->count();
    $guardian->update(['lifecycle_status' => Guardian::STATUS_INACTIVE]); // no real change
    expect($guardian->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
