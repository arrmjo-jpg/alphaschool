<?php

namespace App\Core\Contracts;

/**
 * Addendum C9's domain-veto contract, distinct from structural-conflict
 * validation: "financial reconciliation incomplete, legal investigation
 * open, active disciplinary case" -- knowledge Identity Maintenance
 * deliberately does not own, since it would require a Foundation module
 * to understand Domain-specific business rules. A module implements
 * this ONLY if it has a real veto to express; most don't, and never
 * implementing it is correct, not an omission (unlike
 * ReassignsIdentityReferences/RedactsPersonalData, this is optional,
 * not scanned/enforced).
 *
 * Zero real implementers as of Sprint 3.2 -- Academic/HR/Finance, the
 * modules that would have real vetoes, don't exist yet. Identity
 * Maintenance's dry-run checks for this via instanceof; absence is
 * "no veto", never a failure.
 */
interface VetoesPersonReassignment
{
    /**
     * Returns null if this module has no objection to $personId being
     * reassigned away, or a human-readable veto reason if it does.
     */
    public function canReassignPerson(int $personId): ?string;
}
