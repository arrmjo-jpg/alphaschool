<?php

namespace App\Core\Contracts;

/**
 * Declares that a model is an owned child entity of another aggregate
 * root, which is solely responsible for reassigning/redacting this
 * model's own Person-shaped reference(s) as part of its own
 * ReassignsIdentityReferences/RedactsPersonalData implementation (e.g.
 * Person::reassignPerson() already cascades to Contact/Address/
 * PersonIdentityDocument directly).
 *
 * Implementing this instead of the two real contracts is a deliberate,
 * audited exemption (docs/DOMAIN_BLUEPRINT.md Addendum C11), not a
 * silent omission -- the schema scanner
 * (tests/Architecture/IdentityMaintenanceSchemaDeclarationTest.php)
 * treats a model implementing this as satisfied without requiring the
 * two real contracts, on the understanding that owningAggregate()
 * already handles the cascade. The scanner does not verify that the
 * named aggregate actually cascades correctly -- that correctness is
 * covered by the owning aggregate's own tests (e.g. PersonTest's
 * reassignPerson() coverage), this contract only records the claim.
 */
interface OwnedByAggregate
{
    /**
     * The aggregate root class responsible for this model's identity
     * reassignment/redaction. Documentation, not enforcement.
     */
    public static function owningAggregate(): string;
}
