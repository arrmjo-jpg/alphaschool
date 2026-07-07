<?php

use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates an employee referencing a person with a default active status', function () {
    $employee = Employee::factory()->create();

    expect($employee->person_id)->not->toBeNull()
        ->and($employee->lifecycle_status)->toBe(Employee::STATUS_ACTIVE)
        ->and(Str::isUlid($employee->public_id))->toBeTrue();
});

it('rejects a second employee for the same person', function () {
    $person = Person::factory()->create();
    Employee::factory()->create(['person_id' => $person->id]);

    Employee::factory()->create(['person_id' => $person->id]);
})->throws(QueryException::class);

it('soft deletes rather than hard deletes', function () {
    $employee = Employee::factory()->create();
    $id = $employee->id;

    $employee->delete();

    expect(Employee::find($id))->toBeNull()
        ->and(Employee::withTrashed()->find($id))->not->toBeNull();
});

it('reassigns its own person_id when a merge moves it to another person', function () {
    $oldPerson = Person::factory()->create();
    $newPerson = Person::factory()->create();
    $employee = Employee::factory()->create(['person_id' => $oldPerson->id]);

    $employee->reassignPerson($oldPerson->id, $newPerson->id);

    expect($employee->fresh()->person_id)->toBe($newPerson->id);
});

it('treats anonymizePerson as a no-op, since it holds no personally-identifying field', function () {
    $employee = Employee::factory()->create();
    $before = $employee->fresh()->toArray();

    $employee->anonymizePerson($employee->person_id);

    expect($employee->fresh()->toArray())->toEqual($before);
});

it('logs lifecycle_status changes via activitylog, suppressing empty diffs', function () {
    $employee = Employee::factory()->create();

    $employee->update(['lifecycle_status' => Employee::STATUS_INACTIVE]);

    expect($employee->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $employee->activitiesAsSubject()->count();
    $employee->update(['lifecycle_status' => Employee::STATUS_INACTIVE]); // no real change
    expect($employee->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
