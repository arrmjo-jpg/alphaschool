<?php

namespace App\Core\Contracts;

/**
 * Implemented by every module holding a Person reference
 * (docs/DOMAIN_BLUEPRINT.md Addendum C3), so Identity Maintenance
 * (Phase 3) can execute a Person Merge across every module without
 * needing direct knowledge of any module's schema.
 *
 * Deliberately minimal for now -- just the mechanism a real Merge
 * ultimately calls. Addendum C7's refinements (a $dryRun parameter, a
 * companion previewReassignment() method) are explicitly Phase 3 scope
 * (Sprint 3.2, where Merge itself is built) per
 * docs/IMPLEMENTATION_PLAYBOOK.md -- adding them now, with no consumer
 * to validate the shape against, would be prediction rather than
 * promotion (Addendum B1).
 */
interface ReassignsIdentityReferences
{
    /**
     * Reassign every reference this module holds to $oldPersonId over to
     * $newPersonId.
     */
    public function reassignPerson(int $oldPersonId, int $newPersonId): void;
}
