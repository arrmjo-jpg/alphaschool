<?php

namespace App\Core\ValueObjects;

/**
 * The generic input shape App\Core\Services\DuplicateDetectionService
 * scores against -- a name, an optional date-of-birth/nationality, and
 * any known identity documents. Deliberately independent of Person (or
 * any other Eloquent model): the Duplicate-Detection Pattern
 * (docs/DOMAIN_BLUEPRINT.md §6) is "built for Person, reusable wherever
 * else entity resolution matters later (e.g. Vendor records)" -- Core
 * must never import App\Modules\People\Models\Person to stay
 * domain-agnostic, so the calling module adapts its own rows into this
 * shape instead.
 *
 * $subject is an opaque pass-through (Core never inspects it) letting a
 * caller correlate a DuplicateMatchResult back to whatever real record
 * produced it (e.g. a Person's id).
 */
final class DuplicateSignals
{
    /**
     * @param  IdentityDocumentReference[]  $identityDocuments
     */
    public function __construct(
        public readonly PersonName $name,
        public readonly ?string $dob = null,
        public readonly ?string $nationality = null,
        public readonly array $identityDocuments = [],
        public readonly mixed $subject = null,
    ) {}
}
