<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Administration Platform data boundary
| (docs/adr/0016-administration-platform-data-boundary-and-philosophy.md,
| Decisions 4-5)
|--------------------------------------------------------------------------
|
| Administration Platform's own schema is permanently bounded to four
| shapes -- the Configuration Registry, the Provider Registry/Credential
| Vault, Package/Snapshot artifacts, and the Experience Layer's derived,
| rebuildable compilations -- and its own code must never depend on
| another module's Eloquent models or business logic outside the two
| declared registration contracts (DeclaresSettingsSchema,
| DeclaresProviderSlots, both living in Core). Enforced generically here,
| mirroring the exact discipline already proven for Core
| (CoreBoundaryTest.php) and Identity Maintenance
| (IdentityMaintenanceSchemaDeclarationTest.php) -- not a hand-maintained
| convention, and not deptrac's ruleset alone (deptrac.yaml's own
| Administration: [Core] entry is the other half of this same
| enforcement, per ADR-0016 §5's explicit "the same mechanism already
| proven for Core... and deptrac's module-dependency graph").
|
| Written and proven in Phase 0
| (docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md), against a deliberate
| violation, before the first real Configuration Platform migration
| exists -- the same sequencing already used for Core's temporal-pattern
| enforcement (Blueprint Addendum A3).
|
*/

/**
 * Discovered, not hand-maintained: every other module currently under
 * app/Modules is forbidden. Adding module #16 later extends this list
 * automatically, with no edit to this file required -- the same
 * generic-scanning discipline already established for
 * IdentityMaintenanceSchemaDeclarationTest.php's model scan.
 */
function administrationForbiddenModuleNamespaces(): array
{
    return collect(glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR))
        ->map(fn (string $path) => 'App\\Modules\\'.basename($path))
        ->reject(fn (string $namespace) => $namespace === 'App\Modules\Administration')
        ->values()
        ->all();
}

arch('Administration Platform does not depend on any other module directly')
    ->expect('App\Modules\Administration')
    ->not->toUse(administrationForbiddenModuleNamespaces());

arch('Administration Platform has no dependency on Eloquent models outside itself and Core')
    ->expect('App\Modules\Administration')
    ->not->toUse('App\Models');

/**
 * The four permitted table-name prefixes, per ADR-0016 Decision 4:
 * Configuration Registry ("configuration_"), Provider Registry and
 * Credential Vault ("provider_"), Package/Snapshot artifacts
 * ("package_"/"snapshot_"), and the Experience Layer's derived
 * compilations, e.g. the Dependency Graph ("dependency_"). Deliberately
 * a prefix allowlist, not a fixed table-name list -- Phase 1/2 design
 * their own concrete table names within these shapes; this test does
 * not predict them.
 */
function administrationPermittedTablePrefixes(): array
{
    return ['configuration_', 'provider_', 'package_', 'snapshot_', 'dependency_'];
}

function administrationModelClasses(): array
{
    // realpath() first, then glob() from that already-resolved absolute
    // path -- globbing through the "../.." segments directly leaves them
    // unresolved in the result on Windows, while realpath() elsewhere
    // fully resolves and backslash-normalizes, so a later Str::after()
    // comparing the two silently never matches. Found and fixed while
    // proving this test's own negative case, not a hypothetical.
    $appPath = str_replace('\\', '/', realpath(__DIR__.'/../../app'));
    $files = glob($appPath.'/Modules/Administration/Models/*.php') ?: [];
    $classes = [];

    foreach ($files as $file) {
        $relative = Str::after(str_replace('\\', '/', $file), $appPath.'/');
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    return $classes;
}

// Table-name inspection only needs the model's own $table convention, not
// a live migrated schema -- lighter than
// IdentityMaintenanceSchemaDeclarationTest's Schema::getColumnListing()
// usage, so only TestCase is bound here, not RefreshDatabase.
uses(TestCase::class);

it('requires every Administration Platform model to use one of the four permitted table shapes', function () {
    $violations = [];

    foreach (administrationModelClasses() as $class) {
        $table = (new $class)->getTable();
        $permitted = collect(administrationPermittedTablePrefixes())
            ->contains(fn (string $prefix) => str_starts_with($table, $prefix));

        if (! $permitted) {
            $violations[] = "{$class} (table: {$table})";
        }
    }

    expect($violations)->toBe([]);
});
