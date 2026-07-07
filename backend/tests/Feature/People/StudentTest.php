<?php

use App\Modules\People\Models\Person;
use App\Modules\People\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a student referencing a person with a default active status', function () {
    $student = Student::factory()->create();

    expect($student->person_id)->not->toBeNull()
        ->and($student->lifecycle_status)->toBe(Student::STATUS_ACTIVE)
        ->and(Str::isUlid($student->public_id))->toBeTrue();
});

it('rejects a second student for the same person', function () {
    $person = Person::factory()->create();
    Student::factory()->create(['person_id' => $person->id]);

    Student::factory()->create(['person_id' => $person->id]);
})->throws(QueryException::class);

it('soft deletes rather than hard deletes', function () {
    $student = Student::factory()->create();
    $id = $student->id;

    $student->delete();

    expect(Student::find($id))->toBeNull()
        ->and(Student::withTrashed()->find($id))->not->toBeNull();
});

it('reassigns its own person_id when a merge moves it to another person', function () {
    $oldPerson = Person::factory()->create();
    $newPerson = Person::factory()->create();
    $student = Student::factory()->create(['person_id' => $oldPerson->id]);

    $student->reassignPerson($oldPerson->id, $newPerson->id);

    expect($student->fresh()->person_id)->toBe($newPerson->id);
});

it('treats anonymizePerson as a no-op, since it holds no personally-identifying field', function () {
    $student = Student::factory()->create();
    $before = $student->fresh()->toArray();

    $student->anonymizePerson($student->person_id);

    expect($student->fresh()->toArray())->toEqual($before);
});

it('logs lifecycle_status changes via activitylog, suppressing empty diffs', function () {
    $student = Student::factory()->create();

    $student->update(['lifecycle_status' => Student::STATUS_GRADUATED]);

    expect($student->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $student->activitiesAsSubject()->count();
    $student->update(['lifecycle_status' => Student::STATUS_GRADUATED]); // no real change
    expect($student->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});

it('supports the withdrawn and graduated states via factory helpers', function () {
    expect(Student::factory()->withdrawn()->create()->lifecycle_status)->toBe(Student::STATUS_WITHDRAWN)
        ->and(Student::factory()->graduated()->create()->lifecycle_status)->toBe(Student::STATUS_GRADUATED);
});
