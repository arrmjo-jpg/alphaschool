<?php

use App\Modules\Academic\Exceptions\ClosedTermException;
use App\Modules\Academic\Models\Term;
use App\Modules\Academic\Support\AcademicPeriodGuard;

/**
 * Term's own sibling to AcademicYearClosedGuardTest -- AcademicPeriodGuard
 * became a shared guard once Term became its second real consumer
 * (Subject Offering + Timetables Architecture Pass, BUS-0032's own
 * recorded preference).
 */
it('rejects a closed Term and accepts an open one', function () {
    $closedTerm = Term::factory()->closed()->create();
    $activeTerm = Term::factory()->active()->create();
    $upcomingTerm = Term::factory()->create();

    $guard = app(AcademicPeriodGuard::class);

    expect(fn () => $guard->assertTermIsOpen($closedTerm->id))
        ->toThrow(ClosedTermException::class);

    expect(fn () => $guard->assertTermIsOpen($activeTerm->id))->not->toThrow(Exception::class);
    expect(fn () => $guard->assertTermIsOpen($upcomingTerm->id))->not->toThrow(Exception::class);
});
