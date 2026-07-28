<?php

use App\Modules\Academic\Events\AcademicYearClosed;
use App\Modules\Academic\Exceptions\ClosedAcademicYearException;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Support\AcademicPeriodGuard;

/**
 * Sprint 4.1's own named Definition of Done: "attempting to create a
 * record scoped to a closed Academic Year is rejected by a policy
 * check, proven by a test." ClosedAcademicYearGuard was renamed/
 * generalized to AcademicPeriodGuard once Term became its second real
 * consumer (Subject Offering + Timetables Architecture Pass) -- see
 * AcademicPeriodGuardTermTest for Term's own sibling coverage.
 */
it('rejects a closed Academic Year and accepts an open one', function () {
    $closedYear = AcademicYear::factory()->closed()->create();
    $activeYear = AcademicYear::factory()->active()->create();
    $upcomingYear = AcademicYear::factory()->create();

    $guard = app(AcademicPeriodGuard::class);

    expect(fn () => $guard->assertAcademicYearIsOpen($closedYear->id))
        ->toThrow(ClosedAcademicYearException::class);

    expect(fn () => $guard->assertAcademicYearIsOpen($activeYear->id))->not->toThrow(Exception::class);
    expect(fn () => $guard->assertAcademicYearIsOpen($upcomingYear->id))->not->toThrow(Exception::class);
});

it('dispatches AcademicYearClosed exactly once on a real transition, and is idempotent on a repeat close', function () {
    Event::fake([AcademicYearClosed::class]);

    $academicYear = AcademicYear::factory()->active()->create();

    $academicYear->close();
    $academicYear->close();

    expect($academicYear->fresh()->status)->toBe(AcademicYear::STATUS_CLOSED);
    Event::assertDispatchedTimes(AcademicYearClosed::class, 1);
});

it('does not dispatch AcademicYearClosed when closing an already-closed year', function () {
    Event::fake([AcademicYearClosed::class]);

    $academicYear = AcademicYear::factory()->closed()->create();

    $academicYear->close();

    Event::assertNotDispatched(AcademicYearClosed::class);
});
