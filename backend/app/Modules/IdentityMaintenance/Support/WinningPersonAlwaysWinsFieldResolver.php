<?php

namespace App\Modules\IdentityMaintenance\Support;

use App\Modules\People\Models\Person;

/**
 * Sprint 3.2's default, and only, Merge Strategy: the winning Person's
 * own fields always win, unconditionally. The losing Person's field
 * values are never read here -- discarded, but not destroyed, since
 * MergeRequest.losing_person_snapshot (Addendum C8) preserves them for
 * audit/rollback. No partial/null-coalescing "fill the winning side's
 * empty field from the losing side" logic -- considered and rejected
 * for this sprint as a real richer strategy with no stated business
 * need yet (Addendum B1).
 */
class WinningPersonAlwaysWinsFieldResolver implements MergeFieldResolver
{
    public function resolve(Person $losing, Person $winning): array
    {
        return $winning->only([
            'first_name_en', 'first_name_ar',
            'second_name_en', 'second_name_ar',
            'third_name_en', 'third_name_ar',
            'family_name_en', 'family_name_ar',
            'dob', 'gender', 'nationality',
        ]);
    }
}
