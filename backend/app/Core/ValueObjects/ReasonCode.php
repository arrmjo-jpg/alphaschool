<?php

namespace App\Core\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A structured reason for why a temporal record was closed or cancelled
 * (docs/DOMAIN_BLUEPRINT.md §6). Reason codes exist so "why did this end"
 * is reportable ("how many employees left due to retirement vs
 * resignation this year") instead of free text a report can't group by.
 *
 * This is a PURE value object -- it only validates the string's shape
 * (non-empty, snake_case-like) and never touches the database. Whether a
 * given code is a real, active, registered reason for a given context
 * (e.g. is "promoted" valid for the "enrollment" context) is a separate
 * concern, checked at the point of use via App\Core\Rules\ValidReasonCode
 * or directly against the `reason_codes` table -- keeping this class free
 * of side effects and fast/trivial to construct in tests.
 */
final class ReasonCode implements Stringable
{
    public readonly string $code;

    public function __construct(string $code)
    {
        $code = trim($code);

        if ($code === '') {
            throw new InvalidArgumentException('ReasonCode: code must not be empty.');
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            throw new InvalidArgumentException(sprintf(
                "ReasonCode: '%s' is not a valid code. Codes must be lowercase snake_case ".
                "(e.g. 'promoted', 'branch_transfer') so they stay stable, reportable identifiers ".
                'rather than free text.',
                $code,
            ));
        }

        $this->code = $code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
