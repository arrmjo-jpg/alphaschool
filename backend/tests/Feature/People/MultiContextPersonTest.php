<?php

use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\Student;

/**
 * Proves the exact scenario Person-as-substrate exists to support
 * (ADR-0001): a single physical person can hold more than one context
 * simultaneously without duplicate identity records. This is the
 * sprint's headline Definition of Done item, not a theoretical claim --
 * see docs/DOMAIN_BLUEPRINT.md §3/§8.
 */
it('lets one person simultaneously hold Employee and Guardian contexts', function () {
    $person = Person::factory()->create();

    $employee = Employee::factory()->create(['person_id' => $person->id]);
    $guardian = Guardian::factory()->create(['person_id' => $person->id]);

    expect($employee->person_id)->toBe($person->id)
        ->and($guardian->person_id)->toBe($person->id)
        ->and(Employee::where('person_id', $person->id)->count())->toBe(1)
        ->and(Guardian::where('person_id', $person->id)->count())->toBe(1);
});

it('lets one person simultaneously hold Employee and Student contexts', function () {
    $person = Person::factory()->create();

    $employee = Employee::factory()->create(['person_id' => $person->id]);
    $student = Student::factory()->create(['person_id' => $person->id]);

    expect($employee->person_id)->toBe($person->id)
        ->and($student->person_id)->toBe($person->id);
});

it('lets one person simultaneously hold all three contexts at once', function () {
    $person = Person::factory()->create();

    $employee = Employee::factory()->create(['person_id' => $person->id]);
    $student = Student::factory()->create(['person_id' => $person->id]);
    $guardian = Guardian::factory()->create(['person_id' => $person->id]);

    expect($employee->person_id)->toBe($person->id)
        ->and($student->person_id)->toBe($person->id)
        ->and($guardian->person_id)->toBe($person->id);
});

it('keeps each context lifecycle independent -- changing one never affects the others', function () {
    $person = Person::factory()->create();
    $employee = Employee::factory()->create(['person_id' => $person->id]);
    $guardian = Guardian::factory()->create(['person_id' => $person->id]);

    $employee->update(['lifecycle_status' => Employee::STATUS_INACTIVE]);

    expect($employee->fresh()->lifecycle_status)->toBe(Employee::STATUS_INACTIVE)
        ->and($guardian->fresh()->lifecycle_status)->toBe(Guardian::STATUS_ACTIVE);

    $employee->delete();

    expect(Employee::find($employee->id))->toBeNull()
        ->and(Guardian::find($guardian->id))->not->toBeNull()
        ->and(Person::find($person->id))->not->toBeNull();
});
