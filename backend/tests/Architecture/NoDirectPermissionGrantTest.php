<?php

use Symfony\Component\Finder\Finder;

/**
 * docs/DOMAIN_BLUEPRINT.md §8: permissions are "never granted directly
 * to a user -- always through a role." Spatie's HasPermissions trait
 * (pulled in via HasRoles on User) technically exposes
 * givePermissionTo()/syncPermissions()/revokePermissionTo() on any
 * model, including User -- nothing in the package itself prevents
 * direct assignment. This must be a structural guarantee re-checked on
 * every run, not a UI omission trusted by convention (the Playbook's
 * explicit DoD wording for this sprint).
 *
 * Seeders are the one legitimate exception: PermissionSeeder calls
 * Role::syncPermissions() to attach permissions to a ROLE, which is
 * precisely the sanctioned path -- this scan only covers app/, the
 * actual application/business-logic layer.
 */
it('never calls Spatie\'s direct permission-assignment methods anywhere in app/', function () {
    $forbidden = ['givePermissionTo(', 'syncPermissions(', 'revokePermissionTo('];

    $offendingLines = [];

    // tests/Architecture isn't bound to Laravel's TestCase (Pest.php
    // only extends Feature) -- no app() container exists here, so the
    // path is resolved relative to this file rather than via app_path().
    $files = Finder::create()->files()->in(__DIR__.'/../../app')->name('*.php');

    foreach ($files as $file) {
        $contents = $file->getContents();

        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $offendingLines[] = "{$file->getRelativePathname()} contains '{$needle}'";
            }
        }
    }

    expect($offendingLines)->toBe([]);
});
