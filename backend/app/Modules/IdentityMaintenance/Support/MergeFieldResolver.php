<?php

namespace App\Modules\IdentityMaintenance\Support;

use App\Modules\People\Models\Person;

/**
 * Field-by-field conflict resolution for the Person aggregate's own
 * columns during a merge (Sprint 3.2) -- deliberately separate from
 * MergeOrchestrationService, which only decides WHEN resolution happens
 * (one step in execute()), never HOW fields are resolved.
 */
interface MergeFieldResolver
{
    /**
     * Returns the field values to apply to $winning after merge, via
     * $winning->update($result). Applied even when, as with the
     * default resolver, the result is identical to $winning's current
     * values -- keeping the code path uniform so a future richer
     * resolver (gap-filling, manual field-by-field selection) plugs in
     * without MergeOrchestrationService changing at all.
     *
     * @return array<string, mixed>
     */
    public function resolve(Person $losing, Person $winning): array;
}
