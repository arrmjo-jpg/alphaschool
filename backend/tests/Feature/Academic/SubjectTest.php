<?php

use App\Modules\Academic\Models\Subject;
use Illuminate\Database\QueryException;

it('creates a subject in the global catalog', function () {
    $subject = Subject::factory()->create(['code' => 'MATH-101', 'name_en' => 'Mathematics']);

    expect($subject->code)->toBe('MATH-101')
        ->and($subject->name_en)->toBe('Mathematics')
        ->and($subject->is_active)->toBeTrue();
});

it('rejects a duplicate subject code', function () {
    Subject::factory()->create(['code' => 'MATH-101']);

    expect(fn () => Subject::factory()->create(['code' => 'MATH-101']))
        ->toThrow(QueryException::class);
});

it('scopes to active subjects only', function () {
    Subject::factory()->create(['is_active' => true]);
    Subject::factory()->inactive()->create();

    expect(Subject::active()->count())->toBe(1);
});

it('deactivates instead of requiring deletion, matching GradeLevel\'s own reference-entity convention', function () {
    $subject = Subject::factory()->create();

    $subject->update(['is_active' => false]);

    expect($subject->fresh()->is_active)->toBeFalse();
});
