<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Sets Spatie Teams' active branch context for role/permission checks
 * in tests -- shared here (not per-file) after Sprint 3.1 discovered
 * two independently-written test files both declaring their own copy,
 * which PHP cannot load together (fatal redeclaration error).
 */
function withTeam(?int $branchId): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
}

/**
 * Real MariaDB connection helpers for genuine dual-connection
 * concurrency proofs (phpunit.xml forces sqlite for the default
 * connection, which has no real row-level locking). Shared here, not
 * per-file, after Sprint 3.2 hit the identical "two test files each
 * declare their own copy" fatal-redeclaration problem a second time
 * (see withTeam() above) -- moved out of
 * tests/Feature/Core/NumberGeneratorConcurrencyTest.php once a second
 * consumer (Sprint 3.2's merge execution-locking test) needed the same
 * helpers.
 */
function realMariadbCredentialsFromDotEnv(): ?array
{
    $path = base_path('.env');
    if (! file_exists($path)) {
        return null;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $values = [];
    foreach ($lines as $line) {
        if (preg_match('/^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=(.*)$/', $line, $m)) {
            $values[$m[1]] = trim($m[2], "\"' ");
        }
    }

    return isset($values['DB_HOST'], $values['DB_DATABASE'], $values['DB_USERNAME']) ? $values : null;
}

function openRealMariadbPdo(array $credentials): ?PDO
{
    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $credentials['DB_HOST'], $credentials['DB_PORT'] ?? 3306, $credentials['DB_DATABASE']),
            $credentials['DB_USERNAME'],
            $credentials['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    } catch (PDOException) {
        return null;
    }
}
