<?php

use App\Core\ValueObjects\ReasonCode;
use App\Modules\Academic\Models\HomeroomAssignment;
use App\Modules\Academic\Models\Section;
use App\Modules\People\Models\Employee;
use Database\Seeders\ReasonCodeSeeder;

it('creates a homeroom assignment linking an Employee to a Section', function () {
    $assignment = HomeroomAssignment::factory()->create();

    expect($assignment->status)->toBe('active')
        ->and($assignment->fresh()->employee)->toBeInstanceOf(Employee::class)
        ->and($assignment->fresh()->section)->toBeInstanceOf(Section::class);
});

/**
 * The trait's own worked example, verbatim (HasTemporalAssignment's
 * class docblock): "two teachers cannot both be the active homeroom
 * teacher of the same section at once."
 */
it('rejects a second active homeroom teacher for the same Section', function () {
    $section = Section::factory()->create();
    HomeroomAssignment::factory()->create(['section_id' => $section->id]);

    expect(fn () => HomeroomAssignment::factory()->create(['section_id' => $section->id]))
        ->toThrow(RuntimeException::class);
});

it('allows a homeroom teacher reassignment once the prior assignment has been closed', function () {
    $this->seed(ReasonCodeSeeder::class);

    $section = Section::factory()->create();
    $first = HomeroomAssignment::factory()->create([
        'section_id' => $section->id,
        'effective_from' => now()->subMonths(3),
    ]);

    $first->closeAssignment(new ReasonCode('teacher_reassigned'), now()->subDay());

    $second = HomeroomAssignment::factory()->create([
        'section_id' => $section->id,
        'effective_from' => now(),
    ]);

    expect($first->fresh()->status)->toBe('ended')
        ->and($second->status)->toBe('active');
});
