<?php

namespace App\Core\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Bilingual structured name (docs/DOMAIN_BLUEPRINT.md §1/§5), agreed
 * before implementation as first/second/third/family name parts in both
 * Arabic and English -- plain structured parts, never a single
 * concatenated string, since display/formatting/search logic needs each
 * part independently addressable.
 *
 * First and family names are required in both languages; second name
 * (father's name) and third name (grandfather's name) are optional,
 * matching Arabic naming convention where not everyone's records include
 * all four generations of name.
 *
 * This is a pure value object: no persistence, no side effects. A Person
 * model stores these as plain flat columns (never Spatie Translatable --
 * names are transliterations, not translations) and constructs this VO
 * on demand for formatting/comparison/duplicate-detection use.
 */
final class PersonName implements Stringable
{
    public function __construct(
        public readonly string $firstNameEn,
        public readonly string $familyNameEn,
        public readonly string $firstNameAr,
        public readonly string $familyNameAr,
        public readonly ?string $secondNameEn = null,
        public readonly ?string $secondNameAr = null,
        public readonly ?string $thirdNameEn = null,
        public readonly ?string $thirdNameAr = null,
    ) {
        foreach (['firstNameEn' => $firstNameEn, 'familyNameEn' => $familyNameEn, 'firstNameAr' => $firstNameAr, 'familyNameAr' => $familyNameAr] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("PersonName: '{$field}' is required and cannot be empty.");
            }
        }
    }

    public function fullNameEn(): string
    {
        return implode(' ', array_filter([
            $this->firstNameEn, $this->secondNameEn, $this->thirdNameEn, $this->familyNameEn,
        ], fn (?string $part) => $part !== null && trim($part) !== ''));
    }

    public function fullNameAr(): string
    {
        return implode(' ', array_filter([
            $this->firstNameAr, $this->secondNameAr, $this->thirdNameAr, $this->familyNameAr,
        ], fn (?string $part) => $part !== null && trim($part) !== ''));
    }

    public function __toString(): string
    {
        return $this->fullNameEn();
    }
}
