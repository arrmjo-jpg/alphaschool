<?php

use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Person;
use App\Modules\People\Models\Student;

/**
 * docs/DOMAIN_BLUEPRINT.md Addendum C11: every module holding a Person
 * reference must declare its Identity Maintenance contract status --
 * implemented, or explicitly "none". This is the lightweight, Sprint
 * 2.4-scoped version of that check: confirms Employee/Student/Guardian
 * (and Person, already built in Sprint 2.1) each implement both
 * contracts. The full schema-scanning version (catching an undeclared
 * Person-referencing column on any future module) is Sprint 3.1's job,
 * not this one.
 */
it('confirms every Person-referencing context aggregate declares both Identity Maintenance contracts', function (string $class) {
    expect(is_a($class, ReassignsIdentityReferences::class, true))->toBeTrue()
        ->and(is_a($class, RedactsPersonalData::class, true))->toBeTrue();
})->with([
    Person::class,
    Employee::class,
    Student::class,
    Guardian::class,
]);
