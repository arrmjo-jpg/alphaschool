<?php

namespace App\Core\Contracts;

/**
 * Implemented by every module holding a Person reference
 * (docs/DOMAIN_BLUEPRINT.md Addendum C3), so Identity Maintenance
 * (Phase 3) can execute a Person Anonymization across every module
 * without needing direct knowledge of any module's schema.
 *
 * Deliberately minimal for now -- see ReassignsIdentityReferences for
 * why the fuller Phase 3 shape (approval gating, sensitivity-aware
 * redaction of attached Media per Addendum B3) is not built here.
 */
interface RedactsPersonalData
{
    /**
     * Redact every personally-identifying field this module holds for
     * the given Person.
     */
    public function anonymizePerson(int $personId): void;
}
