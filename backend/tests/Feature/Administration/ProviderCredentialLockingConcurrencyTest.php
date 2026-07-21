<?php

use Illuminate\Support\Str;

/**
 * A genuine concurrency proof for ProviderCredentialVault::write()'s row
 * lock -- the same real dual-MariaDB-connection pattern already proven
 * in tests/Feature/Administration/ConfigurationValueLockingConcurrencyTest.php
 * and tests/Feature/IdentityMaintenance/MergeExecutionLockingConcurrencyTest.php,
 * applied to the Vault's own table. Reuses the shared
 * realMariadbCredentialsFromDotEnv()/openRealMariadbPdo() helpers, not
 * redeclaring them.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM provider_credentials WHERE slot_key = 'test.vault-concurrency-proof'");
    $this->pdo->exec("DELETE FROM provider_registrations WHERE slot_key = 'test.vault-concurrency-proof'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO provider_registrations (public_id, slot_key, capability_contract, provider_class, credential_fields, owning_module, required_permission_to_edit, deprecation_status, created_at, updated_at) '.
        "VALUES (?, 'test.vault-concurrency-proof', 'test.category', 'Test\\\\FakeProvider', '[{\"name\":\"api_key\",\"type\":\"text\"}]', 'Test', 'test.manage-provider', 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid()]);

    $stmt = $this->pdo->prepare(
        'INSERT INTO provider_credentials (public_id, slot_key, altitude, credentials, version, status, created_at, updated_at) '.
        "VALUES (?, 'test.vault-concurrency-proof', 'global', 'initial-ciphertext', 1, 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid()]);
    $this->credentialId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM provider_credentials WHERE slot_key = 'test.vault-concurrency-proof'");
    $this->pdo?->exec("DELETE FROM provider_registrations WHERE slot_key = 'test.vault-concurrency-proof'");
});

it('proves the credential write-path row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM provider_credentials WHERE id = {$this->credentialId} FOR UPDATE");

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM provider_credentials WHERE id = {$this->credentialId} FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    $connectionA->exec("UPDATE provider_credentials SET credentials = 'written-by-a-ciphertext', version = 2 WHERE id = {$this->credentialId}");
    $connectionB->rollBack();
    $connectionA->commit();

    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT version, credentials FROM provider_credentials WHERE id = {$this->credentialId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect((int) $row['version'])->toBe(2)
        ->and($row['credentials'])->toBe('written-by-a-ciphertext');
});
