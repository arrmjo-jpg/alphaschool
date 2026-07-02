<?php

namespace App\Core\Rules;

use App\Core\Models\ReasonCode as ReasonCodeModel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a submitted reason code is a real, active, registered
 * reason for a given context -- the DB-backed check that
 * App\Core\ValueObjects\ReasonCode deliberately does not perform itself
 * (see that class's docblock). Use this in FormRequests at the point
 * where a reason code is actually submitted by a user, e.g. when closing
 * an Employment or an Enrollment.
 */
final class ValidReasonCode implements ValidationRule
{
    public function __construct(private readonly string $context) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = ReasonCodeModel::query()
            ->forContext($this->context)
            ->where('code', $value)
            ->exists();

        if (! $exists) {
            $fail("The selected {$attribute} is not a valid reason for '{$this->context}'.");
        }
    }
}
