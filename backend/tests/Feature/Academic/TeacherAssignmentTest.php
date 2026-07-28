<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\SubjectOffering;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\People\Models\Employee;
use Database\Seeders\ReasonCodeSeeder;

it('creates a teacher assignment linking an Employee to a SubjectOffering', function () {
    $assignment = TeacherAssignment::factory()->create();

    expect($assignment->status)->toBe('active')
        ->and($assignment->fresh()->employee)->toBeInstanceOf(Employee::class)
        ->and($assignment->fresh()->subjectOffering)->toBeInstanceOf(SubjectOffering::class);
});

/**
 * Single-cardinality assumption (Subject Offering + Timetables
 * Architecture Pass, explicitly flagged) -- mirrors HomeroomAssignment's
 * own "two teachers cannot both be active" shape, scoped to
 * subject_offering_id instead of section_id.
 */
it('rejects a second active teacher for the same SubjectOffering', function () {
    $offering = SubjectOffering::factory()->create();
    TeacherAssignment::factory()->create(['subject_offering_id' => $offering->id]);

    expect(fn () => TeacherAssignment::factory()->create(['subject_offering_id' => $offering->id]))
        ->toThrow(RuntimeException::class);
});

it('allows a teacher reassignment once the prior assignment has been closed', function () {
    $this->seed(ReasonCodeSeeder::class);

    $offering = SubjectOffering::factory()->create();
    $first = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now()->subMonths(3),
    ]);

    $first->closeAssignment(new ReasonCode('teacher_reassigned'), now()->subDay());

    $second = TeacherAssignment::factory()->create([
        'subject_offering_id' => $offering->id,
        'effective_from' => now(),
    ]);

    expect($first->fresh()->status)->toBe('ended')
        ->and($second->status)->toBe('active');
});
