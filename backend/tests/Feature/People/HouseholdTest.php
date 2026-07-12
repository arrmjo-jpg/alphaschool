<?php

use App\Modules\People\Models\Household;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonRelationship;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('creates a household with a public_id and active by default', function () {
    $household = Household::factory()->create();

    expect(Str::isUlid($household->public_id))->toBeTrue()
        ->and($household->is_active)->toBeTrue();
});

it('deactivates instead of deleting', function () {
    $household = Household::factory()->create();

    $household->update(['is_active' => false]);

    expect(Household::find($household->id))->not->toBeNull()
        ->and($household->fresh()->is_active)->toBeFalse();
});

it('refuses to physically delete a household', function () {
    $household = Household::factory()->create();

    expect(fn () => $household->delete())->toThrow(RuntimeException::class);
    expect(Household::find($household->id))->not->toBeNull();
});

it('gathers people as members, independent of any context role', function () {
    $household = Household::factory()->create();
    $personOne = Person::factory()->create();
    $personTwo = Person::factory()->create();

    $household->members()->attach([$personOne->id, $personTwo->id]);

    expect($household->members()->count())->toBe(2)
        ->and($household->members->pluck('id'))->toContain($personOne->id, $personTwo->id);
});

it('never infers or is inferred from person_relationships', function () {
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    // A real, recorded sibling relationship in the graph...
    PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
    ]);

    // ...has no bearing on household membership: an empty household
    // stays empty, and creating one has no side effect on the graph.
    $household = Household::factory()->create();

    expect($household->members()->count())->toBe(0)
        ->and(PersonRelationship::where('person_id', $personA->id)->count())->toBe(1);
});

it('refuses to force-delete a Person referenced by household membership', function () {
    $household = Household::factory()->create();
    $person = Person::factory()->create();
    $household->members()->attach($person->id);

    expect(fn () => $person->forceDelete())->toThrow(QueryException::class);
});

it('logs changes via activitylog, suppressing empty diffs', function () {
    $household = Household::factory()->create(['name_en' => 'Original']);

    $household->update(['name_en' => 'Renamed']);
    expect($household->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $household->activitiesAsSubject()->count();
    $household->update(['name_en' => 'Renamed']);
    expect($household->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
