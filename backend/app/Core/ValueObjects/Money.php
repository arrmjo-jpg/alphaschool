<?php

namespace App\Core\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A monetary amount, stored as an integer in the currency's smallest unit
 * (minor units -- cents, fils, etc.), never as a float. Floating-point
 * arithmetic on money is a well-known source of rounding bugs (0.1 + 0.2
 * != 0.3) -- integer minor-unit arithmetic avoids the entire class of bug.
 *
 * Currency validation is STRUCTURAL ONLY (3 uppercase letters, ISO 4217
 * shape) -- this class does not hardcode a whitelist of "supported"
 * currencies. Different customer schools use different currencies, and a
 * hardcoded list would repeat the exact mistake corrected in
 * App\Core\ValueObjects\ReasonCode: a Core value object must never bake in
 * business/deployment-specific vocabulary.
 *
 * Rounding: multiply() rounds to the currency's minor-unit precision using
 * round-half-away-from-zero (PHP's default round() mode) -- documented
 * here because Money's rounding behavior must be a stated, tested
 * decision, not an accident of whatever the arithmetic happens to do.
 *
 * Multi-currency ledger mechanics (FX conversion, functional vs.
 * transaction currency) are explicitly out of scope here -- see
 * docs/IMPLEMENTATION_PLAYBOOK.md's Technical Debt Register. add()/
 * subtract() between different currencies is a programming error, not a
 * business rule to encode, so it throws.
 */
final class Money implements Stringable
{
    /**
     * Minor-unit exponents for currencies that are NOT the 2-decimal
     * default (e.g. JOD = 3 decimal places / fils). This is a structural
     * fact about how each currency subdivides, defined by ISO 4217 itself
     * -- not a business decision -- so it belongs here, unlike a
     * "supported currencies" whitelist would.
     */
    private const NON_STANDARD_MINOR_UNIT_EXPONENTS = [
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0,
    ];

    public readonly int $minorUnits;

    public readonly string $currency;

    private function __construct(int $minorUnits, string $currency)
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(sprintf(
                "Money: '%s' is not a structurally valid ISO 4217 currency code (expected 3 uppercase letters).",
                $currency,
            ));
        }

        $this->minorUnits = $minorUnits;
        $this->currency = $currency;
    }

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Accepts a decimal string (e.g. "12.50"), never a float -- a float
     * parameter here would reintroduce the exact precision problem this
     * class exists to avoid before the value even reaches minor units.
     */
    public static function fromDecimalString(string $amount, string $currency): self
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Money: '{$amount}' is not a valid decimal amount string.");
        }

        $exponent = self::minorUnitExponent($currency);
        $minorUnits = (int) round(((float) $amount) * (10 ** $exponent));

        return new self($minorUnits, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Multiplies by a factor (e.g. a percentage as a decimal string like
     * "0.15" for 15%), rounding the result to this currency's minor-unit
     * precision using round-half-away-from-zero.
     */
    public function multiply(string $factor): self
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $factor)) {
            throw new InvalidArgumentException("Money: '{$factor}' is not a valid decimal factor string.");
        }

        $result = (int) round($this->minorUnits * (float) $factor);

        return new self($result, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits < $other->minorUnits;
    }

    public function toDecimalString(): string
    {
        $exponent = self::minorUnitExponent($this->currency);
        if ($exponent === 0) {
            return (string) $this->minorUnits;
        }

        return number_format($this->minorUnits / (10 ** $exponent), $exponent, '.', '');
    }

    public function __toString(): string
    {
        return "{$this->toDecimalString()} {$this->currency}";
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Money: cannot operate on %s and %s -- mismatched currencies require an explicit FX conversion, '.
                'which is out of scope for this value object (see docs/IMPLEMENTATION_PLAYBOOK.md Technical Debt Register).',
                $this->currency,
                $other->currency,
            ));
        }
    }

    private static function minorUnitExponent(string $currency): int
    {
        return self::NON_STANDARD_MINOR_UNIT_EXPONENTS[$currency] ?? 2;
    }
}
