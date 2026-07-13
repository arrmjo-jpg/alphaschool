<?php

namespace App\Modules\IdentityMaintenance\Models;

use App\Core\Contracts\OwnedByAggregate;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per reference actually reassigned during a Merge (Addendum
 * C8) -- the permanent record rollback reads and reverses. Written
 * exclusively by MergeOrchestrationService::execute(), immediately
 * before the real reassignment on each affected row, using the same
 * data ReassignmentImpact already computed during preview -- never
 * written by an individual module (ADR-0009: logging ownership belongs
 * to Identity Maintenance, not the module being reassigned).
 *
 * Append-only. No update path exists on this model on purpose; a
 * rollback reads these rows to reverse a merge, it never edits them.
 *
 * OwnedByAggregate, not ReassignsIdentityReferences/RedactsPersonalData
 * directly: old_person_id/new_person_id are historical facts owned by
 * MergeRequest's own immutability policy (see that class's docblock) --
 * a later merge must never rewrite what this row says happened.
 */
class MergeReassignmentLog extends Model implements OwnedByAggregate
{
    // Default timestamps (both columns exist in the migration) --
    // created_at is set once on insert; nothing ever calls ->update()
    // on this model, so updated_at simply mirrors it in practice.
    protected $fillable = [
        'merge_request_id',
        'class',
        'field',
        'entity_id',
        'old_person_id',
        'new_person_id',
    ];

    public function mergeRequest(): BelongsTo
    {
        return $this->belongsTo(MergeRequest::class);
    }

    public function oldPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'old_person_id');
    }

    public function newPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'new_person_id');
    }

    public static function owningAggregate(): string
    {
        return MergeRequest::class;
    }
}
