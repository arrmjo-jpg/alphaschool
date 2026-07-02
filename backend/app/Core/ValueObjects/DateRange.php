<?php

namespace App\Core\ValueObjects;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Stringable;

/**
 * A half-open date interval [from, until) -- the shared representation for
 * every effective-dated fact in the system (docs/DOMAIN_BLUEPRINT.md §6,
 * "Effective Dating Pattern").
 *
 * `until` is EXCLUSIVE. A null `until` means the range is still open-ended
 * ("ongoing"). This is the deliberate convention: a period ending on
 * 2026-06-01 and the next one starting on 2026-06-01 are adjacent, not
 * overlapping -- chaining consecutive periods never needs +1/-1 day
 * arithmetic, and there is no ambiguity about which period "owns" a
 * shared boundary day.
 *
 * This class is a pure value object: no database access, no side effects.
 * Validating a code/value against a lookup table (e.g. is this a real,
 * active reason code) is a separate concern -- see ReasonCode and
 * App\Core\Rules\ValidReasonCode.
 */
final class DateRange implements Stringable
{
    public readonly Carbon $from;

    public readonly ?Carbon $until;

    /**
     * @param  Carbon|string  $from
     * @param  Carbon|string|null  $until
     */
    public function __construct($from, $until = null)
    {
        $this->from = Carbon::parse($from)->startOfDay();
        $this->until = $until !== null ? Carbon::parse($until)->startOfDay() : null;

        if ($this->until !== null && $this->until->lessThanOrEqualTo($this->from)) {
            throw new InvalidArgumentException(sprintf(
                "DateRange: 'until' (%s) must be strictly after 'from' (%s). ".
                'A zero-length or backwards range is never valid.',
                $this->until->toDateString(),
                $this->from->toDateString(),
            ));
        }
    }

    public function isOpenEnded(): bool
    {
        return $this->until === null;
    }

    /**
     * Whether $date falls within this range. Since `until` is exclusive,
     * the boundary day itself belongs to whatever period starts there, not
     * to this one.
     */
    public function contains(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $date->greaterThanOrEqualTo($this->from)
            && ($this->until === null || $date->lessThan($this->until));
    }

    /**
     * Whether this range shares any day with $other. Two ranges that only
     * touch at a boundary (one's `until` equals the other's `from`) do NOT
     * overlap -- that is the entire point of the half-open convention.
     */
    public function overlaps(self $other): bool
    {
        $thisStartsBeforeOtherEnds = $other->until === null || $this->from->lessThan($other->until);
        $otherStartsBeforeThisEnds = $this->until === null || $other->from->lessThan($this->until);

        return $thisStartsBeforeOtherEnds && $otherStartsBeforeThisEnds;
    }

    public function equals(self $other): bool
    {
        return $this->from->equalTo($other->from)
            && (($this->until === null && $other->until === null)
                || ($this->until !== null && $other->until !== null && $this->until->equalTo($other->until)));
    }

    public function __toString(): string
    {
        return sprintf(
            '[%s, %s)',
            $this->from->toDateString(),
            $this->until?->toDateString() ?? '∞',
        );
    }
}
