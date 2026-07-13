<?php

namespace App\Core\ValueObjects;

/**
 * The read-only output of ReassignsIdentityReferences::previewReassignment()
 * (docs/DOMAIN_BLUEPRINT.md Addendum C7) -- one instance per matching
 * Person-referencing column on the reporting model's own table (a model
 * with two such columns, e.g. PersonRelationship's person_id/
 * related_person_id, returns two). Deliberately structured enough
 * (per-field, per-entity-id) that Identity Maintenance's Merge
 * orchestration can build merge_reassignment_log rows directly from
 * this data before calling the real reassignment, not just display a
 * human-readable count.
 */
final class ReassignmentImpact
{
    /**
     * @param  int[]  $affectedEntityIds
     */
    public function __construct(
        public readonly string $class,
        public readonly string $field,
        public readonly array $affectedEntityIds,
        public readonly string $description,
    ) {}
}
