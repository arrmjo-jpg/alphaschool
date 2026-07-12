<?php

use App\Modules\People\Models\Person;
use App\Modules\People\Models\PersonRelationship;
use App\Modules\People\Models\RelationshipType;
use Illuminate\Database\QueryException;

it('creates a directed relationship between two people', function () {
    $relationship = PersonRelationship::factory()->create();

    expect($relationship->person)->toBeInstanceOf(Person::class)
        ->and($relationship->relatedPerson)->toBeInstanceOf(Person::class)
        ->and($relationship->relationshipType)->toBeInstanceOf(RelationshipType::class);
});

it('rejects a relationship from a person to themselves', function () {
    $person = Person::factory()->create();

    expect(fn () => PersonRelationship::factory()->create([
        'person_id' => $person->id,
        'related_person_id' => $person->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a relationship_type that belongs to the guardian_student scope, not person_relationship', function () {
    $guardianStudentType = RelationshipType::factory()->guardianStudent()->create();

    expect(fn () => PersonRelationship::factory()->create([
        'relationship_type_id' => $guardianStudentType->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('is discoverable querying from either side, without a second mirrored row', function () {
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
    ]);

    $fromA = PersonRelationship::where('person_id', $personA->id)->orWhere('related_person_id', $personA->id)->count();
    $fromB = PersonRelationship::where('person_id', $personB->id)->orWhere('related_person_id', $personB->id)->count();

    expect($fromA)->toBe(1)
        ->and($fromB)->toBe(1);
});

it('allows a relationship for a person holding no Employee, Student, or Guardian context at all', function () {
    // A grandfather or emergency contact who never holds any context
    // role in the system -- proving this graph is genuinely Person-
    // level, never gated on a context role existing.
    $grandfather = Person::factory()->create();
    $grandchild = Person::factory()->create();

    $relationship = PersonRelationship::factory()->create([
        'person_id' => $grandfather->id,
        'related_person_id' => $grandchild->id,
    ]);

    expect($relationship->exists)->toBeTrue();
});

it('rejects recording the exact same relationship fact twice', function () {
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();
    $type = RelationshipType::factory()->create();

    PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
        'relationship_type_id' => $type->id,
    ]);

    expect(fn () => PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
        'relationship_type_id' => $type->id,
    ]))->toThrow(QueryException::class);
});

it('allows several different relationship types between the same two people', function () {
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();
    $typeOne = RelationshipType::factory()->create();
    $typeTwo = RelationshipType::factory()->create();

    PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
        'relationship_type_id' => $typeOne->id,
    ]);
    PersonRelationship::factory()->create([
        'person_id' => $personA->id,
        'related_person_id' => $personB->id,
        'relationship_type_id' => $typeTwo->id,
    ]);

    expect(PersonRelationship::where('person_id', $personA->id)->where('related_person_id', $personB->id)->count())->toBe(2);
});

it('represents spouse and former-spouse relationships with no schema change, purely via relationship_type', function () {
    $spouse = RelationshipType::factory()->create(['code' => 'spouse', 'name' => ['en' => 'Spouse', 'ar' => 'زوج/زوجة']]);
    $formerSpouse = RelationshipType::factory()->create(['code' => 'former_spouse', 'name' => ['en' => 'Former Spouse', 'ar' => 'زوج/زوجة سابق']]);

    $married = PersonRelationship::factory()->create(['relationship_type_id' => $spouse->id]);
    $divorced = PersonRelationship::factory()->create(['relationship_type_id' => $formerSpouse->id]);

    expect($married->relationshipType->code)->toBe('spouse')
        ->and($divorced->relationshipType->code)->toBe('former_spouse');
});

it('refuses to force-delete a Person referenced by a person_relationships row', function () {
    $relationship = PersonRelationship::factory()->create();
    $person = $relationship->person;

    expect(fn () => $person->forceDelete())->toThrow(QueryException::class);
});

it('logs relationship changes via activitylog, suppressing empty diffs', function () {
    $typeA = RelationshipType::factory()->create();
    $typeB = RelationshipType::factory()->create();
    $relationship = PersonRelationship::factory()->create(['relationship_type_id' => $typeA->id]);

    $relationship->update(['relationship_type_id' => $typeB->id]); // real change
    expect($relationship->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $relationship->activitiesAsSubject()->count();
    $relationship->update(['relationship_type_id' => $typeB->id]); // no real change
    expect($relationship->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});
