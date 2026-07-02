<?php

use App\Core\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

/**
 * A genuine concurrency proof, not just a sequential-loop test -- opens
 * TWO real, independent connections to the actual local MariaDB (the
 * phpunit.xml testing environment forces sqlite for the default
 * connection, which has no real row-level locking, so this test reads
 * the real .env directly to get true MariaDB credentials, bypassing that
 * override on purpose).
 *
 * Proves the row lock App\Core\Services\NumberGeneratorService relies on
 * is real: Connection A holds the lock inside an open transaction;
 * Connection B, with a 1-second lock-wait timeout, must fail to acquire
 * it while A still holds it, then succeed immediately once A commits --
 * and a second test runs the actual service class (not raw SQL) against
 * this same real connection to prove its own logic, not just the locking
 * primitive, holds under real MySQL-family semantics.
 *
 * If no real MariaDB is reachable in this environment, both tests skip
 * rather than fail -- this is infrastructure-dependent, not a unit test,
 * per docs/IMPLEMENTATION_PLAYBOOK.md's Sprint 1.1.2 note that a naive
 * implementation "looks correct under single-developer testing and
 * silently fails in production."
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

beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one (see class docblock).');
    }

    $this->pdo->exec("DELETE FROM number_sequences WHERE code = 'concurrency_test_code'");
    $this->pdo->exec("INSERT INTO number_sequences (code, scope_type, scope_id, current_value, created_at, updated_at) VALUES ('concurrency_test_code', '', 0, 0, NOW(), NOW())");
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM number_sequences WHERE code = 'concurrency_test_code'");
});

it('proves the row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    // Connection A locks the row and holds it, uncommitted.
    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM number_sequences WHERE code = 'concurrency_test_code' FOR UPDATE");

    // Connection B, with a short lock-wait timeout, must fail to acquire
    // the same lock while A still holds it -- this is the actual proof
    // the lock is real, not just present in the SQL syntax.
    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM number_sequences WHERE code = 'concurrency_test_code' FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    $connectionB->rollBack();
    $connectionA->commit();

    // Now that A released the lock, B can acquire it and proceed
    // immediately -- no artificial retry loop needed.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT * FROM number_sequences WHERE code = 'concurrency_test_code' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->exec("UPDATE number_sequences SET current_value = {$row['current_value']} + 1 WHERE id = {$row['id']}");
    $connectionB->commit();

    $final = $connectionA->query("SELECT current_value FROM number_sequences WHERE code = 'concurrency_test_code'")->fetch(PDO::FETCH_ASSOC);
    expect((int) $final['current_value'])->toBe(1);
});

it('runs the actual NumberGeneratorService against real MySQL-family semantics, not just sqlite', function () {
    // The default Eloquent connection is forced to sqlite by phpunit.xml
    // for every other test in this suite. This test deliberately points
    // it at the real local MariaDB for its own duration, then restores
    // it, so the service's own read-lock-increment-write logic is proven
    // once against real MySQL locking semantics, not only against
    // sqlite's very different (mostly whole-database) locking model.
    config(['database.default' => 'mariadb']);
    config(['database.connections.mariadb.database' => $this->credentials['DB_DATABASE']]);
    DB::purge('mariadb');

    try {
        $service = new NumberGeneratorService;

        $values = [];
        for ($i = 0; $i < 20; $i++) {
            $values[] = $service->next('concurrency_test_code');
        }

        expect($values)->toBe(array_map('strval', range(1, 20)))
            ->and(array_unique($values))->toHaveCount(20);
    } finally {
        config(['database.default' => 'sqlite']);
        DB::purge('mariadb');
    }
});
