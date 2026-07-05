<?php

namespace App\Core\ValueObjects;

/**
 * The outcome of scoring one candidate against a probe in
 * App\Core\Services\DuplicateDetectionService. $score is 0-100; $tier is
 * one of the service's TIER_* constants. $breakdown exposes the
 * per-signal contribution so a human reviewer (or a test) can see *why*
 * a candidate scored the way it did, not just the total.
 */
final class DuplicateMatchResult
{
    /**
     * @param  array<string, int>  $breakdown
     */
    public function __construct(
        public readonly int $score,
        public readonly string $tier,
        public readonly array $breakdown,
        public readonly mixed $subject = null,
    ) {}

    public function isCertain(): bool
    {
        return $this->tier === 'certain';
    }

    public function isLikely(): bool
    {
        return $this->tier === 'likely';
    }
}
