<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\RelationshipType;
use App\Modules\People\Models\Student;
use Database\Seeders\ReasonCodeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates a guardian_student relationship with a public_id and active status', function () {
    $relationship = GuardianStudent::factory()->create();

    expect(Str::isUlid($relationship->public_id))->toBeTrue()
        ->and($relationship->status)->toBe('active')
        ->and($relationship->fresh()->guardian)->toBeInstanceOf(Guardian::class)
        ->and($relationship->fresh()->student)->toBeInstanceOf(Student::class);
});

it('rejects a relationship_type that belongs to the person_relationship scope, not guardian_student', function () {
    $personRelationshipType = RelationshipType::factory()->create(); // defaults to person_relationship scope

    expect(fn () => GuardianStudent::factory()->create([
        'relationship_type_id' => $personRelationshipType->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an overlapping active period for the same guardian-student pair', function () {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now()->subYear(),
        'effective_until' => null,
    ]);

    expect(fn () => GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now(),
        'effective_until' => null,
    ]))->toThrow(RuntimeException::class);
});

it('allows a new relationship once the prior one has been closed (sequential, non-overlapping)', function () {
    $this->seed(ReasonCodeSeeder::class);

    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    $first = GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now()->subYear(),
    ]);

    $first->closeAssignment(new ReasonCode('custody_change'), now()->subDay());

    $second = GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now(),
    ]);

    expect($first->fresh()->status)->toBe('ended')
        ->and($second->status)->toBe('active');
});

it('closes a relationship via closeAssignment, recording the reason and end date', function () {
    $this->seed(ReasonCodeSeeder::class);

    $relationship = GuardianStudent::factory()->create(['effective_from' => now()->subMonth()]);

    $relationship->closeAssignment(new ReasonCode('student_withdrawn'));

    expect($relationship->status)->toBe('ended')
        ->and($relationship->effective_until)->not->toBeNull()
        ->and($relationship->reason_code_id)->not->toBeNull();
});

it('cancels a relationship entered in error, distinct from an ordinary closure', function () {
    $this->seed(ReasonCodeSeeder::class);

    $relationship = GuardianStudent::factory()->create();

    $relationship->cancelAssignment(new ReasonCode('entered_in_error'));

    expect($relationship->status)->toBe('cancelled');
});

it('leaves verification fields null with no workflow populating them this sprint', function () {
    $relationship = GuardianStudent::factory()->create();

    expect($relationship->verified_by_id)->toBeNull()
        ->and($relationship->verified_at)->toBeNull();
});

it('logs relationship changes via activitylog, suppressing empty diffs', function () {
    $relationship = GuardianStudent::factory()->create(['is_pickup_authorized' => false]);

    $relationship->update(['is_pickup_authorized' => true]); // real change
    expect($relationship->activitiesAsSubject()->count())->toBeGreaterThan(0);

    $countBefore = $relationship->activitiesAsSubject()->count();
    $relationship->update(['is_pickup_authorized' => true]); // no real change
    expect($relationship->fresh()->activitiesAsSubject()->count())->toBe($countBefore);
});

it('retrieves the full history, not just the current period, once a relationship has been closed and reopened', function () {
    $this->seed(ReasonCodeSeeder::class);

    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    $first = GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now()->subYear(),
    ]);
    $first->closeAssignment(new ReasonCode('custody_change'), now()->subDay());

    GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now(),
    ]);

    $fullHistory = GuardianStudent::where('guardian_id', $guardian->id)->where('student_id', $student->id)->get();
    $activeOnly = GuardianStudent::where('guardian_id', $guardian->id)->where('student_id', $student->id)->active()->get();

    expect($fullHistory)->toHaveCount(2)
        ->and($activeOnly)->toHaveCount(1)
        ->and($activeOnly->first()->status)->toBe('active');
});

it('refuses to force-delete a Guardian referenced by a guardian_student row, while an ordinary soft-delete succeeds', function () {
    $relationship = GuardianStudent::factory()->create();
    $guardian = $relationship->guardian;

    $guardian->delete(); // ordinary soft delete -- does not touch the FK at all
    expect(Guardian::find($guardian->id))->toBeNull()
        ->and(Guardian::withTrashed()->find($guardian->id))->not->toBeNull();

    expect(fn () => $guardian->forceDelete())->toThrow(QueryException::class);
});

it('refuses to force-delete a Student referenced by a guardian_student row', function () {
    $relationship = GuardianStudent::factory()->create();
    $student = $relationship->student;

    expect(fn () => $student->forceDelete())->toThrow(QueryException::class);
});

it('refuses at the database level to delete a referenced RelationshipType, independent of its own application-level guard', function () {
    $relationship = GuardianStudent::factory()->create();
    $relationshipTypeId = $relationship->relationship_type_id;

    // Bypasses Eloquent (and RelationshipType's own deleting() guard)
    // entirely, proving the restrictOnDelete() FK is a real, independent
    // second layer -- not merely reachable in theory because the first
    // layer happens to run first.
    expect(fn () => DB::table('relationship_types')->where('id', $relationshipTypeId)->delete())
        ->toThrow(QueryException::class);
});
