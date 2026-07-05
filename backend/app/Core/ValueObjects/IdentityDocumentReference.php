<?php

namespace App\Core\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A composite identity-document reference (docs/DOMAIN_BLUEPRINT.md §5):
 * document_type + issuing_country + number, deliberately not a single
 * flat string. Uniqueness in the real world is scoped to this whole
 * triple, not the number alone -- two different countries or document
 * types can legitimately reuse the same number space.
 *
 * Pure value object: no persistence, no lookup-table validation. Whether
 * a given document_type/issuing_country is a recognized value is a
 * separate concern for whatever validates input at the boundary (the
 * same division of labor already established for ReasonCode: structural
 * shape here, DB-backed/Rule validation elsewhere).
 */
final class IdentityDocumentReference implements Stringable
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $issuingCountry,
        public readonly string $number,
    ) {
        foreach (['documentType' => $documentType, 'issuingCountry' => $issuingCountry, 'number' => $number] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("IdentityDocumentReference: '{$field}' is required and cannot be empty.");
            }
        }
    }

    public function equals(self $other): bool
    {
        return $this->documentType === $other->documentType
            && $this->issuingCountry === $other->issuingCountry
            && $this->number === $other->number;
    }

    public function __toString(): string
    {
        return "{$this->documentType}/{$this->issuingCountry}/{$this->number}";
    }
}
