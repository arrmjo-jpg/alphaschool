<?php

use App\Core\Services\DuplicateDetectionService;
use App\Modules\IdentityMaintenance\Models\DuplicateFlag;
use App\Modules\People\Models\Person;
use Illuminate\Support\Str;

it('creates a duplicate flag with a public_id and pending status by default', function () {
    $flag = DuplicateFlag::factory()->create();

    expect(Str::isUlid($flag->public_id))->toBeTrue()
        ->and($flag->status)->toBe(DuplicateFlag::STATUS_PENDING);
});

it('rejects flagging a person as a duplicate of themselves', function () {
    $person = Person::factory()->create();

    expect(fn () => DuplicateFlag::factory()->create([
        'source_person_id' => $person->id,
        'candidate_person_id' => $person->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a non-flaggable tier', function () {
    expect(fn () => DuplicateFlag::factory()->create(['tier' => DuplicateDetectionService::TIER_NONE]))
        ->toThrow(InvalidArgumentException::class);
});

it('soft deletes rather than hard deletes, since this is evidentiary', function () {
    $flag = DuplicateFlag::factory()->create();
    $id = $flag->id;

    $flag->delete();

    expect(DuplicateFlag::find($id))->toBeNull()
        ->and(DuplicateFlag::withTrashed()->find($id))->not->toBeNull();
});

it('reassigns both source and candidate references on a person merge', function () {
    $oldPerson = Person::factory()->create();
    $newPerson = Person::factory()->create();
    $otherPerson = Person::factory()->create();

    $asSource = DuplicateFlag::factory()->create(['source_person_id' => $oldPerson->id, 'candidate_person_id' => $otherPerson->id]);
    $anotherPerson = Person::factory()->create();
    $asCandidate = DuplicateFlag::factory()->create(['source_person_id' => $anotherPerson->id, 'candidate_person_id' => $oldPerson->id]);

    $asSource->reassignPerson($oldPerson->id, $newPerson->id);

    expect($asSource->fresh()->source_person_id)->toBe($newPerson->id)
        ->and($asCandidate->fresh()->candidate_person_id)->toBe($newPerson->id);
});

it('treats anonymizePerson as a no-op, since it holds no personally-identifying field of its own', function () {
    $flag = DuplicateFlag::factory()->create();
    $before = $flag->fresh()->toArray();

    $flag->anonymizePerson($flag->source_person_id);

    expect($flag->fresh()->toArray())->toEqual($before);
});

it('logs resolution changes via activitylog, suppressing empty diffs', function () {
    $flag = DuplicateFlag::factory()->create();

    $flag->update(['status' => DuplicateFlag::STATUS_DISMISSED]);
    expect($flag->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $flag->activitiesAsSubject()->count();
    $flag->update(['status' => DuplicateFlag::STATUS_DISMISSED]);
    expect($flag->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
