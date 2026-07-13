<?php

use Illuminate\Support\Str;

/**
 * A genuine concurrency proof for MergeOrchestrationService::execute()'s
 * row lock, matching the established pattern in
 * tests/Feature/Core/NumberGeneratorConcurrencyTest.php exactly (reusing
 * its global realMariadbCredentialsFromDotEnv()/openRealMariadbPdo()
 * helpers rather than redeclaring them -- Sprint 3.2 already found once
 * that PHP fatally errors on duplicate global function declarations
 * across test files, see tests/Pest.php's withTeam()).
 *
 * Proves the same thing NumberGenerator's own first test proves, applied
 * to merge_requests: a connection holding `SELECT ... FOR UPDATE` on a
 * row genuinely blocks a second connection from acquiring the same lock
 * until the first commits -- the actual mechanism
 * MergeOrchestrationService::execute()/rollback() rely on to prevent the
 * same MergeRequest executing twice concurrently.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM people WHERE first_name_en = 'ConcurrencyTestPerson'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO people (public_id, first_name_en, first_name_ar, family_name_en, family_name_ar, dob, gender, search_key, created_at, updated_at) '.
        "VALUES (?, ?, ?, ?, ?, '2000-01-01', 'male', ?, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'ConcurrencyTestPerson', 'test', 'Losing', 'test', 'concurrencytest']);
    $losingId = (int) $this->pdo->lastInsertId();
    $stmt->execute([$ulid(), 'ConcurrencyTestPerson', 'test', 'Winning', 'test', 'concurrencytest']);
    $winningId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO users (public_id, person_id, username, email, password, status, created_at, updated_at) '.
        'VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$ulid(), $losingId, 'concurrency-test-user-'.$losingId, "concurrency-test-{$losingId}@example.invalid", 'x', 'active']);
    $requesterId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        'INSERT INTO merge_requests (public_id, losing_person_id, winning_person_id, status, requested_by_id, created_at, updated_at) '.
        "VALUES (?, ?, ?, 'approved', ?, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), $losingId, $winningId, $requesterId]);
    $this->mergeRequestId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM merge_requests WHERE id = {$this->mergeRequestId}");
    $this->pdo?->exec("DELETE FROM users WHERE username LIKE 'concurrency-test-user-%'");
    $this->pdo?->exec("DELETE FROM people WHERE first_name_en = 'ConcurrencyTestPerson'");
});

it('proves the execution row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query("SELECT * FROM merge_requests WHERE id = {$this->mergeRequestId} FOR UPDATE");

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query("SELECT * FROM merge_requests WHERE id = {$this->mergeRequestId} FOR UPDATE");
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    // Connection A, still holding the lock, does exactly what
    // execute()'s first transaction does: flip status to executing.
    $connectionA->exec("UPDATE merge_requests SET status = 'executing' WHERE id = {$this->mergeRequestId}");
    $connectionB->rollBack();
    $connectionA->commit();

    // Now that A released the lock, B can acquire it immediately and
    // sees the already-committed 'executing' status -- exactly the
    // signal MergeOrchestrationService::execute() uses to refuse a
    // concurrent second attempt, without needing to hold the lock
    // across the entire reassignment transaction.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT status FROM merge_requests WHERE id = {$this->mergeRequestId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect($row['status'])->toBe('executing');
});
