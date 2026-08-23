<?php

use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 Sprint A0 -- proves HasTemporalAssignment's own
 * setEffectiveFromAttribute()/setEffectiveUntilAttribute() mutators
 * (moved here from GuardianStudent's local copy, which is now removed)
 * still normalize correctly after the move -- a refactor-regression
 * proof, not a proof that GuardianStudent was previously broken (it
 * wasn't; its own local mutator already handled this identically).
 * The value of centralizing it is that SectionAssignment/
 * HomeroomAssignment (Phase 5) and every future HasTemporalAssignment
 * consumer inherit this for free, with nothing left to independently
 * rediscover.
 */
it('normalizes effective_from/effective_until to day boundaries at the raw storage level, not just on display', function () {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    $relationship = GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now(), // a real timestamp, not midnight
        'effective_until' => null,
    ]);

    // Read the raw stored value directly via the query builder, bypassing
    // Eloquent's own 'date' cast/accessor entirely -- this is what
    // actually proves the mutator normalized the value at write time,
    // not merely that the accessor truncates it for display.
    $rawEffectiveFrom = DB::table('guardian_student')->where('id', $relationship->id)->value('effective_from');

    // Compared via Carbon, not an exact string match -- Eloquent's own
    // 'date' cast serializes a startOfDay() Carbon instance with a
    // "00:00:00" time suffix under this test suite's SQLite connection;
    // what matters is that the stored value parses to midnight on the
    // correct day, not the exact on-disk string format.
    expect(Carbon::parse($rawEffectiveFrom)->equalTo(now()->startOfDay()))->toBeTrue();
});

it('includes a same-day, post-midnight-created row in active()/asOf(today()), per the backlog\'s own proof standard', function () {
    $guardian = Guardian::factory()->create();
    $student = Student::factory()->create();

    $relationship = GuardianStudent::factory()->create([
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'effective_from' => now(), // real current time, potentially long after midnight
        'effective_until' => null,
    ]);

    expect(GuardianStudent::query()->whereKey($relationship->id)->active()->exists())->toBeTrue()
        ->and(GuardianStudent::query()->whereKey($relationship->id)->asOf(now())->exists())->toBeTrue();
});
