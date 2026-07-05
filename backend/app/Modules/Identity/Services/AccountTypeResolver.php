<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;

/**
 * Account Type is not stored as an enum (docs/DOMAIN_BLUEPRINT.md §8) --
 * it's derived from which context rows (Employee/Student/Guardian) the
 * User's Person holds. A single Person can hold multiple contexts
 * simultaneously (an Employee who is also a Guardian), so this returns
 * every account type that applies, not a single value.
 *
 * Trivial today: Employee/Student/Guardian don't exist yet (Sprint 2.4).
 * Once they do, this method is filled in with the real checks against
 * $user->person -- not redesigned, since the shape (a service returning
 * a list of derived types, never a stored column) is exactly what
 * Sprint 2.4 is meant to build on top of unchanged.
 */
class AccountTypeResolver
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_STUDENT = 'student';

    public const TYPE_GUARDIAN = 'guardian';

    /**
     * @return string[]
     */
    public function resolve(User $user): array
    {
        if ($user->person === null) {
            return [];
        }

        return [];
    }

    public function hasAnyAccountType(User $user): bool
    {
        return $this->resolve($user) !== [];
    }
}
