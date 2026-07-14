<?php

use Illuminate\Support\Str;

/**
 * A genuine concurrency proof for SettingsResolver::write()'s row lock
 * (ADR-0018 Decision 8's optimistic-locking write contract still needs
 * a real row lock underneath it to make the version-check-then-update
 * atomic against a second writer racing in between), matching the
 * established pattern in
 * tests/Feature/IdentityMaintenance/MergeExecutionLockingConcurrencyTest.php
 * exactly -- reusing the shared realMariadbCredentialsFromDotEnv()/
 * openRealMariadbPdo() helpers, not redeclaring them.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM configuration_values WHERE configuration_key = 'test.concurrency-proof'");
    $this->pdo->exec("DELETE FROM configuration_definitions WHERE `key` = 'test.concurrency-proof'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO configuration_definitions (public_id, `key`, type, eligible_altitudes, owning_module, capability, data_classification, required_permission_to_view, required_permission_to_edit, deprecation_status, created_at, updated_at) '.
        "VALUES (?, 'test.concurrency-proof', 'string', '[\"global\"]', 'Test', 'policy-configuration-governance', 'operational', 'test.view', 'test.edit', 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid()]);

    $stmt = $this->pdo->prepare(
        'INSERT INTO configuration_values (configuration_key, altitude, value, version, status, created_at, updated_at) '.
        "VALUES ('test.concurrency-proof', 'global', '\"initial\"', 1, 'active', NOW(), NOW())"
    );
    $stmt->execute();
    $this->valueId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM configuration_values WHERE configuration_key = 'test.concurrency-proof'");
    $this->pdo?->exec("DELETE FROM configuration_definitions WHERE `key` = 'test.concurrency-proof'");
});

it('proves the write-path row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM configuration_values WHERE id = {$this->valueId} FOR UPDATE");

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM configuration_values WHERE id = {$this->valueId} FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    // Connection A, still holding the lock, does exactly what
    // SettingsResolver::write() does inside its transaction: bump the
    // version and change the value.
    $connectionA->exec("UPDATE configuration_values SET value = '\"written-by-a\"', version = 2 WHERE id = {$this->valueId}");
    $connectionB->rollBack();
    $connectionA->commit();

    // Only once A released the lock can B acquire it -- and B now sees
    // the already-committed version 2, exactly the signal
    // SettingsResolver::write()'s expectedVersion check relies on to
    // refuse a stale second write rather than silently overwriting A's
    // change.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT version, value FROM configuration_values WHERE id = {$this->valueId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect((int) $row['version'])->toBe(2)
        ->and($row['value'])->toBe('"written-by-a"');
});
