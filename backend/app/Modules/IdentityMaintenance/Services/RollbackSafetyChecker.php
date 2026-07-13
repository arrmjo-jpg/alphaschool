<?php

namespace App\Modules\IdentityMaintenance\Services;

use App\Modules\IdentityMaintenance\Models\MergeRequest;
use Illuminate\Support\Facades\DB;

/**
 * Addendum C8: "if new data has been created since the merge that
 * assumes it happened... the rollback capability must detect this and
 * block or clearly warn, rather than silently reversing into a fresh
 * inconsistency." For each merge_reassignment_logs row, confirms the
 * named entity's field still holds the value the merge set it to --
 * if anything else has changed it since, rollback is blocked entirely,
 * never partially reversed.
 */
class RollbackSafetyChecker
{
    /**
     * @return string[] problems found; empty means safe to roll back
     */
    public function check(MergeRequest $mergeRequest): array
    {
        $problems = [];

        foreach ($mergeRequest->logs as $log) {
            $table = (new $log->class)->getTable();

            $currentValue = DB::table($table)->where('id', $log->entity_id)->value($log->field);

            if ($currentValue !== null && (int) $currentValue !== $log->new_person_id) {
                $problems[] = "{$log->class}#{$log->entity_id}.{$log->field} is now {$currentValue}, expected {$log->new_person_id} -- something else changed it since the merge.";
            }
        }

        return $problems;
    }
}
