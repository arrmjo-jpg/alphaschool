<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Student;

/**
 * Account Type is not stored as an enum (docs/DOMAIN_BLUEPRINT.md §8) --
 * it's derived from which context rows (Employee/Student/Guardian) the
 * User's Person holds. A single Person can hold multiple contexts
 * simultaneously (an Employee who is also a Guardian), so this returns
 * every account type that applies, not a single value.
 *
 * Filled in now that Employee/Student/Guardian exist (Sprint 2.4) --
 * not redesigned, since the shape (a service returning a list of
 * derived types, never a stored column) is exactly what Sprint 2.2
 * built this to be extended into. Each check is a plain, unmemoized
 * existence query -- no caching, no optimization; if resolving this
 * repeatedly per request ever becomes a real cost, that is a future,
 * separately-justified decision, not one made here speculatively.
 *
 * Adding a future account type (e.g. Applicant) is one more `exists()`
 * check and one more constant here -- nothing about User, Person, or
 * this class's public shape needs to change.
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

        $personId = $user->person->id;
        $types = [];

        if (Employee::where('person_id', $personId)->exists()) {
            $types[] = self::TYPE_EMPLOYEE;
        }

        if (Student::where('person_id', $personId)->exists()) {
            $types[] = self::TYPE_STUDENT;
        }

        if (Guardian::where('person_id', $personId)->exists()) {
            $types[] = self::TYPE_GUARDIAN;
        }

        return $types;
    }

    public function hasAnyAccountType(User $user): bool
    {
        return $this->resolve($user) !== [];
    }
}
