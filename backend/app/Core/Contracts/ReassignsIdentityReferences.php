<?php

namespace App\Core\Contracts;

use App\Core\ValueObjects\ReassignmentImpact;

/**
 * Implemented by every module holding a Person reference
 * (docs/DOMAIN_BLUEPRINT.md Addendum C3), so Identity Maintenance
 * (Phase 3) can execute a Person Merge across every module without
 * needing direct knowledge of any module's schema.
 *
 * Addendum C7's refinements, added in Sprint 3.2 (Merge itself), now
 * that a real consumer exists to validate the shape against:
 * - previewReassignment(): a read-only companion, one ReassignmentImpact
 *   per matching column, informational only, no validation.
 * - reassignPerson()'s $dryRun parameter: when true, the implementer
 *   performs full structural-validity self-checks (Addendum C9 -- "is
 *   the combined identity structurally valid", e.g. would this update
 *   violate my own unique constraint) WITHOUT writing anything or
 *   dispatching events -- an explicit code path, never "run the real
 *   write in a transaction and roll it back" (events dispatched
 *   mid-write aren't undone by a DB rollback, per C7's own reasoning).
 */
interface ReassignsIdentityReferences
{
    /**
     * Reassign every reference this module holds to $oldPersonId over to
     * $newPersonId. When $dryRun is true, validates only -- no write, no
     * event dispatch -- throwing if the reassignment would be
     * structurally invalid for this specific module (e.g. a unique
     * constraint the winning Person would violate).
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId, bool $dryRun = false): void;

    /**
     * @return ReassignmentImpact[]
     */
    public function previewReassignment(int $oldPersonId, int $newPersonId): array;
}
