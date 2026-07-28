<?php

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Term;
use Illuminate\Database\QueryException;

it('creates a term scoped to an academic year', function () {
    $term = Term::factory()->create(['name_en' => 'First Term', 'sequence_order' => 1]);

    expect($term->name_en)->toBe('First Term')
        ->and($term->sequence_order)->toBe(1)
        ->and($term->status)->toBe(Term::STATUS_UPCOMING)
        ->and($term->fresh()->academicYear)->toBeInstanceOf(AcademicYear::class);
});

it('rejects a duplicate sequence_order within the same academic year', function () {
    $academicYear = AcademicYear::factory()->create();

    Term::factory()->create(['academic_year_id' => $academicYear->id, 'sequence_order' => 1]);

    expect(fn () => Term::factory()->create(['academic_year_id' => $academicYear->id, 'sequence_order' => 1]))
        ->toThrow(QueryException::class);
});

it('allows the same sequence_order across different academic years -- a Term is per-year, not a permanent catalog', function () {
    $yearOne = AcademicYear::factory()->create();
    $yearTwo = AcademicYear::factory()->create();

    $termYearOne = Term::factory()->create(['academic_year_id' => $yearOne->id, 'sequence_order' => 1]);
    $termYearTwo = Term::factory()->create(['academic_year_id' => $yearTwo->id, 'sequence_order' => 1]);

    expect($termYearOne->id)->not->toBe($termYearTwo->id);
});

it('mirrors AcademicYear\'s exact 3-state lifecycle', function () {
    $term = Term::factory()->create();

    expect($term->isActive())->toBeFalse()
        ->and($term->isClosed())->toBeFalse();

    $term->update(['status' => Term::STATUS_ACTIVE]);
    expect($term->fresh()->isActive())->toBeTrue();

    $term->close();
    expect($term->fresh()->isClosed())->toBeTrue();
});

it('is idempotent when closing an already-closed term', function () {
    $term = Term::factory()->closed()->create();

    $term->close();

    expect($term->fresh()->status)->toBe(Term::STATUS_CLOSED);
});
