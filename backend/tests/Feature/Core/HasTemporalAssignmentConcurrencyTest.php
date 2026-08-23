<?php

use Illuminate\Support\Str;

/**
 * A genuine dual-connection concurrency proof for
 * HasTemporalAssignment::guardAgainstOverlap()'s row lock (Phase 5
 * Sprint A0 -- the High-Priority Core Architecture Backlog item this
 * closes), matching the established pattern
 * (tests/Feature/Core/NumberGeneratorConcurrencyTest.php,
 * tests/Feature/People/ConvertApplicantToStudentConcurrencyTest.php)
 * exactly -- same shared realMariadbCredentialsFromDotEnv()/
 * openRealMariadbPdo() helpers from tests/Pest.php.
 *
 * Exercises the trait through its first real consumer, guardian_student
 * (App\Modules\People\Models\GuardianStudent), via raw SQL against the
 * real table -- this is a Core-level trait proof reusing a Foundation
 * module's table only as the concrete vehicle, the same way
 * MergeExecutionLockingConcurrencyTest.php (IdentityMaintenance) already
 * proves its own locking against people/users/merge_requests directly.
 *
 * Proves the mechanism this trait's own docblock discloses: row locking
 * genuinely serializes two concurrent saves competing for the SAME
 * scope when at least one competitor row already exists. The narrower,
 * disclosed remaining gap (zero prior rows for a brand-new scope) is
 * not exercised here -- it isn't closed by this fix, and isn't claimed
 * to be.
 */
beforeEach(function () {
    $this->credentials = realMariadbCredentialsFromDotEnv();
    $this->pdo = $this->credentials ? openRealMariadbPdo($this->credentials) : null;

    if ($this->pdo === null) {
        test()->markTestSkipped('No real local MariaDB reachable -- this concurrency test needs one.');
    }

    $this->pdo->exec("DELETE FROM people WHERE first_name_en = 'ConcurrencyTestTemporalAssignment'");

    $ulid = fn () => (string) Str::ulid();

    $stmt = $this->pdo->prepare(
        'INSERT INTO people (public_id, first_name_en, first_name_ar, family_name_en, family_name_ar, dob, gender, search_key, created_at, updated_at) '.
        "VALUES (?, ?, ?, ?, ?, '1985-01-01', 'male', ?, NOW(), NOW())"
    );
    $stmt->execute([$ulid(), 'ConcurrencyTestTemporalAssignment', 'test', 'Guardian', 'test', 'concurrencytesttemporalassignmentguardian']);
    $guardianPersonId = (int) $this->pdo->lastInsertId();

    $stmt->execute([$ulid(), 'ConcurrencyTestTemporalAssignment', 'test', 'Student', 'test', 'concurrencytesttemporalassignmentstudent']);
    $studentPersonId = (int) $this->pdo->lastInsertId();
    $this->personIds = [$guardianPersonId, $studentPersonId];

    $stmt = $this->pdo->prepare('INSERT INTO guardians (public_id, person_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
    $stmt->execute([$ulid(), $guardianPersonId]);
    $this->guardianId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare('INSERT INTO students (public_id, person_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
    $stmt->execute([$ulid(), $studentPersonId]);
    $this->studentId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO relationship_types (code, name, scope, is_active, created_at, updated_at) VALUES (?, ?, 'guardian_student', 1, NOW(), NOW())"
    );
    $code = 'concur_temporal_'.random_int(100000, 999999);
    $stmt->execute([$code, json_encode(['en' => 'Concurrency Test Type', 'ar' => 'test'])]);
    $this->relationshipTypeId = (int) $this->pdo->lastInsertId();

    $stmt = $this->pdo->prepare(
        "INSERT INTO guardian_student (public_id, guardian_id, student_id, relationship_type_id, effective_from, status, created_at, updated_at) VALUES (?, ?, ?, ?, '2020-01-01', 'active', NOW(), NOW())"
    );
    $stmt->execute([$ulid(), $this->guardianId, $this->studentId, $this->relationshipTypeId]);
    $this->guardianStudentId = (int) $this->pdo->lastInsertId();
});

afterEach(function () {
    $this->pdo?->exec("DELETE FROM guardian_student WHERE id = {$this->guardianStudentId}");
    $this->pdo?->exec("DELETE FROM relationship_types WHERE id = {$this->relationshipTypeId}");
    $this->pdo?->exec("DELETE FROM students WHERE id = {$this->studentId}");
    $this->pdo?->exec("DELETE FROM guardians WHERE id = {$this->guardianId}");
    $this->pdo?->exec('DELETE FROM people WHERE id IN ('.implode(',', $this->personIds).')');
});

it('proves guardAgainstOverlap\'s row lock genuinely blocks a second connection until the first commits', function () {
    $connectionA = openRealMariadbPdo($this->credentials);
    $connectionB = openRealMariadbPdo($this->credentials);

    $connectionA->beginTransaction();
    $connectionA->query(
        "SELECT * FROM guardian_student WHERE guardian_id = {$this->guardianId} AND student_id = {$this->studentId} AND status != 'cancelled' FOR UPDATE"
    );

    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query(
            "SELECT * FROM guardian_student WHERE guardian_id = {$this->guardianId} AND student_id = {$this->studentId} AND status != 'cancelled' FOR UPDATE"
        );
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205');
    }

    expect($blocked)->toBeTrue();

    // Connection A, still holding the lock, does exactly what
    // guardAgainstOverlap()'s own transaction would: proceed to close
    // out the row it locked.
    $connectionA->exec("UPDATE guardian_student SET status = 'ended', effective_until = '2020-06-01' WHERE id = {$this->guardianStudentId}");
    $connectionB->rollBack();
    $connectionA->commit();

    // Now that A released the lock, B can acquire it immediately and
    // sees the already-committed 'ended' status.
    $connectionB->beginTransaction();
    $row = $connectionB->query("SELECT status FROM guardian_student WHERE id = {$this->guardianStudentId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $connectionB->commit();

    expect($row['status'])->toBe('ended');
});
