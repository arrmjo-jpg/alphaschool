<?php

use App\Core\Contracts\OwnedByAggregate;
use App\Core\Contracts\ReassignsIdentityReferences;
use App\Core\Contracts\RedactsPersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

// This is the first Architecture test needing a real, migrated schema
// (Schema::getColumnListing()) and a fully booted app (app_path()) --
// tests/Pest.php only binds TestCase/RefreshDatabase to Feature by
// default. Scoped to this file only, not a global Pest.php change that
// would affect every other (lightweight, unbooted) Architecture test.
uses(TestCase::class, RefreshDatabase::class);

/**
 * docs/DOMAIN_BLUEPRINT.md Addendum C11: a module holding a Person
 * reference must implement ReassignsIdentityReferences/
 * RedactsPersonalData -- enforced generically here (every Eloquent
 * model under app/Modules/*\/Models and app/Core/Models, scanned
 * against its own actual migrated schema), not via a hand-maintained
 * class list. Supersedes the Sprint 2.4 version of this test, whose own
 * docblock said as much.
 *
 * Simplified per explicit instruction: a model with no Person-reference
 * column asserts nothing at all -- no "declares none" marker interface.
 * A model WITH one of the patterns below (`*_person_id`, `student_id`,
 * `employee_id`, `guardian_id`, per C11's literal wording) must
 * implement both contracts -- UNLESS it implements
 * App\Core\Contracts\OwnedByAggregate instead, declaring itself an owned
 * child entity whose aggregate root (e.g. Person, for Contact/Address/
 * PersonIdentityDocument) already cascades to it. This is not the same
 * as "declares none" -- it's a positive, auditable claim ("I am owned,
 * here is by whom"), not a blanket exemption.
 *
 * SCOPE, RECORDED HERE DELIBERATELY: this scans Eloquent MODEL classes,
 * not raw database tables. A plain pivot table with a Person/Student-
 * shaped column but no dedicated model (e.g. household_members,
 * billing_group_members -- Sprint 2.5) is invisible to this scanner by
 * design, not by oversight. This is not technical debt: the scanner
 * validates models, and a pivot with no model has nothing to validate.
 * If either pivot is ever promoted to a first-class model, it falls
 * under this scanner's coverage automatically, with no change to the
 * scanner itself required.
 */
function identityMaintenanceModelClasses(): array
{
    $files = array_merge(
        glob(app_path('Modules/*/Models/*.php')) ?: [],
        glob(app_path('Core/Models/*.php')) ?: [],
    );

    $classes = [];

    foreach ($files as $file) {
        $relative = Str::after($file, app_path().DIRECTORY_SEPARATOR);
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    return $classes;
}

function tableHasPersonReferenceColumn(string $table): bool
{
    if (! Schema::hasTable($table)) {
        return false;
    }

    foreach (Schema::getColumnListing($table) as $column) {
        if ($column === 'person_id'
            || str_ends_with($column, '_person_id')
            || $column === 'student_id'
            || $column === 'employee_id'
            || $column === 'guardian_id'
        ) {
            return true;
        }
    }

    return false;
}

it('requires every model whose table holds a Person/Student/Employee/Guardian-shaped column to implement both Identity Maintenance contracts', function () {
    $violations = [];

    foreach (identityMaintenanceModelClasses() as $class) {
        $table = (new $class)->getTable();

        if (! tableHasPersonReferenceColumn($table)) {
            continue; // no declaration required at all -- simplified, no marker interface
        }

        if (is_a($class, OwnedByAggregate::class, true)) {
            continue; // owned child entity -- its aggregate root cascades to it instead
        }

        $declaresReassign = is_a($class, ReassignsIdentityReferences::class, true);
        $declaresRedact = is_a($class, RedactsPersonalData::class, true);

        if (! $declaresReassign || ! $declaresRedact) {
            $violations[] = $class;
        }
    }

    expect($violations)->toBe([]);
});

it('requires every OwnedByAggregate declaration to name a real aggregate that itself implements both Identity Maintenance contracts', function () {
    $checked = [];

    foreach (identityMaintenanceModelClasses() as $class) {
        if (! is_a($class, OwnedByAggregate::class, true)) {
            continue;
        }

        $owner = $class::owningAggregate();

        expect(class_exists($owner))
            ->toBeTrue("{$class}::owningAggregate() names '{$owner}', which does not exist.")
            ->and(is_a($owner, ReassignsIdentityReferences::class, true))
            ->toBeTrue("{$class} claims {$owner} owns its reassignment, but {$owner} does not implement ReassignsIdentityReferences.")
            ->and(is_a($owner, RedactsPersonalData::class, true))
            ->toBeTrue("{$class} claims {$owner} owns its redaction, but {$owner} does not implement RedactsPersonalData.");

        $checked[] = $class;
    }

    // The claim this test exists to prove is meaningless if it silently
    // checked zero classes -- Contact/Address/PersonIdentityDocument are
    // the concrete, known consumers as of this sprint.
    expect($checked)->not->toBeEmpty();
});
